<?php
/**
 * API - 记忆墙照片管理
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/BaseController.php';
require_once __DIR__ . '/../includes/Validator.php';
require_once __DIR__ . '/../includes/cache.php';

class PhotoController extends BaseController {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function handle(): void {
        $method = $this->getMethod();

        try {
            match($method) {
                'GET' => $this->index(),
                'POST' => $this->create(),
                'PUT' => $this->update(),
                'DELETE' => $this->delete(),
                default => $this->error('不支持的方法', 'METHOD_NOT_ALLOWED', 405)
            };
        } catch (ValidationException $e) {
            $this->error($e->getMessage(), 'VALIDATION_ERROR');
        } catch (Throwable $e) {
            Logger::error('Photos API error: ' . $e->getMessage());
            $this->serverError();
        }
    }

    private function index(): void {
        $this->requireAuth();

        $stmt = $this->db->query("SELECT * FROM photos ORDER BY created_at DESC");
        $data = $stmt->fetchAll();

        $this->success($data);
    }

    private function create(): void {
        $this->requireAuth();
        $this->validateRequest();

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (strpos($contentType, 'multipart/form-data') !== false) {
            $this->uploadFile();
        } else {
            $this->addFromUrl();
        }
    }

    private function uploadFile(): void {
        $caption = trim($_POST['caption'] ?? '');

        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->error('请选择要上传的图片', 'VALIDATION_ERROR');
        }

        $file = $_FILES['image'];

        if ($file['size'] > 10 * 1024 * 1024) {
            $this->error('图片大小不能超过 10MB', 'VALIDATION_ERROR');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $allowedTypes)) {
            $this->error('只允许上传图片文件', 'VALIDATION_ERROR');
        }

        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false || !in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP])) {
            $this->error('文件不是有效的图片', 'VALIDATION_ERROR');
        }

        // 纵深防御：拒绝嵌入脚本标记的 polyglot 文件（如 "GIF89a<?php ..."，
        // getimagesize 只校验魔数即可通过）。合法图片二进制几乎不可能在头部出现这些标记
        $head = (string)file_get_contents($file['tmp_name'], false, null, 0, 2048);
        if (preg_match('/<\?|<script/i', $head)) {
            $this->error('文件内容异常，已拒绝上传', 'VALIDATION_ERROR');
        }

        $ext = match($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg'
        };

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $uploadDir = __DIR__ . '/../assets/uploads/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destination = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->serverError('文件保存失败');
        }

        chmod($destination, 0644);

        $url = '/assets/uploads/' . $filename;

        // 先截断到列宽（VARCHAR(200)），避免严格模式写入失败留下孤儿文件
        $caption = mb_substr($caption, 0, 200);

        $stmt = $this->db->prepare("INSERT INTO photos (url, caption, source_type) VALUES (?, ?, 'local')");
        try {
            $stmt->execute([$url, $caption]);
        } catch (PDOException $e) {
            // 文件已落盘但入库失败：清理避免孤儿文件
            @unlink($destination);
            throw $e;
        }

        $id = $this->db->lastInsertId();
        $stmt = $this->db->prepare("SELECT * FROM photos WHERE id = ?");
        $stmt->execute([$id]);
        $newItem = $stmt->fetch();

        Logger::audit('Upload photo', ['id' => $id, 'file' => $filename, 'caption' => $caption]);
        Cache::clear('api_data');
        $this->success($newItem, '上传成功', 201);
    }

    private function addFromUrl(): void {
        $data = $this->getJsonInput();

        $url = trim($data['url'] ?? '');
        $caption = trim($data['caption'] ?? '');

        $errors = Validator::validate(compact('url'), [
            'url' => 'required|url'
        ]);

        if (!empty($errors)) {
            $this->error($errors[0], 'VALIDATION_ERROR');
        }

        if (!isUrlSafe($url)) {
            $this->error('不允许的 URL', 'VALIDATION_ERROR');
        }

        $finalUrl = $this->probeImageUrl($url);
        $url = $finalUrl;

        $stmt = $this->db->prepare("INSERT INTO photos (url, caption, source_type) VALUES (?, ?, 'url')");
        $stmt->execute([$url, $caption]);

        $id = $this->db->lastInsertId();
        $stmt = $this->db->prepare("SELECT * FROM photos WHERE id = ?");
        $stmt->execute([$id]);
        $newItem = $stmt->fetch();

        Logger::audit('Add photo from URL', ['id' => $id, 'url' => $url]);
        Cache::clear('api_data');
        $this->success($newItem, '添加成功', 201);
    }

    /**
     * 逐跳探测图片 URL：关闭自动重定向，每一跳校验 Location 安全与最终 Content-Type
     * 避免 get_headers 合并 header 时多跳场景 Content-Type 校验被绕过
     */
    private function probeImageUrl(string $url): string {
        $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'];
        $current = $url;
        $maxHops = 3;

        for ($hop = 0; $hop <= $maxHops; $hop++) {
            if (!isUrlSafe($current)) {
                $this->error('不允许的 URL（重定向目标不安全）', 'VALIDATION_ERROR');
            }

            $ch = curl_init($current);
            curl_setopt_array($ch, [
                CURLOPT_NOBODY => true,
                CURLOPT_HEADER => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            ]);
            $raw = curl_exec($ch);
            $errno = curl_errno($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);

            if ($raw === false || $errno !== 0) {
                $this->error('图片 URL 无法访问或不存在', 'VALIDATION_ERROR');
            }

            $status = (int)($info['http_code'] ?? 0);
            $contentType = strtolower(trim(explode(';', (string)($info['content_type'] ?? ''))[0]));

            // 3xx：校验 Location 后进入下一跳
            if ($status >= 300 && $status < 400) {
                $location = $this->extractHeader($raw, 'Location');
                if ($location === '') {
                    $this->error('图片 URL 无法访问或不存在', 'VALIDATION_ERROR');
                }
                $location = $this->resolveUrl($current, $location);
                if (!isUrlSafe($location)) {
                    $this->error('不允许的 URL（重定向目标不安全）', 'VALIDATION_ERROR');
                }
                $current = $location;
                continue;
            }

            if ($status !== 200) {
                $this->error('图片 URL 无法访问或不存在', 'VALIDATION_ERROR');
            }

            if (!in_array($contentType, $imageTypes, true)) {
                $this->error('URL 必须指向图片文件', 'VALIDATION_ERROR');
            }

            return $current;
        }

        $this->error('重定向次数过多', 'VALIDATION_ERROR');
        return $url; // unreachable
    }

    private function extractHeader(string $rawHeaders, string $name): string {
        $pattern = '/^' . preg_quote($name, '/') . ':\s*(.+)$/mi';
        if (!preg_match($pattern, $rawHeaders, $m)) {
            return '';
        }
        $lines = explode("\r\n", $m[1]);
        return trim($lines[0]);
    }

    private function resolveUrl(string $base, string $location): string {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $parts = parse_url($base);
        if (!$parts || !isset($parts['scheme'], $parts['host'])) {
            return $location;
        }
        $origin = $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (str_starts_with($location, '//')) {
            return $parts['scheme'] . ':' . $location;
        }
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }
        $path = $parts['path'] ?? '/';
        $dir = rtrim(dirname($path), '/');
        return $origin . ($dir === '' ? '' : $dir) . '/' . $location;
    }

    private function update(): void {
        $this->requireAuth();
        $this->validateRequest();

        $data = $this->getJsonInput();
        $id = $this->resolveId($data);

        $caption = is_string($data['caption'] ?? null) ? trim($data['caption']) : '';
        $caption = mb_substr($caption, 0, 200);
        $url = isset($data['url']) && is_string($data['url']) ? trim($data['url']) : null;

        $stmt = $this->db->prepare("SELECT * FROM photos WHERE id = ?");
        $stmt->execute([$id]);
        $photo = $stmt->fetch();

        if ($url !== null && $url !== '') {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $this->error('无效的图片链接', 'VALIDATION_ERROR');
            }
            if (!isUrlSafe($url)) {
                $this->error('不允许的 URL', 'VALIDATION_ERROR');
            }
            $stmt = $this->db->prepare("UPDATE photos SET caption = ?, url = ?, source_type = 'url', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$caption, $url, $id]);

            // 本地照片改为外链后，旧的本地文件不再被引用，顺手删除避免堆积
            if ($photo && $photo['source_type'] === 'local') {
                $this->removeLocalFile((string)$photo['url']);
            }
        } else {
            $stmt = $this->db->prepare("UPDATE photos SET caption = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$caption, $id]);
        }

        Logger::audit('Update photo', ['id' => $id, 'url' => $url]);
        Cache::clear('api_data');
        $this->success(null, '更新成功');
    }

    private function delete(): void {
        $this->requireAuth();
        $this->validateRequest();

        $id = $this->resolveId($this->getJsonInput());

        $stmt = $this->db->prepare("SELECT * FROM photos WHERE id = ?");
        $stmt->execute([$id]);
        $photo = $stmt->fetch();

        if ($photo && $photo['source_type'] === 'local') {
            $this->removeLocalFile((string)$photo['url']);
        }

        $stmt = $this->db->prepare("DELETE FROM photos WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            $this->error('记录不存在', 'NOT_FOUND', 404);
        }

        Logger::audit('Delete photo', ['id' => $id, 'url' => $photo['url'] ?? '']);
        Cache::clear('api_data');
        $this->success(null, '删除成功');
    }

    /**
     * 删除本地上传的图片文件（带路径穿越防护）
     */
    private function removeLocalFile(string $url): void {
        $uploadDir = realpath(__DIR__ . '/../assets/uploads/');
        $filePath = realpath(__DIR__ . '/..' . $url);

        // 目录分隔符后缀：防止 /assets/uploads-evil 等前缀相同的兄弟目录绕过检查
        if ($filePath !== false && $uploadDir !== false
            && str_starts_with($filePath, $uploadDir . DIRECTORY_SEPARATOR)) {
            @unlink($filePath);
        }
    }
}

$controller = new PhotoController();
$controller->handle();
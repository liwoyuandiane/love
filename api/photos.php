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
        } catch (Exception $e) {
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

        $ext = match($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg'
        };

        $filename = uniqid() . '.' . $ext;
        $uploadDir = __DIR__ . '/../assets/uploads/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destination = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->serverError('文件保存失败');
        }

        $url = '/assets/uploads/' . $filename;

        $stmt = $this->db->prepare("INSERT INTO photos (url, caption, source_type) VALUES (?, ?, 'local')");
        $stmt->execute([$url, $caption]);

        $id = $this->db->lastInsertId();
        $stmt = $this->db->prepare("SELECT * FROM photos WHERE id = ?");
        $stmt->execute([$id]);
        $newItem = $stmt->fetch();

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

        $headers = @get_headers($url, 1);
        if ($headers === false || strpos($headers[0], '200') === false) {
            $this->error('图片 URL 无法访问或不存在', 'VALIDATION_ERROR');
        }

        $contentType = $headers['Content-Type'] ?? '';
        if (is_array($contentType)) {
            $contentType = $contentType[0];
        }

        $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'];
        if (!in_array(strtolower($contentType), $imageTypes)) {
            $this->error('URL 必须指向图片文件', 'VALIDATION_ERROR');
        }

        $stmt = $this->db->prepare("INSERT INTO photos (url, caption, source_type) VALUES (?, ?, 'url')");
        $stmt->execute([$url, $caption]);

        $id = $this->db->lastInsertId();
        $stmt = $this->db->prepare("SELECT * FROM photos WHERE id = ?");
        $stmt->execute([$id]);
        $newItem = $stmt->fetch();

        $this->success($newItem, '添加成功', 201);
    }

    private function update(): void {
        $this->requireAuth();
        $this->validateRequest();

        $data = $this->getJsonInput();
        $id = intval($data['id'] ?? 0);

        if ($id <= 0) {
            $this->error('无效的ID', 'VALIDATION_ERROR');
        }

        $caption = trim($data['caption'] ?? '');

        $stmt = $this->db->prepare("UPDATE photos SET caption = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$caption, $id]);

        $this->success(null, '更新成功');
    }

    private function delete(): void {
        $this->requireAuth();
        $this->validateRequest();

        $data = $this->getJsonInput();
        $id = intval($data['id'] ?? 0);

        if ($id <= 0) {
            $this->error('无效的ID', 'VALIDATION_ERROR');
        }

        $stmt = $this->db->prepare("SELECT * FROM photos WHERE id = ?");
        $stmt->execute([$id]);
        $photo = $stmt->fetch();

        if ($photo && $photo['source_type'] === 'local') {
            $filePath = __DIR__ . '/..' . $photo['url'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $stmt = $this->db->prepare("DELETE FROM photos WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            $this->error('记录不存在', 'NOT_FOUND', 404);
        }

        $this->success(null, '删除成功');
    }
}

$controller = new PhotoController();
$controller->handle();

<?php
/**
 * API - 音乐设置
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/BaseController.php';
require_once __DIR__ . '/../includes/cache.php';

class MusicController extends BaseController {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function handle(): void {
        $method = $this->getMethod();

        try {
            match($method) {
                'GET' => $this->index(),
                'PUT' => $this->update(),
                default => $this->error('不支持的方法', 'METHOD_NOT_ALLOWED', 405)
            };
        } catch (Exception $e) {
            Logger::error('Music API error: ' . $e->getMessage());
            $this->serverError();
        }
    }

    private function index(): void {
        $this->requireAuth();

        $stmt = $this->db->query("SELECT * FROM music WHERE id = 1");
        $music = $stmt->fetch();
        if (!$music) {
            $music = [
                'id' => 1,
                'source_type' => 'url',
                'source_url' => '',
                'backup_url' => '',
                'title' => '背景音乐',
                'artist' => ''
            ];
        }

        $this->success($music);
    }

    private function update(): void {
        $this->requireAuth();
        $this->validateRequest();

        $data = $this->getJsonInput();

        $sourceType = $data['source_type'] ?? 'url';
        $sourceUrl = mb_substr(trim($data['source_url'] ?? ''), 0, 500);
        $backupUrl = mb_substr(trim($data['backup_url'] ?? ''), 0, 500);
        $title = mb_substr(trim($data['title'] ?? ''), 0, 200);
        $artist = mb_substr(trim($data['artist'] ?? ''), 0, 100);

        $allowedSourceTypes = ['url', 'local'];
        if (!in_array($sourceType, $allowedSourceTypes)) {
            $sourceType = 'url';
        }

        if ($sourceType === 'url' && $sourceUrl !== '' && !filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            $this->error('无效的音乐链接', 'VALIDATION_ERROR');
            return;
        }

        if ($backupUrl !== '' && !filter_var($backupUrl, FILTER_VALIDATE_URL)) {
            $backupUrl = '';
        }

        $stmt = $this->db->prepare(
            "UPDATE music SET source_type = ?, source_url = ?, backup_url = ?, title = ?, artist = ?, updated_at = NOW() WHERE id = 1"
        );
        $stmt->execute([$sourceType, $sourceUrl, $backupUrl, $title, $artist]);

        Logger::audit('Update music settings', ['title' => $title]);
        Cache::clear('api_data');
        $this->success(null, '保存成功');
    }
}

$controller = new MusicController();
$controller->handle();

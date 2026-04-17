<?php
/**
 * API - 音乐设置
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/BaseController.php';

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
        $sourceUrl = trim($data['source_url'] ?? '');
        $backupUrl = trim($data['backup_url'] ?? '');
        $title = trim($data['title'] ?? '');
        $artist = trim($data['artist'] ?? '');

        $allowedSourceTypes = ['url', 'local'];
        if (!in_array($sourceType, $allowedSourceTypes)) {
            $sourceType = 'url';
        }

        $stmt = $this->db->prepare(
            "UPDATE music SET source_type = ?, source_url = ?, backup_url = ?, title = ?, artist = ?, updated_at = NOW() WHERE id = 1"
        );
        $stmt->execute([$sourceType, $sourceUrl, $backupUrl, $title, $artist]);

        $this->success(null, '保存成功');
    }
}

$controller = new MusicController();
$controller->handle();

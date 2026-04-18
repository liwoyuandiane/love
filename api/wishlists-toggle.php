<?php
/**
 * API - 愿望清单切换完成状态
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/BaseController.php';
require_once __DIR__ . '/../includes/cache.php';

class WishlistToggleController extends BaseController {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function handle(): void {
        $method = $this->getMethod();

        if ($method !== 'POST') {
            $this->error('不支持的方法', 'METHOD_NOT_ALLOWED', 405);
        }

        try {
            $this->requireAuth();
            $this->validateRequest();

            $id = intval($_GET['id'] ?? 0);

            if ($id <= 0) {
                $this->error('无效的ID', 'VALIDATION_ERROR');
            }

            $stmt = $this->db->prepare("SELECT completed FROM wishlists WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();

            if (!$item) {
                $this->error('愿望不存在', 'NOT_FOUND', 404);
            }

            $newCompleted = $item['completed'] ? 0 : 1;
            $completedAt = $newCompleted ? date('Y-m-d H:i:s') : null;

            $stmt = $this->db->prepare("UPDATE wishlists SET completed = ?, completed_at = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newCompleted, $completedAt, $id]);

            Cache::clear('api_data');
            $this->success(['id' => $id, 'completed' => $newCompleted, 'completed_at' => $completedAt]);

        } catch (Exception $e) {
            Logger::error('Wishlists toggle API error: ' . $e->getMessage());
            $this->serverError();
        }
    }
}

$controller = new WishlistToggleController();
$controller->handle();

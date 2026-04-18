<?php
/**
 * API - 管理员管理（简化版）
 * 仅允许修改当前登录用户的信息
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/BaseController.php';
require_once __DIR__ . '/Validator.php';

class AdminUserController extends BaseController {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function handle(): void {
        $method = $this->getMethod();

        try {
            match($method) {
                'PUT' => $this->update(),
                default => $this->error('不支持的方法', 'METHOD_NOT_ALLOWED', 405)
            };
        } catch (ValidationException $e) {
            $this->error($e->getMessage(), 'VALIDATION_ERROR');
        } catch (Exception $e) {
            Logger::error('Admin users API error: ' . $e->getMessage());
            $this->serverError();
        }
    }

    private function update(): void {
        $this->requireAuth();
        $this->validateRequest();

        $data = $this->getJsonInput();

        $currentUserId = intval($_SESSION['user_id'] ?? 0);
        if ($currentUserId <= 0) {
            $this->error('未登录', 'UNAUTHORIZED', 401);
        }

        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if (empty($username) && empty($password)) {
            $this->error('请填写用户名或密码', 'VALIDATION_ERROR');
        }

        if (!empty($username)) {
            $stmt = $this->db->prepare("SELECT id FROM admin_users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $currentUserId]);
            if ($stmt->fetch()) {
                $this->error('用户名已存在', 'VALIDATION_ERROR');
            }
            $stmt = $this->db->prepare("UPDATE admin_users SET username = ? WHERE id = ?");
            $stmt->execute([$username, $currentUserId]);
            $_SESSION['user_username'] = $username;
        }

        if (!empty($password)) {
            if (!isPasswordStrong($password)) {
                $this->error('密码至少8位，需包含字母和数字', 'VALIDATION_ERROR');
            }
            $hashedPassword = hashPassword($password);
            $stmt = $this->db->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $currentUserId]);
            session_regenerate_id(true);
        }

        Logger::audit('Update current user', ['user_id' => $currentUserId]);
        $this->success(null, '更新成功');
    }
}

$controller = new AdminUserController();
$controller->handle();
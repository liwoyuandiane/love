<?php
/**
 * API - 管理员管理
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/BaseController.php';
require_once __DIR__ . '/../includes/Validator.php';

class AdminUserController extends BaseController {
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
            Logger::error('Admin users API error: ' . $e->getMessage());
            $this->serverError();
        }
    }

    private function index(): void {
        $this->requireAuth();

        $stmt = $this->db->query("SELECT id, username, role, created_at FROM admin_users ORDER BY created_at DESC");
        $users = $stmt->fetchAll();

        $this->success($users);
    }

    private function create(): void {
        $this->requireAdmin();
        $this->validateRequest();

        $data = $this->getJsonInput();

        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $role = $data['role'] ?? 'user';

        $errors = Validator::validate(compact('username', 'password'), [
            'username' => 'required|max:50',
            'password' => 'required|min:8'
        ]);

        if (!empty($errors)) {
            $this->error($errors[0], 'VALIDATION_ERROR');
        }

        if (!isPasswordStrong($password)) {
            $this->error('密码至少8位，需包含字母和数字', 'VALIDATION_ERROR');
        }

        if (!in_array($role, ['admin', 'user'])) {
            $role = 'user';
        }

        $stmt = $this->db->prepare("SELECT id FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $this->error('用户名已存在', 'VALIDATION_ERROR');
        }

        $hashedPassword = hashPassword($password);

        $stmt = $this->db->prepare("INSERT INTO admin_users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $hashedPassword, $role]);

        $id = $this->db->lastInsertId();

        Logger::audit('Create user', ['username' => $username, 'role' => $role, 'by' => $_SESSION['user_username'] ?? 'unknown']);
        $this->success(['id' => $id, 'username' => $username, 'role' => $role], '添加成功', 201);
    }

    private function update(): void {
        $this->requireAdmin();
        $this->validateRequest();

        $data = $this->getJsonInput();
        $id = intval($data['id'] ?? 0);

        if ($id <= 0) {
            $this->error('无效的ID', 'VALIDATION_ERROR');
        }

        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';

        if (!empty($username)) {
            $stmt = $this->db->prepare("SELECT id FROM admin_users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $id]);
            if ($stmt->fetch()) {
                $this->error('用户名已存在', 'VALIDATION_ERROR');
            }
            $stmt = $this->db->prepare("UPDATE admin_users SET username = ? WHERE id = ?");
            $stmt->execute([$username, $id]);
            if ($id === $_SESSION['user_id']) {
                $_SESSION['user_username'] = $username;
            }
        }

        if (!empty($password)) {
            if (!isPasswordStrong($password)) {
                $this->error('密码至少8位，需包含字母和数字', 'VALIDATION_ERROR');
            }
            $hashedPassword = hashPassword($password);
            $stmt = $this->db->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $id]);
        }

        Logger::audit('Update admin user', ['user_id' => $id, 'by' => $_SESSION['user_username'] ?? 'unknown']);
        $this->success(null, '更新成功');
    }

    private function delete(): void {
        $this->requireAdmin();
        $this->validateRequest();

        $data = $this->getJsonInput();
        $id = intval($data['id'] ?? 0);

        if ($id <= 0) {
            $this->error('无效的ID', 'VALIDATION_ERROR');
        }

        if ($id === $_SESSION['user_id']) {
            $this->error('不能删除当前登录账号', 'VALIDATION_ERROR');
        }

        $stmt = $this->db->prepare("DELETE FROM admin_users WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            $this->error('记录不存在', 'NOT_FOUND', 404);
        }

        Logger::audit('Delete admin user', ['user_id' => $id, 'by' => $_SESSION['user_username'] ?? 'unknown']);
        $this->success(null, '删除成功');
    }
}

$controller = new AdminUserController();
$controller->handle();

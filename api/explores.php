<?php
/**
 * API - 探索地点管理
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/BaseController.php';
require_once __DIR__ . '/../includes/Validator.php';

class ExploreController extends BaseController {
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
            Logger::error('Explores API error: ' . $e->getMessage());
            $this->serverError();
        }
    }

    private function index(): void {
        $this->requireAuth();

        $stmt = $this->db->query("SELECT * FROM explores ORDER BY created_at DESC");
        $data = $stmt->fetchAll();

        $this->success($data);
    }

    private function create(): void {
        $this->requireAuth();
        $this->validateRequest();

        $data = $this->getJsonInput();

        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $date = trim($data['date'] ?? '');

        $errors = Validator::validate(compact('title'), [
            'title' => 'required|max:200'
        ]);

        if (!empty($errors)) {
            $this->error($errors[0], 'VALIDATION_ERROR');
        }

        $stmt = $this->db->prepare(
            "INSERT INTO explores (title, description, date) VALUES (?, ?, ?)"
        );
        $stmt->execute([$title, $description, $date ?: null]);

        $id = $this->db->lastInsertId();
        $stmt = $this->db->prepare("SELECT * FROM explores WHERE id = ?");
        $stmt->execute([$id]);
        $newItem = $stmt->fetch();

        Logger::audit('Create explore', ['id' => $id, 'title' => $title]);
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

        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $date = trim($data['date'] ?? '');

        $errors = Validator::validate(compact('title'), [
            'title' => 'required|max:200'
        ]);

        if (!empty($errors)) {
            $this->error($errors[0], 'VALIDATION_ERROR');
        }

        $stmt = $this->db->prepare(
            "UPDATE explores SET title = ?, description = ?, date = ?, updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$title, $description, $date ?: null, $id]);

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

        $stmt = $this->db->prepare("DELETE FROM explores WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            $this->error('记录不存在', 'NOT_FOUND', 404);
        }

        Logger::audit('Delete explore', ['id' => $id]);
        $this->success(null, '删除成功');
    }
}

$controller = new ExploreController();
$controller->handle();

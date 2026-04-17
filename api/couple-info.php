<?php
/**
 * API - 情侣信息管理
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/BaseController.php';
require_once __DIR__ . '/../includes/Validator.php';

class CoupleInfoController extends BaseController {
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
        } catch (ValidationException $e) {
            $this->error($e->getMessage(), 'VALIDATION_ERROR');
        } catch (Exception $e) {
            Logger::error('Couple info API error: ' . $e->getMessage());
            $this->serverError();
        }
    }

    private function index(): void {
        $this->requireAdmin();

        $stmt = $this->db->query("SELECT * FROM couple_info WHERE id = 1");
        $couple = $stmt->fetch();
        if (!$couple) {
            $couple = [
                'id' => 1,
                'name1' => '',
                'name2' => '',
                'anniversary' => date('Y-m-d')
            ];
        }

        $this->success($couple);
    }

    private function update(): void {
        $this->requireAdmin();
        $this->validateRequest();

        $data = $this->getJsonInput();

        $name1 = trim($data['name1'] ?? '');
        $name2 = trim($data['name2'] ?? '');
        $anniversary = trim($data['anniversary'] ?? date('Y-m-d'));

        $errors = Validator::validate(compact('name1', 'name2'), [
            'name1' => 'required|max:50',
            'name2' => 'required|max:50'
        ]);

        if (!empty($errors)) {
            $this->error($errors[0], 'VALIDATION_ERROR');
        }

        $stmt = $this->db->prepare(
            "UPDATE couple_info SET name1 = ?, name2 = ?, anniversary = ?, updated_at = NOW() WHERE id = 1"
        );
        $stmt->execute([$name1, $name2, $anniversary]);

        $this->success(null, '保存成功');
    }
}

$controller = new CoupleInfoController();
$controller->handle();

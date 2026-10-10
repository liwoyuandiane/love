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
require_once __DIR__ . '/../includes/cache.php';
require_once __DIR__ . '/../includes/breakup.php';

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
        } catch (Throwable $e) {
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

        // is_string 防御：非标量输入直接 trim 会抛 TypeError
        $name1 = is_string($data['name1'] ?? null) ? trim($data['name1']) : '';
        $name2 = is_string($data['name2'] ?? null) ? trim($data['name2']) : '';
        $anniversary = is_string($data['anniversary'] ?? null) ? trim($data['anniversary']) : '';

        $errors = Validator::validate(compact('name1', 'name2', 'anniversary'), [
            'name1' => 'required|max:50',
            'name2' => 'required|max:50',
            'anniversary' => 'required|date'
        ]);

        if (!empty($errors)) {
            $this->error($errors[0], 'VALIDATION_ERROR');
        }

        // 修改纪念日会改变分手区间的有效性（如进行中区间起点早于新纪念日，
        // 首页计时会恒为 0），有冲突时拒绝并提示先调整区间
        $stmt = $this->db->query('SELECT id, start_date, end_date FROM breakup_periods');
        $rows = $stmt->fetchAll();
        if (!empty($rows)) {
            $periodError = validateBreakupSet($rows, $anniversary);
            if ($periodError !== null) {
                $this->error('新纪念日与已有分手区间冲突（' . $periodError . '），请先调整分手区间', 'VALIDATION_ERROR');
            }
        }

        $stmt = $this->db->prepare(
            "UPDATE couple_info SET name1 = ?, name2 = ?, anniversary = ?, updated_at = NOW() WHERE id = 1"
        );
        $stmt->execute([$name1, $name2, $anniversary]);

        Logger::audit('Update couple info', ['name1' => $name1, 'name2' => $name2, 'anniversary' => $anniversary]);
        Cache::clear('api_data');
        $this->success(null, '保存成功');
    }
}

$controller = new CoupleInfoController();
$controller->handle();

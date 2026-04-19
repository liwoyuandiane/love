<?php
/**
 * API - 网站设置管理
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/BaseController.php';
require_once __DIR__ . '/../includes/Validator.php';
require_once __DIR__ . '/../includes/cache.php';

class SettingsController extends BaseController {
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
            Logger::error('Settings API error: ' . $e->getMessage());
            $this->serverError();
        }
    }

    private function index(): void {
        $this->requireAdmin();

        $stmt = $this->db->query("SELECT * FROM site_settings WHERE id = 1");
        $settings = $stmt->fetch();
        if (!$settings) {
            $settings = [
                'id' => 1,
                'icp_code' => '',
                'police_record_code' => '',
                'site_name' => '',
                'timezone' => 'Asia/Shanghai'
            ];
        }

        $this->success($settings);
    }

    private function update(): void {
        $this->requireAdmin();
        $this->validateRequest();

        $data = $this->getJsonInput();

        $icpCode = trim($data['icp_code'] ?? '');
        $policeRecordCode = trim($data['police_record_code'] ?? '');
        $siteName = trim($data['site_name'] ?? '');
        $timezone = trim($data['timezone'] ?? 'Asia/Shanghai');

        $errors = Validator::validate(compact('icpCode'), [
            'icpCode' => 'max:100'
        ]);

        if (!empty($errors)) {
            $this->error($errors[0], 'VALIDATION_ERROR');
        }

        $stmt = $this->db->prepare(
            "UPDATE site_settings SET icp_code = ?, police_record_code = ?, site_name = ?, timezone = ?, updated_at = NOW() WHERE id = 1"
        );
        $stmt->execute([$icpCode, $policeRecordCode, $siteName, $timezone]);

        Logger::audit('Update settings', ['timezone' => $timezone]);
        Cache::clear('api_data');
        $this->success(null, '保存成功');
    }
}

$controller = new SettingsController();
$controller->handle();
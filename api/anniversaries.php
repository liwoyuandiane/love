<?php
/**
 * API - 纪念日管理
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/BaseController.php';
require_once __DIR__ . '/../includes/CrudController.php';
require_once __DIR__ . '/../includes/Validator.php';
require_once __DIR__ . '/../includes/cache.php';

class AnniversaryController extends CrudController {
    public function __construct(PDO $db) {
        parent::__construct($db);
        $this->table = 'anniversaries';
        $this->orderBy = 'date DESC';
        $this->resourceLabel = 'anniversary';
    }

    protected function extractFields(array $data): array {
        $type = $data['type'] ?? 'anniversary';
        $allowedTypes = ['anniversary', 'birthday', 'wedding', 'other'];
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'anniversary';
        }
        // is_string 防御：非标量输入直接 trim 会抛 TypeError（500）
        $str = static fn($v): string => is_string($v) ? trim($v) : '';
        return [
            'title' => $str($data['title'] ?? ''),
            'date' => $str($data['date'] ?? ''),
            'description' => $str($data['description'] ?? ''),
            'type' => $type,
            'reminder_days' => intval($data['reminder_days'] ?? 0),
        ];
    }

    protected function validateFields(array $fields, string $op): void {
        $errors = Validator::validate(
            ['title' => $fields['title'], 'date' => $fields['date']],
            ['title' => 'required|max:200', 'date' => 'required|date']
        );
        if (!empty($errors)) {
            $this->error($errors[0], 'VALIDATION_ERROR');
        }
    }

    protected function insertColumns(array $fields): array {
        return [
            ['title', 'date', 'description', 'type', 'reminder_days'],
            [$fields['title'], $fields['date'], $fields['description'], $fields['type'], $fields['reminder_days']],
        ];
    }

    protected function updateColumns(array $fields): array {
        return [
            ['title = ?', 'date = ?', 'description = ?', 'type = ?', 'reminder_days = ?'],
            [$fields['title'], $fields['date'], $fields['description'], $fields['type'], $fields['reminder_days']],
        ];
    }
}

$controller = new AnniversaryController(getDB());
$controller->handle();

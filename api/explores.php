<?php
/**
 * API - 探索地点管理
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/BaseController.php';
require_once __DIR__ . '/../includes/CrudController.php';
require_once __DIR__ . '/../includes/Validator.php';
require_once __DIR__ . '/../includes/cache.php';

class ExploreController extends CrudController {
    public function __construct(PDO $db) {
        parent::__construct($db);
        $this->table = 'explores';
        $this->orderBy = 'created_at DESC';
        $this->resourceLabel = 'explore';
    }

    protected function extractFields(array $data): array {
        // is_string 防御：非标量输入直接 trim 会抛 TypeError（500）
        $str = static fn($v): string => is_string($v) ? trim($v) : '';
        return [
            'title' => $str($data['title'] ?? ''),
            'description' => $str($data['description'] ?? ''),
            'date' => $str($data['date'] ?? ''),
        ];
    }

    protected function validateFields(array $fields, string $op): void {
        $errors = Validator::validate(
            ['title' => $fields['title'], 'date' => $fields['date']],
            ['title' => 'required|max:200', 'date' => 'date']
        );
        if (!empty($errors)) {
            $this->error($errors[0], 'VALIDATION_ERROR');
        }
    }

    protected function insertColumns(array $fields): array {
        return [
            ['title', 'description', 'date'],
            [$fields['title'], $fields['description'], $fields['date'] ?: null],
        ];
    }

    protected function updateColumns(array $fields): array {
        return [
            ['title = ?', 'description = ?', 'date = ?'],
            [$fields['title'], $fields['description'], $fields['date'] ?: null],
        ];
    }
}

$controller = new ExploreController(getDB());
$controller->handle();

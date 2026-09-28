<?php
/**
 * API - 分手区间管理
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/BaseController.php';
require_once __DIR__ . '/../includes/CrudController.php';
require_once __DIR__ . '/../includes/Validator.php';
require_once __DIR__ . '/../includes/cache.php';
require_once __DIR__ . '/../includes/breakup.php';

class BreakupPeriodController extends CrudController {
    /** 当前编辑目标 id，重叠校验时排除自身 */
    private int $currentId = 0;

    public function __construct(PDO $db) {
        parent::__construct($db);
        $this->table = 'breakup_periods';
        $this->orderBy = 'start_date ASC';
        $this->resourceLabel = 'breakup-period';
    }

    protected function extractFields(array $data): array {
        $this->currentId = intval($data['id'] ?? 0) ?: intval($_GET['id'] ?? 0);
        return [
            'start_date' => trim((string)($data['start_date'] ?? '')),
            'end_date' => trim((string)($data['end_date'] ?? '')),
            'note' => trim((string)($data['note'] ?? '')),
        ];
    }

    protected function validateFields(array $fields, string $op): void {
        $errors = Validator::validate($fields, [
            'start_date' => 'required|date',
            'end_date' => 'date',
            'note' => 'max:100',
        ]);
        if (!empty($errors)) {
            $this->error($errors[0], 'VALIDATION_ERROR');
        }

        $rows = [];
        $stmt = $this->db->query('SELECT id, start_date, end_date FROM breakup_periods');
        foreach ($stmt->fetchAll() as $row) {
            if ($this->currentId > 0 && intval($row['id']) === $this->currentId) {
                continue;
            }
            $rows[] = $row;
        }
        $rows[] = ['start_date' => $fields['start_date'], 'end_date' => $fields['end_date']];

        $err = validateBreakupSet($rows, $this->getAnniversary());
        if ($err !== null) {
            $this->error($err, 'VALIDATION_ERROR');
        }
    }

    protected function insertColumns(array $fields): array {
        return [
            ['start_date', 'end_date', 'note'],
            [$fields['start_date'], $fields['end_date'] !== '' ? $fields['end_date'] : null, $fields['note']],
        ];
    }

    protected function updateColumns(array $fields): array {
        return [
            ['start_date = ?', 'end_date = ?', 'note = ?'],
            [$fields['start_date'], $fields['end_date'] !== '' ? $fields['end_date'] : null, $fields['note']],
        ];
    }

    protected function auditFields(array $fields): array {
        return [
            'start_date' => $fields['start_date'],
            'end_date' => $fields['end_date'] !== '' ? $fields['end_date'] : '进行中',
        ];
    }

    private function getAnniversary(): string {
        $stmt = $this->db->query('SELECT anniversary FROM couple_info ORDER BY id LIMIT 1');
        $row = $stmt->fetch();
        return $row ? (string)$row['anniversary'] : '';
    }
}

$controller = new BreakupPeriodController(getDB());
$controller->handle();

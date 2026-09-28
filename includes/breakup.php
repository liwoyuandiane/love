<?php
/**
 * 分手区间业务校验
 * 区间为左闭右开 [start_date, end_date)；end_date 为 null 表示进行中
 */

/**
 * 幂等补齐 schema：breakup_periods 表 + site_settings.show_breakup_days 列
 */
function ensureBreakupSchema(PDO $db): void {
    if (empty($db->query("SHOW TABLES LIKE 'breakup_periods'")->fetchAll())) {
        $db->exec("CREATE TABLE `breakup_periods` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `start_date` DATE NOT NULL,
            `end_date` DATE NULL,
            `note` VARCHAR(100) NOT NULL DEFAULT '',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_start` (`start_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    if (empty($db->query("SHOW COLUMNS FROM site_settings LIKE 'show_breakup_days'")->fetchAll())) {
        $db->exec("ALTER TABLE `site_settings` ADD COLUMN `show_breakup_days` TINYINT(1) NOT NULL DEFAULT 0");
    }
}

/**
 * 归一化日期：返回 Y-m-d（合法）、''（空值）或 null（非法）
 */
function normalizeBreakupDate(mixed $value): ?string {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    if (str_contains($value, 'T')) {
        $value = explode('T', $value)[0];
    }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        return null;
    }
    if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        return null;
    }
    return $value;
}

/**
 * 校验一组分手区间：
 * 1) 每条 start 必填且合法、end 空或合法且不早于 start
 * 2) start 不早于纪念日
 * 3) 区间两两不重叠（end 为 null 视为进行中，持续到永远）
 *
 * @param array $rows 每项含 start_date、end_date
 * @param string $anniversary 纪念日 Y-m-d（非法时跳过该项校验）
 * @return string|null 错误信息；null 表示通过
 */
function validateBreakupSet(array $rows, string $anniversary): ?string {
    $ann = normalizeBreakupDate($anniversary);
    $normalized = [];

    foreach ($rows as $row) {
        $start = normalizeBreakupDate($row['start_date'] ?? '');
        $end = normalizeBreakupDate($row['end_date'] ?? '');

        if ($start === null || $end === null) {
            return '存在无效的日期';
        }
        if ($start === '') {
            return '开始日期为必填项';
        }
        if ($end !== '' && $end < $start) {
            return '结束日期不能早于开始日期';
        }
        if ($ann !== null && $ann !== '' && $start < $ann) {
            return '开始日期不能早于纪念日';
        }

        $normalized[] = ['start' => $start, 'end' => $end === '' ? null : $end];
    }

    usort($normalized, static fn(array $a, array $b): int => strcmp($a['start'], $b['start']));

    $maxEnd = null;
    foreach ($normalized as $item) {
        if ($maxEnd !== null && $item['start'] < $maxEnd) {
            return '分手区间不能重叠';
        }
        $end = $item['end'] ?? '9999-12-31';
        if ($maxEnd === null || $end > $maxEnd) {
            $maxEnd = $end;
        }
    }

    return null;
}

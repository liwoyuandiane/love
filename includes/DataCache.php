<?php
/**
 * 数据缓存层 - 本地缓存 + 延迟同步到远程数据库
 *
 * 策略：
 * - 读取：优先从本地缓存，缓存不存在则查询远程数据库并更新本地缓存
 * - 写入：写入本地缓存并标记为"脏数据"，后台进程定期同步到远程数据库
 * - 同步周期：60秒
 */

require_once __DIR__ . '/db.php';

class DataCache {
    private static string $cacheDir;
    private static string $dataFile = 'site_data.json';
    private static string $dirtyFile = 'dirty_markers.json';
    private static string $syncLockFile = 'sync.lock';
    private static int $syncInterval = 60;

    public static function init(): void {
        self::$cacheDir = sys_get_temp_dir() . '/love_site_data';
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
        @chmod(self::$cacheDir, 0755);
    }

    public static function getData(string $key, callable $fetcher = null): ?array {
        self::init();

        $data = self::loadAllData();
        $cached = $data[$key] ?? null;

        if ($cached === null && $fetcher !== null) {
            try {
                $fresh = $fetcher();
                if ($fresh !== null) {
                    $data[$key] = $fresh;
                    self::saveAllData($data);
                    return $fresh;
                }
            } catch (Exception $e) {
                Logger::error("DataCache fetch error for {$key}: " . $e->getMessage());
            }
        }

        return $cached;
    }

    public static function setData(string $key, array $value): void {
        self::init();

        $data = self::loadAllData();
        $data[$key] = $value;
        self::saveAllData($data);

        self::markDirty($key);
    }

    public static function getAllData(): array {
        self::init();
        return self::loadAllData();
    }

    public static function setAllData(array $data): void {
        self::init();
        self::saveAllData($data);
    }

    public static function markDirty(string $key): void {
        self::init();

        $dirty = self::loadDirtyMarkers();
        $dirty[$key] = time();
        self::saveDirtyMarkers($dirty);
    }

    public static function isDirty(string $key): bool {
        self::init();

        $dirty = self::loadDirtyMarkers();
        return isset($dirty[$key]);
    }

    public static function clearDirty(string $key): void {
        self::init();

        $dirty = self::loadDirtyMarkers();
        unset($dirty[$key]);
        self::saveDirtyMarkers($dirty);
    }

    public static function clearAllDirty(): void {
        self::init();
        self::saveDirtyMarkers([]);
    }

    public static function shouldSync(): bool {
        self::init();

        $dirty = self::loadDirtyMarkers();
        if (empty($dirty)) {
            return false;
        }

        $lockFile = self::$cacheDir . '/' . self::$syncLockFile;
        if (file_exists($lockFile)) {
            $lockTime = intval(file_get_contents($lockFile));
            if (time() - $lockTime < self::$syncInterval) {
                return false;
            }
        }

        return true;
    }

    public static function acquireSyncLock(): bool {
        self::init();
        $lockFile = self::$cacheDir . '/' . self::$syncLockFile;

        if (file_exists($lockFile)) {
            $lockTime = intval(file_get_contents($lockFile));
            if (time() - $lockTime < self::$syncInterval) {
                return false;
            }
        }

        return file_put_contents($lockFile, strval(time()), LOCK_EX) !== false;
    }

    public static function releaseSyncLock(): void {
        self::init();
        $lockFile = self::$cacheDir . '/' . self::$syncLockFile;
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }

    public static function syncToDatabase(): array {
        self::init();

        $dirty = self::loadDirtyMarkers();
        if (empty($dirty)) {
            return ['success' => true, 'synced' => 0];
        }

        if (!self::acquireSyncLock()) {
            return ['success' => false, 'message' => 'Sync already in progress'];
        }

        $synced = 0;
        $errors = [];

        try {
            $db = getDB();
            $data = self::loadAllData();

            foreach ($dirty as $key => $dirtyTime) {
                try {
                    self::syncKeyToDatabase($db, $key, $data[$key] ?? null);
                    unset($dirty[$key]);
                    $synced++;
                } catch (Exception $e) {
                    $errors[$key] = $e->getMessage();
                    Logger::error("Sync error for {$key}: " . $e->getMessage());
                }
            }

            self::saveDirtyMarkers($dirty);

        } finally {
            self::releaseSyncLock();
        }

        return [
            'success' => empty($errors),
            'synced' => $synced,
            'errors' => $errors
        ];
    }

    private static function syncKeyToDatabase(PDO $db, string $key, array $value = null): void {
        switch ($key) {
            case 'coupleInfo':
                if ($value !== null) {
                    $stmt = $db->prepare(
                        "UPDATE couple_info SET name1 = ?, name2 = ?, anniversary = ?, updated_at = NOW() WHERE id = 1"
                    );
                    $stmt->execute([
                        $value['name1'] ?? '',
                        $value['name2'] ?? '',
                        $value['anniversary'] ?? date('Y-m-d')
                    ]);
                }
                break;

            case 'anniversaries':
                if (is_array($value)) {
                    $allowedTypes = ['anniversary', 'birthday', 'wedding', 'other'];
                    foreach ($value as $item) {
                        if (isset($item['id']) && isset($item['title'], $item['date'])) {
                            $type = in_array($item['type'] ?? '', $allowedTypes) ? $item['type'] : 'anniversary';
                            $stmt = $db->prepare(
                                "INSERT INTO anniversaries (id, title, date, description, type, reminder_days, updated_at)
                                 VALUES (?, ?, ?, ?, ?, ?, NOW())
                                 ON DUPLICATE KEY UPDATE title = VALUES(title), date = VALUES(date),
                                 description = VALUES(description), type = VALUES(type),
                                 reminder_days = VALUES(reminder_days), updated_at = NOW()"
                            );
                            $stmt->execute([
                                max(1, min(65535, intval($item['id'] ?? 0))),
                                mb_substr($item['title'] ?? '', 0, 200),
                                $item['date'] ?? null,
                                mb_substr($item['description'] ?? '', 0, 500),
                                $type,
                                max(0, min(365, intval($item['reminder_days'] ?? 0)))
                            ]);
                        }
                    }
                }
                break;

            case 'wishlists':
                if (is_array($value)) {
                    foreach ($value as $item) {
                        if (isset($item['id']) && isset($item['title'])) {
                            $stmt = $db->prepare(
                                "INSERT INTO wishlists (id, title, description, date, completed, completed_at, updated_at)
                                 VALUES (?, ?, ?, ?, ?, ?, NOW())
                                 ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description),
                                 date = VALUES(date), completed = VALUES(completed),
                                 completed_at = VALUES(completed_at), updated_at = NOW()"
                            );
                            $stmt->execute([
                                max(1, min(65535, intval($item['id'] ?? 0))),
                                mb_substr($item['title'] ?? '', 0, 200),
                                mb_substr($item['description'] ?? '', 0, 500),
                                $item['date'] ?? null,
                                intval($item['completed'] ?? 0) ? 1 : 0,
                                $item['completed_at'] ?? null
                            ]);
                        }
                    }
                }
                break;

            case 'explores':
                if (is_array($value)) {
                    foreach ($value as $item) {
                        if (isset($item['id']) && isset($item['title'])) {
                            $stmt = $db->prepare(
                                "INSERT INTO explores (id, title, description, date, updated_at)
                                 VALUES (?, ?, ?, ?, NOW())
                                 ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description),
                                 date = VALUES(date), updated_at = NOW()"
                            );
                            $stmt->execute([
                                max(1, min(65535, intval($item['id'] ?? 0))),
                                mb_substr($item['title'] ?? '', 0, 200),
                                mb_substr($item['description'] ?? '', 0, 500),
                                $item['date'] ?? null
                            ]);
                        }
                    }
                }
                break;

            case 'photos':
                if (is_array($value)) {
                    $allowedSourceTypes = ['local', 'url'];
                    foreach ($value as $item) {
                        if (isset($item['id']) && isset($item['url'])) {
                            $sourceType = in_array($item['source_type'] ?? '', $allowedSourceTypes) ? $item['source_type'] : 'url';
                            $stmt = $db->prepare(
                                "INSERT INTO photos (id, url, caption, source_type, updated_at)
                                 VALUES (?, ?, ?, ?, NOW())
                                 ON DUPLICATE KEY UPDATE url = VALUES(url), caption = VALUES(caption),
                                 source_type = VALUES(source_type), updated_at = NOW()"
                            );
                            $stmt->execute([
                                max(1, min(65535, intval($item['id'] ?? 0))),
                                mb_substr($item['url'] ?? '', 0, 500),
                                mb_substr($item['caption'] ?? '', 0, 200),
                                $sourceType
                            ]);
                        }
                    }
                }
                break;

            case 'music':
                if ($value !== null) {
                    $allowedSourceTypes = ['url', 'local'];
                    $sourceType = in_array($value['source_type'] ?? '', $allowedSourceTypes) ? $value['source_type'] : 'url';
                    $stmt = $db->prepare(
                        "UPDATE music SET source_type = ?, source_url = ?, backup_url = ?, title = ?, artist = ?, updated_at = NOW() WHERE id = 1"
                    );
                    $stmt->execute([
                        $sourceType,
                        mb_substr($value['source_url'] ?? '', 0, 500),
                        mb_substr($value['backup_url'] ?? '', 0, 500),
                        mb_substr($value['title'] ?? '', 0, 200),
                        mb_substr($value['artist'] ?? '', 0, 100)
                    ]);
                }
                break;
        }
    }

    public static function refreshFromDatabase(): bool {
        self::init();

        try {
            $db = getDB();

            $data = [];

            $stmt = $db->query("SELECT * FROM couple_info WHERE id = 1");
            $data['coupleInfo'] = $stmt->fetch();

            $stmt = $db->query("SELECT * FROM anniversaries ORDER BY date DESC");
            $data['anniversaries'] = $stmt->fetchAll();

            $stmt = $db->query("SELECT * FROM wishlists ORDER BY completed ASC, created_at DESC");
            $data['wishlists'] = $stmt->fetchAll();

            $stmt = $db->query("SELECT * FROM explores ORDER BY created_at DESC");
            $data['explores'] = $stmt->fetchAll();

            $stmt = $db->query("SELECT * FROM photos ORDER BY created_at DESC");
            $data['photos'] = $stmt->fetchAll();

            $stmt = $db->query("SELECT * FROM music WHERE id = 1");
            $data['music'] = $stmt->fetch();

            self::saveAllData($data);
            self::clearAllDirty();

            return true;
        } catch (Exception $e) {
            Logger::error('DataCache refresh error: ' . $e->getMessage());
            return false;
        }
    }

    public static function clear(): void {
        self::init();

        $dataFile = self::$cacheDir . '/' . self::$dataFile;
        $dirtyFile = self::$cacheDir . '/' . self::$dirtyFile;

        if (file_exists($dataFile)) unlink($dataFile);
        if (file_exists($dirtyFile)) unlink($dirtyFile);
    }

    private static function loadAllData(): array {
        $file = self::$cacheDir . '/' . self::$dataFile;

        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private static function saveAllData(array $data): void {
        $file = self::$cacheDir . '/' . self::$dataFile;
        $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        file_put_contents($file, $content, LOCK_EX);
    }

    private static function loadDirtyMarkers(): array {
        $file = self::$cacheDir . '/' . self::$dirtyFile;

        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return [];
        }

        $markers = json_decode($content, true);
        return is_array($markers) ? $markers : [];
    }

    private static function saveDirtyMarkers(array $markers): void {
        $file = self::$cacheDir . '/' . self::$dirtyFile;
        $content = json_encode($markers, JSON_UNESCAPED_UNICODE);

        file_put_contents($file, $content, LOCK_EX);
    }

    public static function invalidate(string $key): void {
        self::init();

        $data = self::loadAllData();
        unset($data[$key]);
        self::saveAllData($data);

        $dirty = self::loadDirtyMarkers();
        unset($dirty[$key]);
        self::saveDirtyMarkers($dirty);
    }
}

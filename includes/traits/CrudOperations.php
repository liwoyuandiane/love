<?php
/**
 * 通用 CRUD 操作 Trait
 *
 * 为所有资源控制器提供标准化的增删改查实现
 */

trait CrudOperations {
    private PDO $db;
    protected string $table;
    protected array $fillable = [];
    protected array $joins = [];

    abstract protected function getValidationRules(string $action): array;

    public function index(): void {
        $this->requireAuth();
        $fields = $this->getSelectFields();
        $orderBy = $this->getOrderBy();

        $sql = "SELECT $fields FROM {$this->table}" . $this->getJoins() . " ORDER BY {$orderBy}";
        $stmt = $this->db->query($sql);
        $data = $stmt->fetchAll();

        $this->success($this->transformCollection($data));
    }

    public function create(): void {
        $this->requireAuth();
        $this->validateRequest();

        $data = $this->filterInput($this->getJsonInput());
        $this->validate($data, $this->getValidationRules('create'));

        $result = $this->insert($data);
        $id = $this->db->lastInsertId();

        Logger::audit('Create ' . $this->table, ['id' => $id, 'data' => $data]);

        $record = $this->find($id);
        $this->success($this->transform($record), '添加成功', 201);
    }

    public function update(): void {
        $this->requireAuth();
        $this->validateRequest();

        $data = $this->getJsonInput();
        $id = intval($data['id'] ?? 0);

        if ($id <= 0) {
            $this->error('无效的ID', 'VALIDATION_ERROR');
        }

        $this->validate($data, $this->getValidationRules('update'));

        $filtered = $this->filterInput($data);
        $this->modify($id, $filtered);

        Logger::audit('Update ' . $this->table, ['id' => $id]);

        $this->success(null, '更新成功');
    }

    public function delete(): void {
        $this->requireAuth();
        $this->validateRequest();

        $data = $this->getJsonInput();
        $id = intval($data['id'] ?? 0);

        if ($id <= 0) {
            $this->error('无效的ID', 'VALIDATION_ERROR');
        }

        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            $this->error('记录不存在', 'NOT_FOUND', 404);
        }

        Logger::audit('Delete ' . $this->table, ['id' => $id]);
        $this->success(null, '删除成功');
    }

    protected function find(int $id): ?array {
        $fields = $this->getSelectFields();
        $stmt = $this->db->prepare("SELECT $fields FROM {$this->table}" . $this->getJoins() . " WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    protected function insert(array $data): int {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $types = $this->getCasts();

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);

        $values = [];
        foreach (array_keys($data) as $i => $key) {
            $values[] = $this->cast($key, $data[$key] ?? null, $types);
        }
        $stmt->execute($values);

        return $this->db->lastInsertId();
    }

    protected function modify(int $id, array $data): void {
        if (empty($data)) {
            return;
        }

        $sets = implode(' = ?, ', array_keys($data)) . ' = ?';
        $types = $this->getCasts();

        $sql = "UPDATE {$this->table} SET {$sets}, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        $values = [];
        foreach (array_keys($data) as $key) {
            $values[] = $this->cast($key, $data[$key] ?? null, $types);
        }
        $values[] = $id;

        $stmt->execute($values);
    }

    protected function filterInput(array $data): array {
        $filtered = [];
        foreach ($this->fillable as $field) {
            if (isset($data[$field]) || array_key_exists($field, $data)) {
                $value = $data[$field];
                if (is_string($value)) {
                    $value = trim($value);
                }
                $filtered[$field] = $value;
            }
        }
        return $filtered;
    }

    protected function validate(array $data, array $rules): void {
        foreach ($rules as $field => $ruleSet) {
            $ruleList = explode('|', $ruleSet);
            $value = $data[$field] ?? '';

            foreach ($ruleList as $rule) {
                if (strpos($rule, 'max:') === 0) {
                    $len = intval(substr($rule, 4));
                    if (strlen($value) > $len) {
                        throw new ValidationException("{$field}长度不能超过{$len}个字符");
                    }
                } elseif (strpos($rule, 'min:') === 0) {
                    $len = intval(substr($rule, 4));
                    if (strlen($value) < $len) {
                        throw new ValidationException("{$field}长度不能少于{$len}个字符");
                    }
                } elseif ($rule === 'required' && (is_null($value) || $value === '')) {
                    throw new ValidationException("{$field}为必填项");
                } elseif ($rule === 'nullable' && $value === null) {
                    continue;
                }
            }
        }
    }

    protected function getSelectFields(): string {
        return '*';
    }

    protected function getOrderBy(): string {
        return 'id DESC';
    }

    protected function getJoins(): string {
        return '';
    }

    protected function getCasts(): array {
        return [];
    }

    protected function cast(string $field, mixed $value, array $types): mixed {
        if (isset($types[$field])) {
            return match($types[$field]) {
                'int' => intval($value ?? 0),
                'bool' => !empty($value) ? 1 : 0,
                'string' => mb_substr($value ?? '', 0, 500),
                default => $value
            };
        }
        return $value;
    }

    protected function transform(array $record): array {
        return $record;
    }

    protected function transformCollection(array $records): array {
        return array_map([$this, 'transform'], $records);
    }
}

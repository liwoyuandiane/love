<?php
/**
 * 通用表单 CRUD 控制器
 * anniversaries / wishlists / explores 共用
 */

abstract class CrudController extends BaseController {
    protected PDO $db;
    /** 表名（白名单在子类构造中指定） */
    protected string $table;
    protected string $orderBy;
    /** 资源名，用于审计日志 */
    protected string $resourceLabel;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function handle(): void {
        try {
            match($this->getMethod()) {
                'GET' => $this->index(),
                'POST' => $this->create(),
                'PUT' => $this->update(),
                'DELETE' => $this->delete(),
                default => $this->error('不支持的方法', 'METHOD_NOT_ALLOWED', 405)
            };
        } catch (ValidationException $e) {
            $this->error($e->getMessage(), 'VALIDATION_ERROR');
        } catch (Exception $e) {
            Logger::error($this->resourceLabel . ' API error: ' . $e->getMessage());
            $this->serverError();
        }
    }

    private function index(): void {
        $this->requireAuth();
        $stmt = $this->db->query("SELECT * FROM {$this->table} ORDER BY {$this->orderBy}");
        $this->success($stmt->fetchAll());
    }

    private function create(): void {
        $this->requireAuth();
        $this->validateRequest();

        $data = $this->getJsonInput();
        $fields = $this->extractFields($data);
        $this->validateFields($fields, 'create');

        [$cols, $vals] = $this->insertColumns($fields);
        $placeholders = implode(', ', array_fill(0, count($vals), '?'));
        $this->db->prepare(
            "INSERT INTO {$this->table} (" . implode(', ', $cols) . ") VALUES ($placeholders)"
        )->execute($vals);

        $id = $this->db->lastInsertId();
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        $newItem = $stmt->fetch();

        Logger::audit('Create ' . $this->resourceLabel, ['id' => $id] + $this->auditFields($fields));
        Cache::clear('api_data');
        $this->success($newItem, '添加成功', 201);
    }

    private function update(): void {
        $this->requireAuth();
        $this->validateRequest();

        $data = $this->getJsonInput();
        $id = $this->resolveId($data);
        $fields = $this->extractFields($data);
        $this->validateFields($fields, 'update');

        [$sets, $vals] = $this->updateColumns($fields);
        $vals[] = $id;
        $this->db->prepare(
            "UPDATE {$this->table} SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?"
        )->execute($vals);

        Logger::audit('Update ' . $this->resourceLabel, ['id' => $id] + $this->auditFields($fields));
        Cache::clear('api_data');
        $this->success(null, '更新成功');
    }

    private function delete(): void {
        $this->requireAuth();
        $this->validateRequest();

        $id = $this->resolveId($this->getJsonInput());

        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            $this->error('记录不存在', 'NOT_FOUND', 404);
        }

        Logger::audit('Delete ' . $this->resourceLabel, ['id' => $id]);
        Cache::clear('api_data');
        $this->success(null, '删除成功');
    }

    /** 从请求提取并规范化字段（键为 DB 列名） */
    abstract protected function extractFields(array $data): array;

    /** 校验字段，失败时直接输出错误 */
    abstract protected function validateFields(array $fields, string $op): void;

    /** create 的 INSERT 列与值 */
    abstract protected function insertColumns(array $fields): array;

    /** update 的 SET 子句与值（不含 id） */
    abstract protected function updateColumns(array $fields): array;

    protected function auditFields(array $fields): array {
        return ['title' => $fields['title'] ?? null];
    }
}

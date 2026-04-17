<?php
/**
 * 基础控制器类
 * 提供统一的认证、响应和错误处理
 */

class BaseController {
    protected function requireAuth(): void {
        if (!isLoggedIn()) {
            http_response_code(401);
            jsonResponse(false, null, '请先登录', 'UNAUTHORIZED');
        }
    }

    protected function requireAdmin(): void {
        if (!isAdmin()) {
            http_response_code(403);
            jsonResponse(false, null, '需要管理员权限', 'FORBIDDEN');
        }
    }

    protected function validateRequest(): void {
        verifyCSRF();
    }

    protected function getJsonInput(): array {
        $data = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            jsonResponse(false, null, '无效的JSON数据', 'VALIDATION_ERROR');
        }
        return $data ?? [];
    }

    protected function getMethod(): string {
        return $_SERVER['REQUEST_METHOD'];
    }

    protected function success(array $data = null, string $message = '', int $code = 200): void {
        http_response_code($code);
        jsonResponse(true, $data, $message);
    }

    protected function error(string $message, string $code = 'ERROR', int $httpCode = 400): void {
        http_response_code($httpCode);
        jsonResponse(false, null, $message, $code);
    }

    protected function serverError(string $details = '操作失败'): void {
        Logger::error('Server error: ' . $details);
        http_response_code(500);
        jsonResponse(false, null, '操作失败', 'SERVER_ERROR');
    }

    protected function getIdFromInput(): int {
        $data = $this->getJsonInput();
        $id = intval($data['id'] ?? 0);
        if ($id <= 0) {
            $this->error('无效的ID', 'VALIDATION_ERROR');
        }
        return $id;
    }

    protected function validateRequired(array $data, array $fields): void {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $this->error("{$field}为必填项", 'VALIDATION_ERROR');
            }
        }
    }
}

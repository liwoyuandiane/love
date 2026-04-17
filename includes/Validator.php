<?php
/**
 * 请求验证器
 * 提供统一的输入验证
 */

class ValidationException extends Exception {
    public function __construct(string $message, string $code = 'VALIDATION_ERROR') {
        parent::__construct($message);
        $this->code = $code;
    }
}

class Validator {
    public static function required(mixed $value, string $field): void {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            throw new ValidationException("{$field}为必填项");
        }
    }

    public static function maxLength(string $value, int $length, string $field): void {
        if (strlen($value) > $length) {
            throw new ValidationException("{$field}长度不能超过{$length}个字符");
        }
    }

    public static function minLength(string $value, int $length, string $field): void {
        if (strlen($value) < $length) {
            throw new ValidationException("{$field}长度不能少于{$length}个字符");
        }
    }

    public static function url(string $value, string $field): void {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new ValidationException("{$field}不是有效的URL");
        }
    }

    public static function date(string $value, string $field): void {
        if ($value !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $value);
            if (!($d && $d->format('Y-m-d') === $value)) {
                throw new ValidationException("{$field}不是有效的日期格式");
            }
        }
    }

    public static function email(string $value, string $field): void {
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException("{$field}不是有效的邮箱格式");
        }
    }

    public static function integer(mixed $value, string $field): void {
        if (!is_numeric($value) || intval($value) != $value) {
            throw new ValidationException("{$field}必须是整数");
        }
    }

    public static function inRange(mixed $value, int $min, int $max, string $field): void {
        $num = intval($value);
        if ($num < $min || $num > $max) {
            throw new ValidationException("{$field}必须在{$min}到{$max}之间");
        }
    }

    public static function validate(array $data, array $rules): array {
        $errors = [];
        foreach ($rules as $field => $ruleSet) {
            $ruleList = explode('|', $ruleSet);
            $value = $data[$field] ?? '';
            foreach ($ruleList as $rule) {
                try {
                    if ($rule === 'required') {
                        self::required($value, $field);
                    } elseif (strpos($rule, 'max:') === 0) {
                        $len = intval(substr($rule, 4));
                        self::maxLength($value, $len, $field);
                    } elseif (strpos($rule, 'min:') === 0) {
                        $len = intval(substr($rule, 4));
                        self::minLength($value, $len, $field);
                    } elseif ($rule === 'url') {
                        self::url($value, $field);
                    } elseif ($rule === 'date') {
                        self::date($value, $field);
                    } elseif ($rule === 'email') {
                        self::email($value, $field);
                    }
                } catch (ValidationException $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
        return $errors;
    }
}

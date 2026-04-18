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
    private static array $rules = [
        'required' => [self::class, 'validateRequired'],
        'max' => [self::class, 'validateMax'],
        'min' => [self::class, 'validateMin'],
        'url' => [self::class, 'validateUrl'],
        'date' => [self::class, 'validateDate'],
        'email' => [self::class, 'validateEmail'],
        'integer' => [self::class, 'validateInteger'],
        'inRange' => [self::class, 'validateInRange'],
    ];

    public static function validate(array $data, array $rules): array {
        $errors = [];

        foreach ($rules as $field => $ruleSet) {
            $ruleList = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $data[$field] ?? '';

            foreach ($ruleList as $rule) {
                $params = [];
                if (is_string($rule) && str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                if (isset(self::$rules[$rule])) {
                    try {
                        $validator = self::$rules[$rule];
                        $validator($value, $field, ...$params);
                    } catch (ValidationException $e) {
                        $errors[] = $e->getMessage();
                    }
                }
            }
        }

        return $errors;
    }

    private static function validateRequired(mixed $value, string $field): void {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            throw new ValidationException("{$field}为必填项");
        }
    }

    private static function validateMax(string $value, string $field, string $length): void {
        if (mb_strlen($value) > intval($length)) {
            throw new ValidationException("{$field}长度不能超过{$length}个字符");
        }
    }

    private static function validateMin(string $value, string $field, string $length): void {
        if (mb_strlen($value) < intval($length)) {
            throw new ValidationException("{$field}长度不能少于{$length}个字符");
        }
    }

    private static function validateUrl(string $value, string $field): void {
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
            throw new ValidationException("{$field}不是有效的URL");
        }
    }

    private static function validateDate(string $value, string $field): void {
        if ($value !== '') {
            $parts = explode('-', $value);
            if (count($parts) !== 3) {
                throw new ValidationException("{$field}不是有效的日期格式");
            }
            [$year, $month, $day] = array_map('intval', $parts);
            if (!checkdate($month, $day, $year)) {
                throw new ValidationException("{$field}不是有效的日期");
            }
        }
    }

    private static function validateEmail(string $value, string $field): void {
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException("{$field}不是有效的邮箱格式");
        }
    }

    private static function validateInteger(mixed $value, string $field): void {
        if (!is_numeric($value) || intval($value) != $value) {
            throw new ValidationException("{$field}必须是整数");
        }
    }

    private static function validateInRange(mixed $value, string $field, string $min, string $max): void {
        $num = intval($value);
        if ($num < intval($min) || $num > intval($max)) {
            throw new ValidationException("{$field}必须在{$min}到{$max}之间");
        }
    }

    public static function required(mixed $value, string $field): void {
        self::validateRequired($value, $field);
    }

    public static function maxLength(string $value, int $length, string $field): void {
        self::validateMax($value, $field, (string)$length);
    }

    public static function minLength(string $value, int $length, string $field): void {
        self::validateMin($value, $field, (string)$length);
    }

    public static function url(string $value, string $field): void {
        self::validateUrl($value, $field);
    }

    public static function date(string $value, string $field): void {
        self::validateDate($value, $field);
    }

    public static function email(string $value, string $field): void {
        self::validateEmail($value, $field);
    }

    public static function integer(mixed $value, string $field): void {
        self::validateInteger($value, $field);
    }

    public static function inRange(mixed $value, int $min, int $max, string $field): void {
        self::validateInRange($value, $field, (string)$min, (string)$max);
    }
}

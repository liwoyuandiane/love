<?php
/**
 * 加密工具类
 * 使用 AES-256-CBC 对称加密
 */

class Crypter {
    private static string $key;
    private static string $cipher = 'aes-256-cbc';
    private static int $options = OPENSSL_RAW_DATA;

    private static function getKey(): string {
        if (isset(self::$key)) {
            return self::$key;
        }

        $keyFile = __DIR__ . '/../.encryption.key';

        if (file_exists($keyFile)) {
            self::$key = trim(file_get_contents($keyFile));
        } else {
            self::$key = self::generateKey();
            file_put_contents($keyFile, self::$key, LOCK_EX);
            chmod($keyFile, 0400);
        }

        return self::$key;
    }

    public static function generateKey(): string {
        return bin2hex(openssl_random_pseudo_bytes(32));
    }

    public static function encrypt(string $plaintext): string {
        $key = self::getKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$cipher));

        $encrypted = openssl_encrypt(
            $plaintext,
            self::$cipher,
            $key,
            self::$options,
            $iv
        );

        if ($encrypted === false) {
            throw new Exception('Encryption failed');
        }

        return base64_encode($iv . $encrypted);
    }

    public static function decrypt(string $ciphertext): string {
        $key = self::getKey();
        $data = base64_decode($ciphertext);

        if ($data === false) {
            throw new Exception('Invalid ciphertext');
        }

        $ivLength = openssl_cipher_iv_length(self::$cipher);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        $decrypted = openssl_decrypt(
            $encrypted,
            self::$cipher,
            $key,
            self::$options,
            $iv
        );

        if ($decrypted === false) {
            throw new Exception('Decryption failed');
        }

        return $decrypted;
    }

    public static function isEncrypted(string $value): bool {
        if (empty($value) || strlen($value) < 20) {
            return false;
        }
        return preg_match('/^[A-Za-z0-9+\/=]+$/', $value) && base64_decode($value, true) !== false;
    }

    public static function tryDecrypt(string $value): string {
        if (self::isEncrypted($value)) {
            try {
                return self::decrypt($value);
            } catch (Exception $e) {
                return $value;
            }
        }
        return $value;
    }
}

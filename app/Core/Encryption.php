<?php
declare(strict_types=1);
namespace App\Core;

class Encryption {
    private static ?string $key = null;

    private static function getKey(): string {
        if (self::$key === null) {
            $rawKey = getenv('APP_KEY') ?: 'chave-secreta-padrao-para-desenvolvimento';
            self::$key = hash('sha256', $rawKey, true);
        }
        return self::$key;
    }

    public static function encrypt(string $data): string {
        if (empty($data)) return '';
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($data, 'aes-256-gcm', self::getKey(), OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $encoded): string {
        if (empty($encoded)) return '';
        $decoded = base64_decode($encoded);
        $iv = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);
        return openssl_decrypt($ciphertext, 'aes-256-gcm', self::getKey(), OPENSSL_RAW_DATA, $iv, $tag) ?: '';
    }
}

<?php

namespace App\Support;

class ShortUuid
{
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public static function generate(): string
    {
        $bytes = random_bytes(12);
        $result = '';

        for ($i = 0; $i < 12; $i++) {
            $result .= self::ALPHABET[ord($bytes[$i]) % 62];
        }

        return $result;
    }

    public static function isValid(string $id): bool
    {
        return strlen($id) === 12 && ctype_alnum($id);
    }
}

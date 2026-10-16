<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;

class LegacyPassword
{
    public static function isLegacyMd5(?string $hash): bool
    {
        return $hash !== null && $hash !== "" && preg_match("/^[a-f0-9]{32}$/i", $hash) === 1;
    }

    public static function verify(string $plain, string $stored): array
    {
        if ($stored === "") {
            return ["verified" => false, "needsRehash" => false];
        }
        // MD5 32 hex phải kiểm tra trước: Hash::check() (Laravel 12) ném exception nếu không phải bcrypt.
        if (self::isLegacyMd5($stored)) {
            return [
                "verified" => hash_equals($stored, md5($plain)),
                "needsRehash" => true,
            ];
        }
        if (Hash::check($plain, $stored)) {
            return ["verified" => true, "needsRehash" => Hash::needsRehash($stored)];
        }
        return ["verified" => false, "needsRehash" => false];
    }

    public static function hash(string $plain): string
    {
        return Hash::make($plain);
    }

    public static function strengthMessage(string $plain): string
    {
        if (strlen($plain) < 8) {
            return 'Mật khẩu phải có ít nhất 8 ký tự.';
        }
        if (! preg_match('/[A-Za-zÀ-ỹ]/u', $plain)) {
            return 'Mật khẩu phải có ít nhất 1 chữ cái.';
        }
        if (! preg_match('/\d/', $plain)) {
            return 'Mật khẩu phải có ít nhất 1 chữ số.';
        }
        return "";
    }
}

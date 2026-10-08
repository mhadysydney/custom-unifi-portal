<?php
namespace Portal;

class CookieManager
{
    private const COOKIE_NAME = 'unifi_portal_token';
    private const DAYS = 30;

    public static function set(string $token): void
    {
        setcookie(self::COOKIE_NAME, $token, [
            'expires'  => time() + (self::DAYS * 86400),
            'path'     => '/',
            'secure'   => true,
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function get(): ?string
    {
        return $_COOKIE[self::COOKIE_NAME] ?? null;
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
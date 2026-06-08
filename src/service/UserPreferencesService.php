<?php
declare(strict_types=1);

namespace App\service;

class UserPreferencesService
{
    private const SESSION_KEY = 'user_preferences';

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start(['name' => 'dev-console']);
        }
    }

    public function get(string $key, $default = null)
    {
        return $_SESSION[self::SESSION_KEY][$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $_SESSION[self::SESSION_KEY][$key] = $value;
    }
}
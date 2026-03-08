<?php

namespace OAuth\Core;

class Config
{
    private static array $items = [];

    public static function load(string $path): void
    {
        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                self::$items[trim($key)] = trim($value);
            }
        }
    }

    public static function get(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? self::$items[$key] ?? $default;
        return $value;
    }

    public static function set(string $key, $value): void
    {
        self::$items[$key] = $value;
    }
}
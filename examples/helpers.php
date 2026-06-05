<?php

declare(strict_types=1);

if (!function_exists('require_jsonfast')) {
    function require_jsonfast(): void
    {
        if (class_exists(JsonFast::class, false)) {
            return;
        }

        $root = dirname(__DIR__);
        $ext = match (PHP_OS_FAMILY) {
            'Windows' => $root . '/target/release/php_jsonfast.dll',
            'Darwin' => $root . '/target/release/libphp_jsonfast.dylib',
            default => $root . '/target/release/libphp_jsonfast.so',
        };

        $hint = "php -d extension={$ext} examples/<script>.php";

        if (is_file($ext) && function_exists('dl')) {
            /** @noinspection PhpUndefinedConstantInspection */
            if (@dl(basename($ext))) {
                return;
            }
        }

        fwrite(STDERR, "JsonFast extension is not loaded.\nRun: {$hint}\n");
        exit(1);
    }
}

if (!function_exists('section')) {
    function section(string $title): void
    {
        echo PHP_EOL . str_repeat('=', 72) . PHP_EOL;
        echo $title . PHP_EOL;
        echo str_repeat('=', 72) . PHP_EOL . PHP_EOL;
    }
}

if (!function_exists('show')) {
    function show(mixed $value): void
    {
        if (is_string($value)) {
            echo $value . PHP_EOL;
            return;
        }

        echo json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . PHP_EOL;
    }
}

if (!function_exists('show_json_string')) {
    function show_json_string(string $json): void
    {
        $decoded = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            show($decoded);
            return;
        }

        echo $json . PHP_EOL;
    }
}

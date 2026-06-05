<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_jsonfast();

$base = <<<'JSON'
{
    "name": "Allan",
    "active": true,
    "settings": {
        "theme": "dark",
        "notifications": true
    },
    "tags": ["php", "rust"]
}
JSON;

$overlay = <<<'JSON'
{
    "active": false,
    "role": "admin",
    "settings": {
        "language": "en"
    },
    "tags": ["php", "rust", "json"]
}
JSON;

section('Deep merge (overlay wins on conflicts)');

$merged = JsonFast::merge($base, $overlay);
show($merged);

section('Diff two documents');

$before = '{"name":"Allan","active":true,"tags":["php"]}';
$after = '{"name":"Allan","active":false,"role":"admin","tags":["php","rust"]}';

show(JsonFast::diff($before, $after));

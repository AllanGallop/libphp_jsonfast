<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_jsonfast();

$examples = [
    '01_format_and_validate.php',
    '02_inspect_and_unwrap.php',
    '03_path_access.php',
    '04_merge_and_diff.php',
    '05_schema.php',
];

echo 'JsonFast examples' . PHP_EOL;
echo 'PHP ' . PHP_VERSION . ' | extension loaded: '
    . (extension_loaded('jsonfast') || class_exists(JsonFast::class, false) ? 'yes' : 'no')
    . PHP_EOL;

foreach ($examples as $file) {
    $path = __DIR__ . '/' . $file;
    echo PHP_EOL . str_repeat('#', 72) . PHP_EOL;
    echo "# {$file}" . PHP_EOL;
    echo str_repeat('#', 72) . PHP_EOL;

    require $path;
}

echo PHP_EOL . 'All examples finished.' . PHP_EOL;

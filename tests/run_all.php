<?php

declare(strict_types=1);

$suites = [
    'basic' => __DIR__ . '/basic.php',
    'output' => __DIR__ . '/output.php',
    'path' => __DIR__ . '/path.php',
    'analyse' => __DIR__ . '/analyse.php',
    'strict_json' => __DIR__ . '/strict_json.php',
    'schema' => __DIR__ . '/schema.php',
    'diff' => __DIR__ . '/diff.php',
];

$passed = 0;

foreach ($suites as $name => $file) {
    require $file;
    ++$passed;
    echo "[PASS] {$name}" . PHP_EOL;
}

echo PHP_EOL . "All {$passed} test suites passed." . PHP_EOL;

<?php

declare(strict_types=1);

$driver = null;

if (extension_loaded('pcov')) {
    $driver = 'pcov';
    pcov_start();
} elseif (extension_loaded('xdebug') && version_compare(phpversion('xdebug'), '3.0', '>=')) {
    $driver = 'xdebug';
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
} else {
    fwrite(STDERR, "Coverage requires the pcov or xdebug extension." . PHP_EOL);
    exit(1);
}

require __DIR__ . '/run_all.php';

if ($driver === 'pcov') {
    $collected = pcov_collect();
    pcov_stop();
} else {
    $collected = xdebug_get_code_coverage();
    xdebug_stop_code_coverage();
}

$root = dirname(__DIR__);
$testFiles = glob($root . '/tests/*.php') ?: [];
$testFiles = array_filter(
    $testFiles,
    static fn(string $file): bool => !str_ends_with($file, 'coverage.php')
);

$executable = 0;
$covered = 0;
$byFile = [];

foreach ($testFiles as $file) {
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        continue;
    }

    $fileCovered = $collected[$file] ?? [];
    $fileExecutable = 0;
    $fileHit = 0;

    foreach ($lines as $index => $line) {
        $lineNumber = $index + 1;
        $trimmed = ltrim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '<?php') || str_starts_with($trimmed, '//')) {
            continue;
        }

        ++$fileExecutable;

        if (isset($fileCovered[$lineNumber]) && $fileCovered[$lineNumber] > 0) {
            ++$fileHit;
        }
    }

    $executable += $fileExecutable;
    $covered += $fileHit;
    $byFile[$file] = [
        'executable' => $fileExecutable,
        'covered' => $fileHit,
    ];
}

$percent = $executable > 0 ? ($covered / $executable) * 100 : 0.0;

echo PHP_EOL . "Coverage driver: {$driver}" . PHP_EOL;
echo sprintf("Test line coverage: %.2f%% (%d/%d lines)%s", $percent, $covered, $executable, PHP_EOL);

foreach ($byFile as $file => $stats) {
    $filePercent = $stats['executable'] > 0
        ? ($stats['covered'] / $stats['executable']) * 100
        : 0.0;

    echo sprintf(
        "  %s: %.2f%% (%d/%d)%s",
        basename($file),
        $filePercent,
        $stats['covered'],
        $stats['executable'],
        PHP_EOL
    );
}

$coverageDir = $root . '/coverage';
if (!is_dir($coverageDir) && !mkdir($coverageDir, 0777, true) && !is_dir($coverageDir)) {
    throw new RuntimeException("Unable to create coverage directory: {$coverageDir}");
}

$summary = [
    'driver' => $driver,
    'percent' => round($percent, 2),
    'covered' => $covered,
    'executable' => $executable,
    'files' => array_map(
        static fn(array $stats): array => [
            'percent' => $stats['executable'] > 0
                ? round(($stats['covered'] / $stats['executable']) * 100, 2)
                : 0.0,
            'covered' => $stats['covered'],
            'executable' => $stats['executable'],
        ],
        array_combine(
            array_map('basename', array_keys($byFile)),
            array_values($byFile)
        )
    ),
];

file_put_contents(
    $coverageDir . '/summary.json',
    json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
);

echo "Coverage summary written to coverage/summary.json" . PHP_EOL;

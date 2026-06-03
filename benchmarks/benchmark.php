<?php

declare(strict_types=1);

ini_set('memory_limit', '2048M');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/helpers.php';

use JsonSchema\Validator as JustinRainbowValidator;
use Opis\JsonSchema\Validator as OpisValidator;

if (!class_exists('JsonFast', false)) {
    fwrite(STDERR, "JsonFast extension is required. Load it with -d extension=..." . PHP_EOL);
    exit(1);
}

$iterations = (int) ($argv[1] ?? 1000);
$mediumItems = (int) ($argv[2] ?? 500);
$capacityMaxItems = (int) ($argv[3] ?? 200000);

$runner = new BenchmarkRunner($iterations);
$results = [];

$smallJson = JsonFixtures::smallJson();
$smallData = JsonFixtures::smallDocument();
$mediumJson = JsonFixtures::mediumJson($mediumItems);
$mediumModifiedJson = JsonFixtures::modifiedMediumJson($mediumJson);
$overlayJson = JsonFixtures::overlayJson();
$brokenSmallJson = JsonFixtures::brokenSmallJson();
$brokenMediumJson = JsonFixtures::brokenMediumJson($mediumItems);
$schemaJson = JsonFixtures::mediumSchemaJson();

$brokenSmallBytes = strlen($brokenSmallJson);
$brokenMediumBytes = strlen($brokenMediumJson);
$schemaPayloadBytes = strlen($mediumJson) + strlen($schemaJson);

$opisValidator = new OpisValidator();
$opisSchema = json_decode($schemaJson, false, flags: JSON_THROW_ON_ERROR);
$justinRainbowValidator = new JustinRainbowValidator();
$justinRainbowSchema = json_decode($schemaJson, false, flags: JSON_THROW_ON_ERROR);

echo "JsonFast benchmark" . PHP_EOL;
echo "PHP " . PHP_VERSION . ' | iterations=' . $iterations . ' | medium_items=' . $mediumItems . PHP_EOL;
echo str_repeat('=', 96) . PHP_EOL;

$results[] = $runner->measure(
    'repair_small',
    'jsonfast',
    static function () use ($brokenSmallJson): void {
        JsonFast::repair($brokenSmallJson, JsonFast::REPAIR_ALL, JsonFast::OUTPUT_STRING);
    },
    $brokenSmallBytes,
);

$results[] = $runner->measure(
    'repair_medium',
    'jsonfast',
    static function () use ($brokenMediumJson): void {
        JsonFast::repair($brokenMediumJson, JsonFast::REPAIR_ALL, JsonFast::OUTPUT_STRING);
    },
    $brokenMediumBytes,
);

$results[] = $runner->measure('encode_compact', 'native', static function () use ($smallData): void {
    json_encode($smallData, JSON_THROW_ON_ERROR);
});

$results[] = $runner->measure('encode_compact', 'jsonfast', static function () use ($smallJson): void {
    JsonFast::minify($smallJson, JsonFast::OUTPUT_STRING);
});

$results[] = $runner->measure('beautify', 'native', static function () use ($smallData): void {
    json_encode($smallData, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
});

$results[] = $runner->measure('beautify', 'jsonfast', static function () use ($smallJson): void {
    JsonFast::beautify($smallJson, 4, JsonFast::OUTPUT_STRING);
});

$results[] = $runner->measure('minify', 'native', static function () use ($smallData): void {
    json_encode($smallData, JSON_THROW_ON_ERROR);
});

$results[] = $runner->measure('minify', 'jsonfast', static function () use ($smallJson): void {
    JsonFast::minify($smallJson, JsonFast::OUTPUT_STRING);
});

$results[] = $runner->measure('path_get', 'native', static function () use ($smallJson): void {
    $data = json_decode($smallJson, true, flags: JSON_THROW_ON_ERROR);
    native_get_by_path($data, 'profile.city');
});

$results[] = $runner->measure('path_get', 'jsonfast', static function () use ($smallJson): void {
    JsonFast::get($smallJson, 'profile.city');
});

$results[] = $runner->measure('path_search', 'native', static function () use ($mediumJson): void {
    $data = json_decode($mediumJson, true, flags: JSON_THROW_ON_ERROR);
    array_column($data['users'], 'email');
});

$results[] = $runner->measure('path_search', 'jsonfast', static function () use ($mediumJson): void {
    JsonFast::search($mediumJson, 'users[*].email');
});

$results[] = $runner->measure('merge', 'native', static function () use ($smallJson, $overlayJson): void {
    native_merge_json($smallJson, $overlayJson);
});

$results[] = $runner->measure('merge', 'jsonfast', static function () use ($smallJson, $overlayJson): void {
    JsonFast::merge($smallJson, $overlayJson, JsonFast::OUTPUT_STRING);
});

$results[] = $runner->measure('diff', 'native', static function () use ($mediumJson, $mediumModifiedJson): void {
    native_diff_json($mediumJson, $mediumModifiedJson);
});

$results[] = $runner->measure('diff', 'jsonfast', static function () use ($mediumJson, $mediumModifiedJson): void {
    JsonFast::diff($mediumJson, $mediumModifiedJson);
});

$results[] = $runner->measure(
    'validate_schema',
    'jsonfast',
    static function () use ($mediumJson, $schemaJson): void {
        JsonFast::validateSchema($mediumJson, $schemaJson);
    },
    $schemaPayloadBytes,
);

$results[] = $runner->measure(
    'validate_schema',
    'opis/json-schema',
    static function () use ($mediumJson, $opisValidator, $opisSchema): void {
        $data = json_decode($mediumJson, false, flags: JSON_THROW_ON_ERROR);
        $opisValidator->validate($data, $opisSchema)->isValid();
    },
    $schemaPayloadBytes,
);

$results[] = $runner->measure(
    'validate_schema',
    'justinrainbow/json-schema',
    static function () use ($mediumJson, $justinRainbowValidator, $justinRainbowSchema): void {
        $data = json_decode($mediumJson, false, flags: JSON_THROW_ON_ERROR);
        $justinRainbowValidator->validate($data, $justinRainbowSchema);
    },
    $schemaPayloadBytes,
);

print_results($results);

$capacityRows = run_capacity_benchmarks($capacityMaxItems);
print_capacity_results($capacityRows);

/**
 * @return list<array<string, mixed>>
 */
function run_capacity_benchmarks(int $maxItems): array
{
    $sizes = [1_000, 5_000, 10_000, 25_000, 50_000, 100_000];
    $sizes = array_values(array_filter($sizes, static fn(int $size): bool => $size <= $maxItems));

    if ($sizes === [] || end($sizes) !== $maxItems) {
        $sizes[] = $maxItems;
    }

    $rows = [];
    $memoryLimit = ini_get('memory_limit');

    foreach ($sizes as $items) {
        try {
            $broken = JsonFixtures::brokenMediumJson($items);
            $bytes = strlen($broken);
            $json = JsonFixtures::mediumJson($items);
            $modified = JsonFixtures::modifiedMediumJson($json);

            $repair = measure_capacity('repair', $items, $bytes, static function () use ($broken): void {
                JsonFast::repair($broken, JsonFast::REPAIR_ALL, JsonFast::OUTPUT_STRING);
            });

            $rows[] = ['operation' => 'jsonfast repair', ...$repair];

            $merge = measure_capacity('merge', $items, strlen($json), static function () use ($json, $modified): void {
                JsonFast::merge($json, $modified, JsonFast::OUTPUT_STRING);
            });

            $rows[] = ['operation' => 'jsonfast merge', ...$merge];

            $diff = measure_capacity('diff', $items, strlen($json), static function () use ($json, $modified): void {
                JsonFast::diff($json, $modified);
            });

            $rows[] = ['operation' => 'jsonfast diff', ...$diff];
        } catch (Throwable $e) {
            $rows[] = [
                'operation' => 'capacity limit',
                'items' => $items,
                'bytes' => 0,
                'status' => 'failed: ' . $e->getMessage() . " (memory_limit={$memoryLimit})",
            ];
            break;
        }
    }

    return $rows;
}

/**
 * @return array<string, mixed>
 */
function measure_capacity(string $operation, int $items, int $bytes, callable $callback): array
{
    $iterations = max(3, min(20, (int) (100_000 / max($items, 1))));
    $runner = new BenchmarkRunner($iterations, 1);
    $result = $runner->measure($operation, 'capacity', $callback, $bytes);

    return [
        'items' => $items,
        'bytes' => $bytes,
        'status' => 'ok',
        'mean_ms' => $result->meanMs,
        'p95_ms' => $result->p95Ms,
        'files_per_sec' => $result->filesPerSec(),
        'mb_per_sec' => $result->mbPerSec(),
        'peak_memory' => $result->peakMemoryBytes,
    ];
}

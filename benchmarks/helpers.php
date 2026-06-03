<?php

declare(strict_types=1);

final class BenchmarkResult
{
    public function __construct(
        public readonly string $name,
        public readonly string $implementation,
        public readonly int $iterations,
        public readonly float $meanMs,
        public readonly float $p95Ms,
        public readonly float $opsPerSec,
        public readonly int $peakMemoryBytes,
        public readonly int $payloadBytes = 0,
        public readonly ?string $note = null,
    ) {}

    public function filesPerSec(): float
    {
        return $this->opsPerSec;
    }

    public function mbPerSec(): float
    {
        if ($this->payloadBytes <= 0 || $this->meanMs <= 0) {
            return 0.0;
        }

        return ($this->payloadBytes / 1_048_576) / ($this->meanMs / 1000);
    }
}

final class BenchmarkRunner
{
    public function __construct(
        private readonly int $iterations = 1000,
        private readonly int $warmup = 50,
    ) {}

    /**
     * @param callable(): mixed $callback
     */
    public function measure(
        string $name,
        string $implementation,
        callable $callback,
        int $payloadBytes = 0,
    ): BenchmarkResult {
        for ($i = 0; $i < $this->warmup; ++$i) {
            $callback();
        }

        $times = [];
        $peakMemory = memory_get_usage(true);

        for ($i = 0; $i < $this->iterations; ++$i) {
            gc_collect_cycles();
            $start = hrtime(true);
            $before = memory_get_usage(true);

            $callback();

            $elapsed = (hrtime(true) - $start) / 1_000_000;
            $times[] = $elapsed;
            $peakMemory = max($peakMemory, memory_get_usage(true), $before);
        }

        sort($times);
        $count = count($times);
        $mean = array_sum($times) / $count;
        $p95Index = min($count - 1, (int) ceil($count * 0.95) - 1);
        $p95 = $times[$p95Index];
        $opsPerSec = $mean > 0 ? 1000 / $mean : 0.0;

        return new BenchmarkResult(
            $name,
            $implementation,
            $this->iterations,
            $mean,
            $p95,
            $opsPerSec,
            $peakMemory,
            $payloadBytes,
        );
    }
}

final class JsonFixtures
{
    public static function smallDocument(): array
    {
        return [
            'id' => 1,
            'name' => 'Allan',
            'active' => true,
            'roles' => ['admin', 'user'],
            'profile' => [
                'email' => 'allan@example.com',
                'city' => 'Milton Keynes',
            ],
        ];
    }

    public static function smallJson(): string
    {
        return json_encode(self::smallDocument(), JSON_THROW_ON_ERROR);
    }

    public static function mediumDocument(int $items = 500): array
    {
        $users = [];

        for ($i = 0; $i < $items; ++$i) {
            $users[] = [
                'id' => $i,
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'active' => $i % 2 === 0,
                'tags' => ['php', 'rust', 'json'],
                'meta' => [
                    'created' => '2026-01-01T00:00:00Z',
                    'score' => $i * 1.5,
                ],
            ];
        }

        return [
            'generated_at' => '2026-06-03T12:00:00Z',
            'count' => $items,
            'users' => $users,
        ];
    }

    public static function mediumJson(int $items = 500): string
    {
        return json_encode(self::mediumDocument($items), JSON_THROW_ON_ERROR);
    }

    public static function overlayJson(): string
    {
        return json_encode([
            'active' => false,
            'profile' => ['language' => 'en'],
            'roles' => ['admin', 'user', 'editor'],
        ], JSON_THROW_ON_ERROR);
    }

    public static function modifiedMediumJson(string $baseJson): string
    {
        $data = json_decode($baseJson, true, flags: JSON_THROW_ON_ERROR);
        $data['users'][0]['name'] = 'Changed Name';
        $data['users'][1]['active'] = false;
        $data['users'][] = [
            'id' => 9999,
            'name' => 'Extra User',
            'email' => 'extra@example.com',
            'active' => true,
            'tags' => ['new'],
            'meta' => ['created' => '2026-06-03T00:00:00Z', 'score' => 99.5],
        ];

        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    public static function brokenSmallJson(): string
    {
        return <<<'JSON'
        {
            // user profile
            "name": "Allan",
            "active": true,
        }
        JSON;
    }

    public static function brokenMediumJson(int $items = 500): string
    {
        $lines = [
            '{',
            '  // generated payload',
            '  "generated_at": "2026-06-03T12:00:00Z",',
            '  "count": ' . $items . ',',
            '  "users": [',
        ];

        for ($i = 0; $i < $items; ++$i) {
            $active = $i % 2 === 0 ? 'true' : 'false';
            $lines[] = sprintf(
                '    {"id": %d, "name": "User %d", "email": "user%d@example.com", "active": %s, "tags": ["php", "rust"], "meta": {"created": "2026-01-01T00:00:00Z", "score": %.1f}},',
                $i,
                $i,
                $i,
                $active,
                $i * 1.5
            );
        }

        $lines[] = '  ],';
        $lines[] = '}';

        return implode("\n", $lines);
    }

    public static function mediumSchemaJson(): string
    {
        return <<<'JSON'
        {
            "type": "object",
            "properties": {
                "generated_at": { "type": "string" },
                "count": { "type": "integer" },
                "users": {
                    "type": "array",
                    "items": {
                        "type": "object",
                        "properties": {
                            "id": { "type": "integer" },
                            "name": { "type": "string" },
                            "email": { "type": "string" },
                            "active": { "type": "boolean" },
                            "tags": {
                                "type": "array",
                                "items": { "type": "string" }
                            },
                            "meta": {
                                "type": "object",
                                "properties": {
                                    "created": { "type": "string" },
                                    "score": { "type": "number" }
                                },
                                "required": ["created", "score"]
                            }
                        },
                        "required": ["id", "name", "email", "active", "tags", "meta"]
                    }
                }
            },
            "required": ["generated_at", "count", "users"]
        }
        JSON;
    }
}

function native_get_by_path(array $data, string $path): mixed
{
    $segments = preg_split('/\.(?![^\[]*\])/', $path) ?: [];
    $current = $data;

    foreach ($segments as $segment) {
        if (preg_match('/^([^\[]+)(\[(\d+)\])?$/', $segment, $matches)) {
            $key = $matches[1];
            $current = $current[$key] ?? null;

            if ($current === null) {
                return null;
            }

            if (isset($matches[3])) {
                $current = $current[(int) $matches[3]] ?? null;
            }
        }
    }

    return $current;
}

function native_merge_json(string $baseJson, string $overlayJson): string
{
    $base = json_decode($baseJson, true, flags: JSON_THROW_ON_ERROR);
    $overlay = json_decode($overlayJson, true, flags: JSON_THROW_ON_ERROR);

    return json_encode(array_replace_recursive($base, $overlay), JSON_THROW_ON_ERROR);
}

function native_diff_json(string $beforeJson, string $afterJson): array
{
    $before = json_decode($beforeJson, true, flags: JSON_THROW_ON_ERROR);
    $after = json_decode($afterJson, true, flags: JSON_THROW_ON_ERROR);

    return [
        'added' => array_diff_key(flatten_paths($after), flatten_paths($before)),
        'removed' => array_diff_key(flatten_paths($before), flatten_paths($after)),
        'changed' => changed_paths($before, $after),
    ];
}

/**
 * @return array<string, mixed>
 */
function flatten_paths(mixed $value, string $prefix = '$'): array
{
    $paths = [];

    if (!is_array($value)) {
        return [$prefix => $value];
    }

    if ($value === []) {
        return [$prefix => []];
    }

    if (array_is_list($value)) {
        foreach ($value as $index => $item) {
            $paths += flatten_paths($item, "{$prefix}[{$index}]");
        }

        return $paths;
    }

    foreach ($value as $key => $item) {
        $paths += flatten_paths($item, "{$prefix}.{$key}");
    }

    return $paths;
}

/**
 * @return array<string, array{from: mixed, to: mixed}>
 */
function changed_paths(mixed $before, mixed $after, string $prefix = '$'): array
{
    $changes = [];

    if (gettype($before) !== gettype($after)) {
        return [$prefix => ['from' => $before, 'to' => $after]];
    }

    if (!is_array($before) || !is_array($after)) {
        if ($before !== $after) {
            $changes[$prefix] = ['from' => $before, 'to' => $after];
        }

        return $changes;
    }

    if (array_is_list($before) && array_is_list($after)) {
        $max = max(count($before), count($after));
        for ($i = 0; $i < $max; ++$i) {
            $changes += changed_paths($before[$i] ?? null, $after[$i] ?? null, "{$prefix}[{$i}]");
        }

        return $changes;
    }

    $keys = array_unique([...array_keys($before), ...array_keys($after)]);
    foreach ($keys as $key) {
        $changes += changed_paths($before[$key] ?? null, $after[$key] ?? null, "{$prefix}.{$key}");
    }

    return $changes;
}

function format_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $value = (float) $bytes;
    $unit = 0;

    while ($value >= 1024 && $unit < count($units) - 1) {
        $value /= 1024;
        ++$unit;
    }

    return sprintf('%.2f %s', $value, $units[$unit]);
}

function print_results(array $results): void
{
    $headers = [
        'Benchmark',
        'Implementation',
        'Iterations',
        'Mean (ms)',
        'P95 (ms)',
        'Ops/sec',
        'Files/sec',
        'MB/sec',
        'Peak memory',
    ];
    $rows = [];

    foreach ($results as $result) {
        $rows[] = [
            $result->name,
            $result->implementation,
            (string) $result->iterations,
            sprintf('%.4f', $result->meanMs),
            sprintf('%.4f', $result->p95Ms),
            sprintf('%.0f', $result->opsPerSec),
            sprintf('%.0f', $result->filesPerSec()),
            $result->payloadBytes > 0 ? sprintf('%.2f', $result->mbPerSec()) : '-',
            format_bytes($result->peakMemoryBytes),
        ];
    }

    $widths = [];
    foreach ($headers as $index => $header) {
        $widths[$index] = strlen($header);
    }

    foreach ($rows as $row) {
        foreach ($row as $index => $cell) {
            $widths[$index] = max($widths[$index], strlen($cell));
        }
    }

    $line = static function (array $cells) use ($widths): string {
        $parts = [];
        foreach ($cells as $index => $cell) {
            $parts[] = str_pad($cell, $widths[$index]);
        }

        return implode(' | ', $parts);
    };

    echo $line($headers) . PHP_EOL;
    echo str_repeat('-', array_sum($widths) + (count($widths) * 3) - 1) . PHP_EOL;

    foreach ($rows as $row) {
        echo $line($row) . PHP_EOL;
    }
}

function print_capacity_results(array $rows): void
{
    echo PHP_EOL . 'Large JSON capacity' . PHP_EOL;
    echo str_repeat('-', 72) . PHP_EOL;

    foreach ($rows as $row) {
        echo sprintf(
            "%-22s | items=%5d | size=%10s | %s%s",
            $row['operation'],
            $row['items'],
            format_bytes($row['bytes']),
            $row['status'],
            PHP_EOL
        );

        if (isset($row['mean_ms'])) {
            echo sprintf(
                "                       | mean=%8.2f ms | p95=%8.2f ms | files/s=%8.0f | mb/s=%8.2f | peak=%s%s",
                $row['mean_ms'],
                $row['p95_ms'],
                $row['files_per_sec'] ?? 0,
                $row['mb_per_sec'] ?? 0,
                format_bytes($row['peak_memory']),
                PHP_EOL
            );
        }
    }
}

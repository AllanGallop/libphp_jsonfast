<?php

declare(strict_types=1);

function json_string(mixed $value): string
{
    if (is_string($value)) {
        return $value;
    }

    return json_encode($value, JSON_THROW_ON_ERROR);
}

function assert_json_same(mixed $actual, string $expectedJson): void
{
    assert(json_string($actual) === $expectedJson);
}

function assert_json_contains(mixed $actual, string $expectedJson): void
{
    $actualJson = json_string($actual);
    $expected = json_decode($expectedJson, true, flags: JSON_THROW_ON_ERROR);
    $decoded = json_decode($actualJson, true, flags: JSON_THROW_ON_ERROR);

    assert($decoded === $expected);
}

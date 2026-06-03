<?php

declare(strict_types=1);

test_output_array_default();
test_output_string();
test_output_object();
test_output_constants();

function test_output_array_default(): void
{
    $result = JsonFast::testJsonValue();

    assert(is_array($result));
    assert($result['name'] === 'Allan');
    assert($result['active'] === true);
    assert($result['roles'] === ['admin', 'user']);

    echo "Output array default ok" . PHP_EOL;
}

function test_output_string(): void
{
    $result = JsonFast::testJsonValue(JsonFast::OUTPUT_STRING);

    assert(is_string($result));
    assert(str_contains($result, '"name"'));
    assert(str_contains($result, 'Allan'));

    $decoded = json_decode($result, true, flags: JSON_THROW_ON_ERROR);
    assert($decoded['roles'] === ['admin', 'user']);

    echo "Output string ok" . PHP_EOL;
}

function test_output_object(): void
{
    $result = JsonFast::testJsonValue(JsonFast::OUTPUT_OBJECT);

    assert(is_object($result));
    assert($result->name === 'Allan');
    assert($result->active === true);
    assert(is_array($result->roles));
    assert($result->roles === ['admin', 'user']);

    echo "Output object ok" . PHP_EOL;
}

function test_output_constants(): void
{
    assert(JsonFast::OUTPUT_ARRAY === 0);
    assert(JsonFast::OUTPUT_STRING === 1);
    assert(JsonFast::OUTPUT_OBJECT === 2);

    $json = '{"name":"Allan"}';

    assert(is_array(JsonFast::analyse($json)));
    assert(is_string(JsonFast::analyse($json, JsonFast::OUTPUT_STRING)));
    assert(is_object(JsonFast::getSchema($json, JsonFast::OUTPUT_OBJECT)));

    echo "Output constants ok" . PHP_EOL;
}

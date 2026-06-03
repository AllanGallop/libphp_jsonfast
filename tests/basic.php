<?php

declare(strict_types=1);

test_beautify();
test_minify();
test_beautify_array();
test_unwrap();
test_inspect_invalid();
test_inspect_valid();

function test_beautify(): void
{
    $json = '{"name":"Allan","active":true}';
    $pretty = JsonFast::beautify($json, 4, JsonFast::OUTPUT_STRING);
    $expectedPretty = "{\n    \"name\": \"Allan\",\n    \"active\": true\n}";

    assert($pretty === $expectedPretty);

    echo "Beautify ok" . PHP_EOL;
}

function test_minify(): void
{
    $json = '{"name":"Allan","active":true}';
    $pretty = JsonFast::beautify($json, 4, JsonFast::OUTPUT_STRING);
    $minified = JsonFast::minify($pretty, JsonFast::OUTPUT_STRING);

    assert($minified === $json);

    echo "Minify ok" . PHP_EOL;
}

function test_beautify_array(): void
{
    $json = '{"name":"Allan","active":true}';
    $prettyArray = JsonFast::beautify($json, 4);

    assert(is_array($prettyArray));
    assert($prettyArray['name'] === 'Allan');
    assert($prettyArray['active'] === true);

    echo "Beautify array ok" . PHP_EOL;
}

function test_unwrap(): void
{
    $json = '{"name":"Allan","active":true}';
    $double = '"{\"name\":\"Allan\",\"active\":true}"';
    $single = JsonFast::unwrap($double, 3, JsonFast::OUTPUT_STRING);

    assert($single === $json);

    echo "Unwrap ok" . PHP_EOL;
}

function test_inspect_invalid(): void
{
    $result = JsonFast::inspect('{"name":"allan",}');

    assert($result['valid'] === false);
    assert(isset($result['error']));
    assert($result['line'] === 1);
    assert($result['column'] > 0);

    echo "Inspect invalid ok" . PHP_EOL;
}

function test_inspect_valid(): void
{
    $json = '{"name":"Allan","active":true}';
    $result = JsonFast::inspect($json);

    assert($result['valid'] === true);
    assert($result['error'] === '');
    assert($result['line'] === 0);
    assert($result['column'] === 0);

    echo "Inspect valid ok" . PHP_EOL;
}

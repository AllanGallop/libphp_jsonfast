<?php

declare(strict_types=1);

test_json_diff();
test_schema_diff();
test_diff_output_modes();

function test_json_diff(): void
{
    $before = <<<JSON
    {
        "name": "Allan",
        "active": true,
        "profile": {
            "email": "old@example.com"
        },
        "tags": ["php", "rust"]
    }
    JSON;

    $after = <<<JSON
    {
        "name": "Allan",
        "active": false,
        "role": "admin",
        "profile": {
            "email": "new@example.com"
        },
        "tags": ["php", "rust", "json"]
    }
    JSON;

    $diff = JsonFast::diff($before, $after);

    assert($diff['added']['$.role'] === 'admin');
    assert($diff['added']['$.tags[2]'] === 'json');
    assert($diff['changed']['$.active']['from'] === true);
    assert($diff['changed']['$.active']['to'] === false);
    assert($diff['changed']['$.profile.email']['from'] === 'old@example.com');
    assert($diff['changed']['$.profile.email']['to'] === 'new@example.com');

    echo "JSON diff ok" . PHP_EOL;
}

function test_schema_diff(): void
{
    $schemaA = <<<JSON
    {
        "type": "object",
        "properties": {
            "id": { "type": "integer" },
            "name": { "type": "string" },
            "profile": {
                "type": "object",
                "properties": {
                    "email": { "type": "string" }
                }
            }
        }
    }
    JSON;

    $schemaB = <<<JSON
    {
        "type": "object",
        "properties": {
            "id": { "type": "string" },
            "active": { "type": "boolean" },
            "profile": {
                "type": "object",
                "properties": {
                    "email": { "type": "string" },
                    "age": { "type": "integer" }
                }
            }
        }
    }
    JSON;

    $diff = JsonFast::schemaDiff($schemaA, $schemaB);

    assert(in_array('$.active', $diff['added'], true));
    assert(in_array('$.profile.age', $diff['added'], true));
    assert(in_array('$.name', $diff['removed'], true));
    assert($diff['changed']['$.id']['from'] === 'integer');
    assert($diff['changed']['$.id']['to'] === 'string');

    echo "Schema diff ok" . PHP_EOL;
}

function test_diff_output_modes(): void
{
    $before = '{"name":"Allan"}';
    $after = '{"name":"Bob"}';

    $array = JsonFast::diff($before, $after);
    assert(is_array($array));
    assert(isset($array['changed']));

    $string = JsonFast::diff($before, $after, JsonFast::OUTPUT_STRING);
    assert(is_string($string));
    assert(str_contains($string, 'changed'));

    $object = JsonFast::diff($before, $after, JsonFast::OUTPUT_OBJECT);
    assert(is_object($object));
    assert(isset($object->changed));

    echo "Diff output modes ok" . PHP_EOL;
}

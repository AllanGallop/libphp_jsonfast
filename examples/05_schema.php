<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_jsonfast();

$payload = <<<'JSON'
{
    "id": 1,
    "name": "Allan",
    "active": true,
    "profile": {
        "email": "allan@example.com"
    }
}
JSON;

section('Infer schema from sample JSON');

$schema = JsonFast::getSchema($payload, JsonFast::OUTPUT_STRING);
show_json_string($schema);

section('Validate payload against inferred schema');

show(JsonFast::validateSchema($payload, $schema));

section('Validation errors on invalid data');

$bad = <<<'JSON'
{
    "id": "not-an-integer",
    "name": "Allan",
    "active": true,
    "profile": {
        "email": "allan@example.com"
    }
}
JSON;

show(JsonFast::validateSchema($bad, $schema));

section('Apply schema (defaults, strip unknown keys)');

$schemaWithRules = <<<'JSON'
{
    "type": "object",
    "properties": {
        "name": { "type": "string" },
        "role": { "type": "string", "default": "user" }
    },
    "required": ["name"]
}
JSON;

$input = '{"name":"Allan","extra":"removed"}';

echo "Input:  {$input}\n\nApplied:\n";
echo JsonFast::applySchema($input, $schemaWithRules, JsonFast::OUTPUT_STRING) . PHP_EOL;

section('Schema diff (API contract changes)');

$schemaV1 = '{"type":"object","properties":{"id":{"type":"integer"},"name":{"type":"string"}}}';
$schemaV2 = '{"type":"object","properties":{"id":{"type":"integer"},"name":{"type":"string"},"email":{"type":"string"}}}';

show(JsonFast::schemaDiff($schemaV1, $schemaV2));

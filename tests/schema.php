<?php

declare(strict_types=1);

$json = <<<JSON
{
    "id": 1,
    "name": "Allan",
    "active": true,
    "profile": {
        "email": "allan@example.com"
    }
}
JSON;

$schema = JsonFast::getSchema($json, JsonFast::OUTPUT_STRING);

assert(JsonFast::validate($schema));

$result = JsonFast::validateSchema($json, $schema);
assert($result['valid'] === true);

$bad = <<<JSON
{
    "id": "wrong",
    "name": "Allan",
    "active": true,
    "profile": {
        "email": "allan@example.com"
    }
}
JSON;

$result = JsonFast::validateSchema($bad, $schema);
assert($result['valid'] === false);
assert(count($result['errors']) > 0);

$schemaWithDefault = <<<JSON
{
    "type": "object",
    "properties": {
        "name": {
            "type": "string"
        },
        "role": {
            "type": "string",
            "default": "user"
        }
    },
    "required": ["name"]
}
JSON;

$input = '{"name":"Allan","extra":"remove me"}';

$applied = JsonFast::applySchema($input, $schemaWithDefault, JsonFast::OUTPUT_STRING);
assert(JsonFast::minify($applied, JsonFast::OUTPUT_STRING) === '{"name":"Allan","role":"user"}');

$schemaObject = JsonFast::getSchema($json, JsonFast::OUTPUT_OBJECT);
assert(is_object($schemaObject));
assert(isset($schemaObject->type));

echo "Schema tests ok" . PHP_EOL;

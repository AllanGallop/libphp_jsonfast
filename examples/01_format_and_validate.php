<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_jsonfast();

section('Validate JSON');

$valid = '{"name":"Allan","active":true}';
$invalid = '{"name": Allan}';

echo 'Valid document: ' . (JsonFast::validate($valid) ? 'yes' : 'no') . PHP_EOL;
echo 'Invalid document: ' . (JsonFast::validate($invalid) ? 'yes' : 'no') . PHP_EOL;

section('Beautify and minify');

$compact = '{"name":"Allan","roles":["admin","user"]}';

echo "Pretty-printed (OUTPUT_STRING):\n";
echo JsonFast::beautify($compact, 2, JsonFast::OUTPUT_STRING);

section('Native PHP types (default OUTPUT_ARRAY)');

$data = JsonFast::minify($compact);
show($data);

section('stdClass output (OUTPUT_OBJECT)');

$object = JsonFast::minify($compact, JsonFast::OUTPUT_OBJECT);
echo 'name: ' . $object->name . PHP_EOL;
echo 'first role: ' . $object->roles[0] . PHP_EOL;

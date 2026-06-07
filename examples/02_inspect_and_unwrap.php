<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_jsonfast();

$broken = <<<'JSON'
{
    // user profile from a log file
    name: Allan,
    active: true,
}
JSON;

section('Analyse invalid JSON');

$analysis = JsonFast::analyse($broken);
show([
    'valid' => $analysis['valid'],
    'error' => $analysis['error'],
]);

section('Inspect a syntax error');

$bad = '{"items":[1,2,}';
show(JsonFast::inspect($bad));

section('Unwrap double-encoded JSON');

$inner = '{"event":"login","user":"allan"}';
$doubleEncoded = json_encode($inner, JSON_THROW_ON_ERROR);

echo "Wrapped payload:\n{$doubleEncoded}\n\nUnwrapped:\n";
echo JsonFast::unwrap($doubleEncoded, 3, JsonFast::OUTPUT_STRING);

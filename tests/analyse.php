<?php

declare(strict_types=1);

analyse_outputs();
analyse_valid_json();
analyse_invalid_json();

function analyse_outputs(): void
{
    $json = '{"name":"Allan"}';

    $analysis = JsonFast::analyse($json, JsonFast::OUTPUT_ARRAY);
    assert($analysis['valid'] === true);
    assert($analysis['error'] === null);

    assert(is_string(
        JsonFast::analyse($json, JsonFast::OUTPUT_STRING),
    ));

    echo "Analyse output modes ok" . PHP_EOL;
}

function analyse_valid_json(): void
{
    $analysis = JsonFast::analyse('{"active":true}');

    assert($analysis['valid'] === true);
    assert($analysis['error'] === null);

    echo "Analyse valid JSON ok" . PHP_EOL;
}

function analyse_invalid_json(): void
{
    $analysis = JsonFast::analyse('{"items":[1,2,}');

    assert($analysis['valid'] === false);
    assert(is_array($analysis['error']));
    assert(isset($analysis['error']['message']));
    assert($analysis['error']['line'] > 0);
    assert($analysis['error']['column'] > 0);

    echo "Analyse invalid JSON ok" . PHP_EOL;
}

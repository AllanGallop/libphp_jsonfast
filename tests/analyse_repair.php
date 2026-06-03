<?php

declare(strict_types=1);

analyse_outputs();
detect_repair_comments();
detect_repair_unquoted_strings();
detect_repair_unquoted_keys();
detect_repair_jsonp();
detect_repair_double_encoded();
detect_repair_single_quotes();
detect_repair_multiple_issues();
detect_javascript_object_literal();

function analyse(string $json): array
{
    return JsonFast::analyse($json);
}

function repair_flags(array $analysis): array
{
    return array_column($analysis['repairs'], 'flag');
}

function analyse_outputs(): void
{
    $json = <<<JSON
    {
        // user profile
        "name": "Allan",
        "active": true,
    }
    JSON;

    $analysis = json_decode(
        JsonFast::analyse($json, JsonFast::OUTPUT_STRING),
        true,
        flags: JSON_THROW_ON_ERROR
    );
    $flags = repair_flags($analysis);

    assert($analysis['valid'] === false);
    assert($analysis['repairable'] === true);
    assert(in_array('REPAIR_COMMENTS', $flags, true));
    assert(in_array('REPAIR_TRAILING_COMMAS', $flags, true));

    $analysis = JsonFast::analyse($json, JsonFast::OUTPUT_ARRAY);
    $flags = repair_flags($analysis);

    assert($analysis['valid'] === false);
    assert($analysis['repairable'] === true);
    assert(in_array('REPAIR_COMMENTS', $flags, true));
    assert(in_array('REPAIR_TRAILING_COMMAS', $flags, true));
}

function detect_repair_comments(): void
{
    $json = <<<JSON
    {
        // user profile
        "name": "Allan",
        "active": true,
    }
    JSON;

    $analysis = analyse($json);
    $flags = repair_flags($analysis);

    assert($analysis['valid'] === false);
    assert($analysis['repairable'] === true);
    assert(in_array('REPAIR_COMMENTS', $flags, true));
    assert(in_array('REPAIR_TRAILING_COMMAS', $flags, true));

    $fixed = JsonFast::repair(
        $json,
        JsonFast::REPAIR_COMMENTS | JsonFast::REPAIR_TRAILING_COMMAS,
        JsonFast::OUTPUT_STRING
    );

    assert(JsonFast::validate($fixed) === true);
    assert(JsonFast::minify($fixed, JsonFast::OUTPUT_STRING) === '{"name":"Allan","active":true}');

    echo "Comment Repair ok" . PHP_EOL;
}

function detect_repair_unquoted_strings(): void
{
    $bad = <<<JSON
    {
        "name": Allan,
        "active": true,
    }
    JSON;

    $analysis = analyse($bad);
    $flags = repair_flags($analysis);

    assert($analysis['valid'] === false);
    assert($analysis['repairable'] === true);
    assert(in_array('REPAIR_UNQUOTED_STRINGS', $flags, true));
    assert(in_array('REPAIR_TRAILING_COMMAS', $flags, true));

    $fixed = JsonFast::repair(
        $bad,
        JsonFast::REPAIR_TRAILING_COMMAS | JsonFast::REPAIR_UNQUOTED_STRINGS,
        JsonFast::OUTPUT_STRING
    );

    assert(JsonFast::validate($fixed) === true);
    assert(JsonFast::minify($fixed, JsonFast::OUTPUT_STRING) === '{"name":"Allan","active":true}');

    echo "Quote Repair ok" . PHP_EOL;
}

function detect_repair_jsonp(): void
{
    $jsonp = 'callback({"name":"Allan","active":true});';

    $analysis = analyse($jsonp);
    $flags = repair_flags($analysis);

    assert($analysis['repairable'] === true);
    assert(in_array('REPAIR_JSONP', $flags, true));

    echo "JSONP Repair Detection ok" . PHP_EOL;
}

function detect_repair_double_encoded(): void
{
    $json = '{"name":"Allan","active":true}';
    $double = '"{\"name\":\"Allan\",\"active\":true}"';

    $analysis = analyse($double);
    $flags = repair_flags($analysis);

    assert($analysis['valid'] === true);
    assert($analysis['repairable'] === true);
    assert(in_array('REPAIR_DOUBLE_ENCODED', $flags, true));

    $fixed = JsonFast::repair($double, JsonFast::REPAIR_DOUBLE_ENCODED, JsonFast::OUTPUT_STRING);

    assert(JsonFast::validate($fixed) === true);
    assert(JsonFast::minify($fixed, JsonFast::OUTPUT_STRING) === $json);

    echo "Double encoded repair ok" . PHP_EOL;
}

function detect_repair_single_quotes(): void
{
    $bad = <<<JSON
    {
        'name': 'Allan',
        'active': true
    }
    JSON;

    $analysis = analyse($bad);
    $flags = repair_flags($analysis);

    assert(in_array('REPAIR_SINGLE_QUOTES', $flags, true));

    $fixed = JsonFast::repair(
        $bad,
        JsonFast::REPAIR_SINGLE_QUOTES,
        JsonFast::OUTPUT_STRING
    );

    assert(JsonFast::validate($fixed));
    assert(JsonFast::minify($fixed, JsonFast::OUTPUT_STRING) === '{"name":"Allan","active":true}');

    echo "Single quote repair ok" . PHP_EOL;
}

function detect_repair_unquoted_keys(): void
{
    $bad = <<<JSON
    {
        name: "Allan",
        active: true
    }
    JSON;

    $analysis = analyse($bad);
    $flags = repair_flags($analysis);

    assert(in_array('REPAIR_UNQUOTED_KEYS', $flags, true));

    $fixed = JsonFast::repair(
        $bad,
        JsonFast::REPAIR_UNQUOTED_KEYS,
        JsonFast::OUTPUT_STRING
    );

    assert(JsonFast::validate($fixed));
    assert(JsonFast::minify($fixed, JsonFast::OUTPUT_STRING) === '{"name":"Allan","active":true}');

    echo "Unquoted key repair ok" . PHP_EOL;
}

function detect_repair_multiple_issues(): void
{
    $bad = <<<JSON
    callback({
        // user
        "name": Allan,
        "active": true,
    });
    JSON;

    $analysis = analyse($bad);
    $flags = repair_flags($analysis);

    assert(in_array('REPAIR_JSONP', $flags, true));
    assert(in_array('REPAIR_COMMENTS', $flags, true));
    assert(in_array('REPAIR_TRAILING_COMMAS', $flags, true));
    assert(in_array('REPAIR_UNQUOTED_STRINGS', $flags, true));

    $fixed = JsonFast::repair(
        $bad,
        JsonFast::REPAIR_JSONP
        | JsonFast::REPAIR_COMMENTS
        | JsonFast::REPAIR_TRAILING_COMMAS
        | JsonFast::REPAIR_UNQUOTED_STRINGS,
        JsonFast::OUTPUT_STRING
    );

    assert(JsonFast::validate($fixed));

    echo "Multiple repair detection ok" . PHP_EOL;
}

function detect_javascript_object_literal(): void
{
    $bad = <<<JSON
    callback({
        // user
        name: 'Allan',
        active: true,
        role: Admin,
    });
    JSON;

    $fixed = JsonFast::repair(
        $bad,
        JsonFast::REPAIR_ALL,
        JsonFast::OUTPUT_STRING
    );

    assert(JsonFast::validate($fixed));
    assert(JsonFast::minify($fixed, JsonFast::OUTPUT_STRING) === '{"name":"Allan","active":true,"role":"Admin"}');

    echo "JavaScript object literal repair ok" . PHP_EOL;
}

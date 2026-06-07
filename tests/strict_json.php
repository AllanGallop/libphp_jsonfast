<?php

declare(strict_types=1);

reject_non_standard_boolean_literals();
reject_unquoted_keys_and_single_quotes();
preserve_json_boolean_semantics();
repair_api_removed();

function reject_non_standard_boolean_literals(): void
{
    assert_rejected('{"is_admin":False}');
    assert_rejected('{"is_admin":True}');

    echo "Non-standard boolean literals rejected ok" . PHP_EOL;
}

function reject_unquoted_keys_and_single_quotes(): void
{
    assert_rejected('{is_admin:false}');
    assert_rejected("{'is_admin':false}");
    assert_rejected('{"active":true,}');

    echo "Non-standard JSON syntax rejected ok" . PHP_EOL;
}

function preserve_json_boolean_semantics(): void
{
    $parsed = JsonFast::minify('{"is_admin":false}');

    assert(is_array($parsed));
    assert(array_key_exists('is_admin', $parsed));
    assert($parsed['is_admin'] === false);
    assert($parsed['is_admin'] !== 'False');
    assert((bool) $parsed['is_admin'] === false);

    echo "JSON boolean semantics preserved ok" . PHP_EOL;
}

function repair_api_removed(): void
{
    assert(!method_exists(JsonFast::class, 'repair'));
    assert(!defined(JsonFast::class . '::REPAIR_ALL'));

    echo "Repair API removed ok" . PHP_EOL;
}

function assert_rejected(string $json): void
{
    assert(JsonFast::validate($json) === false);

    $analysis = JsonFast::analyse($json);
    assert($analysis['valid'] === false);

    $inspect = JsonFast::inspect($json);
    assert($inspect['valid'] === false);

    assert_throws(static fn (): mixed => JsonFast::beautify($json));
    assert_throws(static fn (): mixed => JsonFast::minify($json));
    assert_throws(static fn (): mixed => JsonFast::unwrap($json));
    assert_throws(static fn (): mixed => JsonFast::get($json, 'is_admin'));
}

function assert_throws(callable $callback): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }

    assert(false);
}

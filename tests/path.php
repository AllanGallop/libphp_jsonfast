<?php

declare(strict_types=1);

test_path();
test_extract();
test_merge();
test_output_modes();

function test_path(): void
{
    $json = <<<JSON
    {
        "user": {
            "name": "Allan",
            "active": true,
            "address": {
                "city": "Milton Keynes"
            }
        },
        "users": [
            {"name": "Allan", "email": "allan@example.com"},
            {"name": "Bob", "email": "bob@example.com"}
        ]
    }
    JSON;

    assert(JsonFast::get($json, 'user.name') === 'Allan');
    assert(JsonFast::get($json, 'user.address.city') === 'Milton Keynes');
    assert(JsonFast::get($json, 'user.active') === true);

    assert(JsonFast::has($json, 'user.name') === true);
    assert(JsonFast::has($json, 'user.missing') === false);

    assert(JsonFast::get($json, 'users[0].email') === 'allan@example.com');
    assert(JsonFast::get($json, 'users[1].email') === 'bob@example.com');

    $emails = JsonFast::search($json, 'users[*].email');
    assert($emails === ['allan@example.com', 'bob@example.com']);

    echo "Path tests ok" . PHP_EOL;
}

function test_extract(): void
{
    $json = <<<JSON
    {
        "user": {
            "name": "Allan",
            "email": "allan@example.com"
        },
        "status": "active"
    }
    JSON;

    $result = JsonFast::extract($json, [
        'user.name',
        'user.email',
        'status',
        'missing.field',
    ]);

    assert($result['user.name'] === 'Allan');
    assert($result['user.email'] === 'allan@example.com');
    assert($result['status'] === 'active');
    assert($result['missing.field'] === null);

    echo "Extract ok" . PHP_EOL;
}

function test_merge(): void
{
    $base = <<<JSON
    {
        "name": "Allan",
        "active": true,
        "settings": {
            "theme": "dark"
        }
    }
    JSON;

    $overlay = <<<JSON
    {
        "active": false,
        "settings": {
            "language": "en"
        }
    }
    JSON;

    $merged = JsonFast::merge($base, $overlay, JsonFast::OUTPUT_STRING);

    assert(JsonFast::validate($merged));
    assert(JsonFast::minify($merged, JsonFast::OUTPUT_STRING) === '{"name":"Allan","active":false,"settings":{"theme":"dark","language":"en"}}');

    echo "Merge ok" . PHP_EOL;
}

function test_output_modes(): void
{
    $json = <<<JSON
    {
        "user": {
            "name": "Allan",
            "profile": {"city": "Milton Keynes"}
        }
    }
    JSON;

    $array = JsonFast::get($json, 'user.profile');
    assert(is_array($array));
    assert($array['city'] === 'Milton Keynes');

    $object = JsonFast::get($json, 'user.profile', JsonFast::OUTPUT_OBJECT);
    assert(is_object($object));
    assert($object->city === 'Milton Keynes');

    $string = JsonFast::get($json, 'user.profile', JsonFast::OUTPUT_STRING);
    assert(is_string($string));
    assert(str_contains($string, 'Milton Keynes'));

    echo "Path output modes ok" . PHP_EOL;
}

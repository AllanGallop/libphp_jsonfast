<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_jsonfast();

$document = <<<'JSON'
{
    "user": {
        "name": "Allan",
        "profile": {
            "city": "Milton Keynes",
            "country": "UK"
        }
    },
    "users": [
        {"name": "Allan", "email": "allan@example.com"},
        {"name": "Bob", "email": "bob@example.com"}
    ]
}
JSON;

section('Dot-path get and has');

echo 'user.name: ';
show(JsonFast::get($document, 'user.name'));

echo 'user.profile.city: ';
show(JsonFast::get($document, 'user.profile.city'));

echo 'user.missing exists: ';
show(JsonFast::has($document, 'user.missing'));

section('Array index paths');

echo 'users[0].email: ';
show(JsonFast::get($document, 'users[0].email'));

section('Wildcard search');

echo "All emails (users[*].email):\n";
show(JsonFast::search($document, 'users[*].email'));

section('Extract multiple paths at once');

$slice = JsonFast::extract($document, [
    'user.name',
    'user.profile.city',
    'users[1].email',
    'missing.path',
]);

show($slice);

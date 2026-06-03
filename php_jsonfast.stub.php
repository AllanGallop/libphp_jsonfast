<?php

// Stubs for php_jsonfast

namespace {
    class JsonFast {
        const OUTPUT_ARRAY = null;

        const OUTPUT_STRING = null;

        const OUTPUT_OBJECT = null;

        const REPAIR_BOM = null;

        const REPAIR_JSONP = null;

        const REPAIR_COMMENTS = null;

        const REPAIR_TRAILING_COMMAS = null;

        const REPAIR_DOUBLE_ENCODED = null;

        const REPAIR_UNQUOTED_STRINGS = null;

        const REPAIR_SINGLE_QUOTES = null;

        const REPAIR_UNQUOTED_KEYS = null;

        const REPAIR_ALL = null;

        public static function testJsonValue(?int $output): mixed {}

        public static function repair(string $json, ?int $flags, ?int $output): mixed {}

        public static function analyse(string $json, ?int $output): mixed {}

        public static function validate(string $json): bool {}

        public static function beautify(string $json, ?int $indent, ?int $output): mixed {}

        public static function minify(string $json, ?int $output): mixed {}

        public static function inspect(string $json, ?int $output): mixed {}

        public static function unwrap(string $json, ?int $max_depth, ?int $output): mixed {}

        public static function get(string $json, string $path, ?int $output): mixed {}

        public static function has(string $json, string $path): bool {}

        public static function search(string $json, string $path, ?int $output): mixed {}

        public static function extract(string $json, array $paths, ?int $output): mixed {}

        public static function merge(string $base, string $overlay, ?int $output): mixed {}

        public static function getSchema(string $json, ?int $output): mixed {}

        public static function validateSchema(string $json, string $schema_json, ?int $output): mixed {}

        public static function applySchema(string $json, string $schema_json, ?int $output): mixed {}

        public static function diff(string $before, string $after, ?int $output): mixed {}

        public static function schemaDiff(string $before_schema, string $after_schema, ?int $output): mixed {}
    }
}

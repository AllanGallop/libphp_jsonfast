use ext_php_rs::prelude::*;
use ext_php_rs::types::Zval;

mod utils;
mod analyse;
mod repair;
mod format;
mod inspector;
mod unwrapping;
mod path;
mod transform;
mod schema;
mod diff;

#[php_class]
pub struct JsonFast;

#[php_impl]
impl JsonFast {

    pub const OUTPUT_ARRAY: u32 = utils::OUTPUT_ARRAY;
    pub const OUTPUT_STRING: u32 = utils::OUTPUT_STRING;
    pub const OUTPUT_OBJECT: u32 = utils::OUTPUT_OBJECT;
    pub const REPAIR_BOM: u32 = 1 << 0;
    pub const REPAIR_JSONP: u32 = 1 << 1;
    pub const REPAIR_COMMENTS: u32 = 1 << 2;
    pub const REPAIR_TRAILING_COMMAS: u32 = 1 << 3;
    pub const REPAIR_DOUBLE_ENCODED: u32 = 1 << 4;
    pub const REPAIR_UNQUOTED_STRINGS: u32 = 1 << 5;
    pub const REPAIR_SINGLE_QUOTES: u32 = 1 << 6;
    pub const REPAIR_UNQUOTED_KEYS: u32 = 1 << 7;
    pub const REPAIR_ALL: u32 = 0xFF;

    pub fn test_json_value(output: Option<u32>) -> PhpResult<Zval> {
        let value: serde_json::Value = serde_json::json!({
            "name": "Allan",
            "active": true,
            "roles": ["admin", "user"]
        });

        utils::encode_output(&value, output.unwrap_or(Self::OUTPUT_ARRAY))
    }

    pub fn repair(json: String, flags: Option<u32>, output: Option<u32>) -> PhpResult<Zval> {
        repair::repair(&json, flags.unwrap_or(Self::REPAIR_ALL), output.unwrap_or(Self::OUTPUT_ARRAY))
    }

    pub fn analyse(json: String, output: Option<u32>) -> PhpResult<Zval> {
        analyse::analyse(&json, output.unwrap_or(Self::OUTPUT_ARRAY))
    }

    pub fn validate(json: String) -> bool {
        serde_json::from_str::<serde_json::Value>(&json).is_ok()
    }

    pub fn beautify(json: String, indent: Option<u32>, output: Option<u32>) -> PhpResult<Zval> {
        format::beautify(&json, indent.unwrap_or(4), output.unwrap_or(Self::OUTPUT_ARRAY))
    }

    pub fn minify(json: String, output: Option<u32>) -> PhpResult<Zval> {
        format::minify(&json, output.unwrap_or(Self::OUTPUT_ARRAY))
    }

    pub fn inspect(json: String, output: Option<u32>) -> PhpResult<Zval> {
        inspector::inspect(&json, output.unwrap_or(Self::OUTPUT_ARRAY))
    }

    pub fn unwrap(json: String, max_depth: Option<u32>, output: Option<u32>) -> PhpResult<Zval> {
        unwrapping::unwrap_json(&json, max_depth.unwrap_or(3), output.unwrap_or(Self::OUTPUT_ARRAY))
    }

    pub fn get(json: String, path: String, output: Option<u32>) -> PhpResult<Option<Zval>> {
        match path::get_value(&json, &path) {
            Some(value) => Ok(Some(utils::encode_output(&value, output.unwrap_or(Self::OUTPUT_ARRAY))?)),
            None => Ok(None),
        }
    }

    pub fn has(json: String, path: String) -> bool {
        path::has(&json, &path)
    }

    pub fn search(json: String, path: String, output: Option<u32>) -> PhpResult<Zval> {
        let values = path::search_values(&json, &path);
        let result = serde_json::Value::Array(values);
        utils::encode_output(&result, output.unwrap_or(Self::OUTPUT_ARRAY))
    }

    pub fn extract(json: String, paths: Vec<String>, output: Option<u32>) -> PhpResult<Zval> {
        let result = path::extract_values(&json, paths);
        utils::encode_output(&result, output.unwrap_or(Self::OUTPUT_ARRAY))
    }

    pub fn merge(base: String, overlay: String, output: Option<u32>) -> PhpResult<Zval> {
        transform::merge(&base, &overlay, output.unwrap_or(Self::OUTPUT_ARRAY))
    }

    pub fn get_schema(json: String, output: Option<u32>) -> PhpResult<Zval> {
        schema::get_schema(&json, output.unwrap_or(Self::OUTPUT_ARRAY))
    }

    pub fn validate_schema(json: String, schema_json: String, output: Option<u32>) -> PhpResult<Zval> {
        schema::validate_schema(&json, &schema_json, output.unwrap_or(Self::OUTPUT_ARRAY))
    }

    pub fn apply_schema(json: String, schema_json: String, output: Option<u32>) -> PhpResult<Zval> {
        schema::apply_schema(&json, &schema_json, output.unwrap_or(Self::OUTPUT_ARRAY))
    }

    pub fn diff(before: String, after: String, output: Option<u32>) -> PhpResult<Zval> {
        diff::diff(&before, &after, output.unwrap_or(Self::OUTPUT_ARRAY))
    }

    pub fn schema_diff(before_schema: String, after_schema: String, output: Option<u32>) -> PhpResult<Zval> {
        diff::schema_diff(&before_schema, &after_schema, output.unwrap_or(Self::OUTPUT_ARRAY))
    }
}

#[php_module]
pub fn module(module: ModuleBuilder) -> ModuleBuilder {
    module
}

use ext_php_rs::prelude::*;
use ext_php_rs::types::Zval;
use crate::utils::encode_output;

pub fn inspect(json: &str, output: u32) -> PhpResult<Zval> {
    let result = match serde_json::from_str::<serde_json::Value>(json) {
        Ok(_) => serde_json::json!({
            "valid": true,
            "error": "",
            "line": 0,
            "column": 0,
        }),
        Err(e) => serde_json::json!({
            "valid": false,
            "error": e.to_string(),
            "line": e.line(),
            "column": e.column(),
        }),
    };

    encode_output(&result, output)
}
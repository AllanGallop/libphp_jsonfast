use ext_php_rs::convert::{IntoZval, IntoZvalDyn};
use ext_php_rs::prelude::*;
use ext_php_rs::types::{ZendCallable, Zval};
use serde_json::Value;

pub const OUTPUT_ARRAY: u32 = 0;
pub const OUTPUT_STRING: u32 = 1;
pub const OUTPUT_OBJECT: u32 = 2;

pub fn json_error(message: String) -> PhpException {
    PhpException::default(message)
}

pub fn encode_output(value: &Value, output: u32) -> PhpResult<Zval> {
    match output {
        OUTPUT_ARRAY => decode_json_to_zval(value, true),
        OUTPUT_OBJECT => decode_json_to_zval(value, false),
        OUTPUT_STRING => {
            let encoded = serde_json::to_string(value).map_err(|e| {
                json_error(format!("Failed to encode JSON: {}", e))
            })?;

            Ok(encoded.into_zval(false)?)
        }
        _ => Err(json_error(format!("Invalid output mode: {}", output))),
    }
}

/// Build PHP arrays/objects via `json_decode` so Zend interned strings are
/// initialized by the engine (avoids ext-php-rs interned-string panics).
fn decode_json_to_zval(value: &Value, associative: bool) -> PhpResult<Zval> {
    let json = serde_json::to_string(value).map_err(|e| {
        json_error(format!("Failed to encode JSON: {}", e))
    })?;

    let decoder = ZendCallable::try_from_name("json_decode")
        .map_err(|_| json_error("json_decode is not available".into()))?;

    decoder
        .try_call(vec![&json as &dyn IntoZvalDyn, &associative as &dyn IntoZvalDyn])
        .map_err(|e| json_error(format!("json_decode failed: {:?}", e)))
}

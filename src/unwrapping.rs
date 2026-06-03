use ext_php_rs::convert::IntoZval;
use ext_php_rs::prelude::*;
use ext_php_rs::types::Zval;
use serde_json::Value;
use crate::utils::{encode_output, json_error};

pub fn unwrap_json(input: &str, max_depth: u32, output: u32) -> PhpResult<Zval> {
    let mut current = input.to_string();

    for _ in 0..max_depth {
        let parsed: Value = serde_json::from_str(&current)
            .map_err(|e| json_error(format!("Invalid JSON: {}", e)))?;

        match parsed {
            Value::String(inner) => {
                current = inner;
            }
            other => {
                return encode_output(&other, output);
            }
        }
    }

    match serde_json::from_str::<Value>(&current) {
        Ok(value) => encode_output(&value, output),
        Err(_) => Ok(current.into_zval(false)?),
    }
}
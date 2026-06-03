use ext_php_rs::convert::IntoZval;
use ext_php_rs::prelude::*;
use ext_php_rs::types::{ZendHashTable, ZendObject, Zval};
use serde_json::Value;

pub const OUTPUT_ARRAY: u32 = 0;
pub const OUTPUT_STRING: u32 = 1;
pub const OUTPUT_OBJECT: u32 = 2;

#[derive(Copy, Clone)]
enum ValueMode {
    Array,
    Object,
}

pub fn json_error(message: String) -> PhpException {
    PhpException::default(message)
}

pub fn encode_output(value: &Value, output: u32) -> PhpResult<Zval> {
    match output {
        OUTPUT_ARRAY => value_to_zval(value, ValueMode::Array),
        OUTPUT_OBJECT => value_to_zval(value, ValueMode::Object),
        OUTPUT_STRING => {
            let encoded = serde_json::to_string(value).map_err(|e| {
                json_error(format!("Failed to encode JSON: {}", e))
            })?;

            Ok(encoded.into_zval(false)?)
        }
        _ => Err(json_error(format!("Invalid output mode: {}", output))),
    }
}

fn value_to_zval(value: &Value, mode: ValueMode) -> PhpResult<Zval> {
    match value {
        Value::Null => Ok(().into_zval(false)?),
        Value::Bool(v) => Ok((*v).into_zval(false)?),
        Value::Number(n) => {
            if let Some(i) = n.as_i64() {
                Ok(i.into_zval(false)?)
            } else if let Some(u) = n.as_u64() {
                Ok((u as i64).into_zval(false)?)
            } else if let Some(f) = n.as_f64() {
                Ok(f.into_zval(false)?)
            } else {
                Ok(().into_zval(false)?)
            }
        }
        Value::String(s) => Ok(s.as_str().into_zval(false)?),
        Value::Array(items) => {
            let mut arr = ZendHashTable::new();

            for item in items {
                arr.push(value_to_zval(item, mode)?)?;
            }

            Ok(arr.into_zval(false)?)
        }
        Value::Object(map) => match mode {
            ValueMode::Array => {
                let mut arr = ZendHashTable::new();

                for (key, item) in map {
                    arr.insert(key.as_str(), value_to_zval(item, mode)?)?;
                }

                Ok(arr.into_zval(false)?)
            }
            ValueMode::Object => {
                let mut obj = ZendObject::new_stdclass();

                for (key, item) in map {
                    obj.set_property(key.as_str(), value_to_zval(item, mode)?)?;
                }

                Ok(obj.into_zval(false)?)
            }
        },
    }
}

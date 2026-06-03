use ext_php_rs::prelude::*;
use ext_php_rs::types::Zval;
use serde_json::Value;
use crate::utils::{encode_output, json_error};

pub fn merge(base: &str, overlay: &str, output: u32) -> PhpResult<Zval> {
    let mut base_value: Value = serde_json::from_str(base)
        .map_err(|e| json_error(format!("Invalid base JSON: {}", e)))?;

    let overlay_value: Value = serde_json::from_str(overlay)
        .map_err(|e| json_error(format!("Invalid overlay JSON: {}", e)))?;

    merge_values(&mut base_value, overlay_value);

    encode_output(&base_value, output)
}

fn merge_values(base: &mut Value, overlay: Value) {
    match (base, overlay) {
        (Value::Object(base_map), Value::Object(overlay_map)) => {
            for (key, overlay_value) in overlay_map {
                match base_map.get_mut(&key) {
                    Some(base_value) => merge_values(base_value, overlay_value),
                    None => {
                        base_map.insert(key, overlay_value);
                    }
                }
            }
        }
        (base_slot, overlay_value) => {
            *base_slot = overlay_value;
        }
    }
}
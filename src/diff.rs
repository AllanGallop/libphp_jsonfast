use ext_php_rs::prelude::*;
use ext_php_rs::types::Zval;
use serde_json::{json, Map, Value};
use crate::utils::{encode_output, json_error};

pub fn diff(before: &str, after: &str, output: u32) -> PhpResult<Zval> {
    let before_value: Value = serde_json::from_str(before)
        .map_err(|e| json_error(format!("Invalid before JSON: {}", e)))?;

    let after_value: Value = serde_json::from_str(after)
        .map_err(|e| json_error(format!("Invalid after JSON: {}", e)))?;

    let result = diff_values(&before_value, &after_value);

    encode_output(&result, output)
}

pub fn schema_diff(before_schema: &str, after_schema: &str, output: u32) -> PhpResult<Zval> {
    let before_value: Value = serde_json::from_str(before_schema)
        .map_err(|e| json_error(format!("Invalid before schema JSON: {}", e)))?;

    let after_value: Value = serde_json::from_str(after_schema)
        .map_err(|e| json_error(format!("Invalid after schema JSON: {}", e)))?;

    let result = diff_schemas(&before_value, &after_value);

    encode_output(&result, output)
}

fn diff_values(before: &Value, after: &Value) -> Value {
    let mut added = Map::new();
    let mut removed = Map::new();
    let mut changed = Map::new();

    diff_value_at_path(before, after, "$", &mut added, &mut removed, &mut changed);

    json!({
        "added": added,
        "removed": removed,
        "changed": changed
    })
}

fn diff_value_at_path(
    before: &Value,
    after: &Value,
    path: &str,
    added: &mut Map<String, Value>,
    removed: &mut Map<String, Value>,
    changed: &mut Map<String, Value>,
) {
    match (before, after) {
        (Value::Object(before_map), Value::Object(after_map)) => {
            for (key, after_child) in after_map {
                let child_path = path_join(path, key);

                if !before_map.contains_key(key) {
                    added.insert(child_path, after_child.clone());
                    continue;
                }

                diff_value_at_path(
                    &before_map[key],
                    after_child,
                    &child_path,
                    added,
                    removed,
                    changed,
                );
            }

            for (key, before_child) in before_map {
                let child_path = path_join(path, key);

                if !after_map.contains_key(key) {
                    removed.insert(child_path, before_child.clone());
                }
            }
        }

        (Value::Array(before_items), Value::Array(after_items)) => {
            let max_len = before_items.len().max(after_items.len());

            for i in 0..max_len {
                let child_path = format!("{}[{}]", path, i);

                match (before_items.get(i), after_items.get(i)) {
                    (Some(b), Some(a)) => {
                        diff_value_at_path(b, a, &child_path, added, removed, changed);
                    }
                    (None, Some(a)) => {
                        added.insert(child_path, a.clone());
                    }
                    (Some(b), None) => {
                        removed.insert(child_path, b.clone());
                    }
                    _ => {}
                }
            }
        }

        _ => {
            if before != after {
                changed.insert(
                    path.to_string(),
                    json!({
                        "from": before,
                        "to": after
                    }),
                );
            }
        }
    }
}

fn diff_schemas(before: &Value, after: &Value) -> Value {
    let mut added = Vec::new();
    let mut removed = Vec::new();
    let mut changed = Map::new();

    diff_schema_at_path(before, after, "$", &mut added, &mut removed, &mut changed);

    json!({
        "added": added,
        "removed": removed,
        "changed": changed
    })
}

fn diff_schema_at_path(
    before: &Value,
    after: &Value,
    path: &str,
    added: &mut Vec<String>,
    removed: &mut Vec<String>,
    changed: &mut Map<String, Value>,
) {
    let before_type = before.get("type").and_then(Value::as_str);
    let after_type = after.get("type").and_then(Value::as_str);

    if before_type != after_type {
        changed.insert(
            path.to_string(),
            json!({
                "from": before_type,
                "to": after_type
            }),
        );
    }

    let before_props = before.get("properties").and_then(Value::as_object);
    let after_props = after.get("properties").and_then(Value::as_object);

    if let (Some(before_map), Some(after_map)) = (before_props, after_props) {
        for (key, after_child) in after_map {
            let child_path = path_join(path, key);

            if let Some(before_child) = before_map.get(key) {
                diff_schema_at_path(before_child, after_child, &child_path, added, removed, changed);
            } else {
                added.push(child_path);
            }
        }

        for key in before_map.keys() {
            if !after_map.contains_key(key) {
                removed.push(path_join(path, key));
            }
        }
    }

    let before_items = before.get("items");
    let after_items = after.get("items");

    if let (Some(before_item), Some(after_item)) = (before_items, after_items) {
        diff_schema_at_path(
            before_item,
            after_item,
            &format!("{}[]", path),
            added,
            removed,
            changed,
        );
    }
}

fn path_join(base: &str, key: &str) -> String {
    if base == "$" {
        format!("$.{}", key)
    } else {
        format!("{}.{}", base, key)
    }
}
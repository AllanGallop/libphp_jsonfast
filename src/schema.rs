use ext_php_rs::prelude::*;
use ext_php_rs::types::Zval;
use serde_json::{json, Map, Value};
use crate::utils::{encode_output, json_error};

pub fn get_schema(input: &str, output: u32) -> PhpResult<Zval> {
    let value: Value = serde_json::from_str(input)
        .map_err(|e| json_error(format!("Invalid JSON: {}", e)))?;

    let schema = infer_schema(&value);

    encode_output(&schema, output)
}

pub fn validate_schema(input: &str, schema_input: &str, output: u32) -> PhpResult<Zval> {
    let value: Value = serde_json::from_str(input)
        .map_err(|e| json_error(format!("Invalid JSON: {}", e)))?;

    let schema: Value = serde_json::from_str(schema_input)
        .map_err(|e| json_error(format!("Invalid schema JSON: {}", e)))?;

    let mut errors = Vec::new();

    validate_value(&value, &schema, "$", &mut errors);

    let result = json!({
        "valid": errors.is_empty(),
        "errors": errors,
    });

    encode_output(&result, output)
}

pub fn apply_schema(input: &str, schema_input: &str, output: u32) -> PhpResult<Zval> {
    let value: Value = serde_json::from_str(input)
        .map_err(|e| json_error(format!("Invalid JSON: {}", e)))?;

    let schema: Value = serde_json::from_str(schema_input)
        .map_err(|e| json_error(format!("Invalid schema JSON: {}", e)))?;

    let applied = apply_value(&value, &schema);

    encode_output(&applied, output)
}

fn infer_schema(value: &Value) -> Value {
    match value {
        Value::Null => json!({ "type": "null" }),
        Value::Bool(_) => json!({ "type": "boolean" }),
        Value::Number(n) => {
            if n.is_i64() || n.is_u64() {
                json!({ "type": "integer" })
            } else {
                json!({ "type": "number" })
            }
        }
        Value::String(_) => json!({ "type": "string" }),
        Value::Array(items) => {
            if items.is_empty() {
                json!({
                    "type": "array",
                    "items": {}
                })
            } else {
                json!({
                    "type": "array",
                    "items": infer_schema(&items[0])
                })
            }
        }
        Value::Object(map) => {
            let mut properties = Map::new();
            let mut required = Vec::new();

            for (key, child) in map {
                properties.insert(key.clone(), infer_schema(child));
                required.push(Value::String(key.clone()));
            }

            json!({
                "type": "object",
                "properties": properties,
                "required": required
            })
        }
    }
}

fn validate_value(value: &Value, schema: &Value, path: &str, errors: &mut Vec<String>) {
    let expected_type = schema.get("type").and_then(Value::as_str);

    if let Some(expected) = expected_type {
        if !matches_type(value, expected) {
            errors.push(format!(
                "{} expected {}, got {}",
                path,
                expected,
                value_type(value)
            ));
            return;
        }
    }

    if expected_type == Some("object") {
        validate_object(value, schema, path, errors);
    }

    if expected_type == Some("array") {
        validate_array(value, schema, path, errors);
    }
}

fn validate_object(value: &Value, schema: &Value, path: &str, errors: &mut Vec<String>) {
    let Some(object) = value.as_object() else {
        return;
    };

    if let Some(required) = schema.get("required").and_then(Value::as_array) {
        for item in required {
            if let Some(key) = item.as_str() {
                if !object.contains_key(key) {
                    errors.push(format!("{}.{} is required", path, key));
                }
            }
        }
    }

    if let Some(properties) = schema.get("properties").and_then(Value::as_object) {
        for (key, child_schema) in properties {
            if let Some(child_value) = object.get(key) {
                validate_value(
                    child_value,
                    child_schema,
                    &format!("{}.{}", path, key),
                    errors,
                );
            }
        }
    }
}

fn validate_array(value: &Value, schema: &Value, path: &str, errors: &mut Vec<String>) {
    let Some(items) = value.as_array() else {
        return;
    };

    let Some(item_schema) = schema.get("items") else {
        return;
    };

    for (index, item) in items.iter().enumerate() {
        validate_value(
            item,
            item_schema,
            &format!("{}[{}]", path, index),
            errors,
        );
    }
}

fn apply_value(value: &Value, schema: &Value) -> Value {
    match schema.get("type").and_then(Value::as_str) {
        Some("object") => apply_object(value, schema),
        Some("array") => apply_array(value, schema),
        _ => value.clone(),
    }
}

fn apply_object(value: &Value, schema: &Value) -> Value {
    let mut output = Map::new();

    let Some(input_object) = value.as_object() else {
        return Value::Object(output);
    };

    let Some(properties) = schema.get("properties").and_then(Value::as_object) else {
        return value.clone();
    };

    for (key, child_schema) in properties {
        if let Some(child_value) = input_object.get(key) {
            output.insert(key.clone(), apply_value(child_value, child_schema));
        } else if let Some(default_value) = child_schema.get("default") {
            output.insert(key.clone(), default_value.clone());
        }
    }

    Value::Object(output)
}

fn apply_array(value: &Value, schema: &Value) -> Value {
    let Some(items) = value.as_array() else {
        return Value::Array(vec![]);
    };

    let Some(item_schema) = schema.get("items") else {
        return value.clone();
    };

    Value::Array(
        items
            .iter()
            .map(|item| apply_value(item, item_schema))
            .collect(),
    )
}

fn matches_type(value: &Value, expected: &str) -> bool {
    match expected {
        "null" => value.is_null(),
        "boolean" => value.is_boolean(),
        "integer" => value.as_i64().is_some() || value.as_u64().is_some(),
        "number" => value.is_number(),
        "string" => value.is_string(),
        "array" => value.is_array(),
        "object" => value.is_object(),
        _ => true,
    }
}

fn value_type(value: &Value) -> &'static str {
    match value {
        Value::Null => "null",
        Value::Bool(_) => "boolean",
        Value::Number(n) => {
            if n.is_i64() || n.is_u64() {
                "integer"
            } else {
                "number"
            }
        }
        Value::String(_) => "string",
        Value::Array(_) => "array",
        Value::Object(_) => "object",
    }
}
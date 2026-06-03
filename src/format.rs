use ext_php_rs::convert::IntoZval;
use ext_php_rs::prelude::*;
use ext_php_rs::types::Zval;
use serde::Serialize;
use serde_json::Value;
use crate::utils::{encode_output, json_error, OUTPUT_STRING};

pub fn beautify(json: &str, indent: u32, output: u32) -> PhpResult<Zval> {
    let value: Value = serde_json::from_str(json)
        .map_err(|e| json_error(format!("Invalid JSON: {}", e)))?;

    let mut buf = Vec::new();
    let indent_bytes = " ".repeat(indent as usize);

    let formatter = serde_json::ser::PrettyFormatter::with_indent(indent_bytes.as_bytes());
    let mut serializer = serde_json::Serializer::with_formatter(&mut buf, formatter);

    value
        .serialize(&mut serializer)
        .map_err(|e| json_error(format!("Failed to beautify JSON: {}", e)))?;

    let pretty_string = String::from_utf8(buf)
        .map_err(|e| json_error(format!("Invalid UTF-8 output: {}", e)))?;

    if output == OUTPUT_STRING {
        return Ok(pretty_string.into_zval(false)?);
    }

    let parsed: Value = serde_json::from_str(&pretty_string)
        .map_err(|e| json_error(format!("Failed to parse beautified JSON: {}", e)))?;

    encode_output(&parsed, output)
}

pub fn minify(json: &str, output: u32) -> PhpResult<Zval> {
    let value: Value = serde_json::from_str(json)
        .map_err(|e| json_error(format!("Invalid JSON: {}", e)))?;

    let minified = serde_json::to_string(&value)
        .map_err(|e| json_error(format!("Failed to minify JSON: {}", e)))?;

    if output == OUTPUT_STRING {
        return Ok(minified.into_zval(false)?);
    }

    let parsed: Value = serde_json::from_str(&minified)
        .map_err(|e| json_error(format!("Failed to parse minified JSON: {}", e)))?;

    encode_output(&parsed, output)
}
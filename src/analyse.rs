use ext_php_rs::prelude::*;
use ext_php_rs::types::Zval;
use serde::Serialize;
use crate::utils::{encode_output, json_error};

// Strict RFC 8259 analysis only — no repair or coercion of non-standard tokens.

#[derive(Serialize)]
struct Analysis {
    valid: bool,
    error: Option<AnalysisError>,
}

#[derive(Serialize)]
struct AnalysisError {
    message: String,
    line: usize,
    column: usize,
}

pub fn analyse(input: &str, output: u32) -> PhpResult<Zval> {
    let error = match serde_json::from_str::<serde_json::Value>(input) {
        Ok(_) => None,
        Err(e) => Some(AnalysisError {
            message: e.to_string(),
            line: e.line(),
            column: e.column(),
        }),
    };

    let analysis = Analysis {
        valid: error.is_none(),
        error,
    };

    let analysis_value = serde_json::to_value(&analysis)
        .map_err(|e| json_error(format!("Failed to encode analysis: {}", e)))?;

    encode_output(&analysis_value, output)
}

use ext_php_rs::prelude::*;
use ext_php_rs::types::Zval;
use serde::Serialize;
use crate::utils::{encode_output, json_error};

const REPAIR_BOM: u32 = 1 << 0;
const REPAIR_JSONP: u32 = 1 << 1;
const REPAIR_COMMENTS: u32 = 1 << 2;
const REPAIR_TRAILING_COMMAS: u32 = 1 << 3;
const REPAIR_DOUBLE_ENCODED: u32 = 1 << 4;
const REPAIR_UNQUOTED_STRINGS: u32 = 1 << 5;
const REPAIR_SINGLE_QUOTES: u32 = 1 << 6;
const REPAIR_UNQUOTED_KEYS: u32 = 1 << 7;

#[derive(Serialize)]
struct Analysis {
    valid: bool,
    repairable: bool,
    error: Option<AnalysisError>,
    repairs: Vec<RepairSuggestion>,
}

#[derive(Serialize)]
struct AnalysisError {
    message: String,
    line: usize,
    column: usize,
}

#[derive(Serialize)]
struct RepairSuggestion {
    flag: &'static str,
    bit: u32,
    description: &'static str,
}

pub fn analyse(input: &str, output: u32) -> PhpResult<Zval> {
    let mut repairs = Vec::new();

    if input.starts_with('\u{feff}') {
        repairs.push(repair_suggestion(
            "REPAIR_BOM",
            REPAIR_BOM,
            "Removes UTF-8 byte order mark from the start of the input",
        ));
    }

    if looks_like_jsonp(input) {
        repairs.push(repair_suggestion(
            "REPAIR_JSONP",
            REPAIR_JSONP,
            "Extracts JSON from a JSONP callback wrapper",
        ));
    }

    if has_comments(input) {
        repairs.push(repair_suggestion(
            "REPAIR_COMMENTS",
            REPAIR_COMMENTS,
            "Removes // and /* */ comments outside strings",
        ));
    }

    if has_trailing_commas(input) {
        repairs.push(repair_suggestion(
            "REPAIR_TRAILING_COMMAS",
            REPAIR_TRAILING_COMMAS,
            "Removes commas before } or ]",
        ));
    }

    if looks_double_encoded(input) {
        repairs.push(repair_suggestion(
            "REPAIR_DOUBLE_ENCODED",
            REPAIR_DOUBLE_ENCODED,
            "Decodes JSON that has been encoded as a JSON string",
        ));
    }

    if has_unquoted_string_values(input) {
        repairs.push(repair_suggestion(
            "REPAIR_UNQUOTED_STRINGS",
            REPAIR_UNQUOTED_STRINGS,
            "Quotes unquoted string values after :",
        ));
    }

    if has_single_quotes(input) {
        repairs.push(repair_suggestion(
            "REPAIR_SINGLE_QUOTES",
            REPAIR_SINGLE_QUOTES,
            "Converts single quoted strings to JSON strings",
        ));
    }

    if has_unquoted_keys(input) {
        repairs.push(repair_suggestion(
            "REPAIR_UNQUOTED_KEYS",
            REPAIR_UNQUOTED_KEYS,
            "Quotes unquoted object keys",
        ));
    }

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
        repairable: !repairs.is_empty(),
        error,
        repairs,
    };

    let analysis_value = serde_json::to_value(&analysis)
        .map_err(|e| json_error(format!("Failed to encode analysis: {}", e)))?;

    encode_output(&analysis_value, output)
}

fn repair_suggestion(
    flag: &'static str,
    bit: u32,
    description: &'static str,
) -> RepairSuggestion {
    RepairSuggestion {
        flag,
        bit,
        description,
    }
}

fn looks_like_jsonp(input: &str) -> bool {
    let s = input.trim();
    s.contains('(') && (s.ends_with(')') || s.ends_with(");"))
}

fn looks_double_encoded(input: &str) -> bool {
    match serde_json::from_str::<serde_json::Value>(input) {
        Ok(serde_json::Value::String(inner)) => {
            serde_json::from_str::<serde_json::Value>(&inner).is_ok()
        }
        _ => false,
    }
}

fn has_comments(input: &str) -> bool {
    let chars: Vec<char> = input.chars().collect();
    let mut in_string = false;
    let mut escape = false;

    for i in 0..chars.len() {
        let c = chars[i];

        if in_string {
            if escape {
                escape = false;
            } else if c == '\\' {
                escape = true;
            } else if c == '"' {
                in_string = false;
            }
            continue;
        }

        if c == '"' {
            in_string = true;
            continue;
        }

        if c == '/' && i + 1 < chars.len() {
            if chars[i + 1] == '/' || chars[i + 1] == '*' {
                return true;
            }
        }
    }

    false
}

fn has_trailing_commas(input: &str) -> bool {
    let chars: Vec<char> = input.chars().collect();
    let mut in_string = false;
    let mut escape = false;

    for i in 0..chars.len() {
        let c = chars[i];

        if in_string {
            if escape {
                escape = false;
            } else if c == '\\' {
                escape = true;
            } else if c == '"' {
                in_string = false;
            }
            continue;
        }

        if c == '"' {
            in_string = true;
            continue;
        }

        if c == ',' {
            let mut j = i + 1;

            while j < chars.len() && chars[j].is_whitespace() {
                j += 1;
            }

            if j < chars.len() && (chars[j] == '}' || chars[j] == ']') {
                return true;
            }
        }
    }

    false
}

fn has_unquoted_string_values(input: &str) -> bool {
    let chars: Vec<char> = input.chars().collect();

    let mut in_string = false;
    let mut escape = false;
    let mut expecting_value = false;
    let mut i = 0;

    while i < chars.len() {
        let c = chars[i];

        if in_string {
            if escape {
                escape = false;
            } else if c == '\\' {
                escape = true;
            } else if c == '"' {
                in_string = false;
            }

            i += 1;
            continue;
        }

        if c == '"' {
            in_string = true;
            i += 1;
            continue;
        }

        if c == ':' {
            expecting_value = true;
            i += 1;
            continue;
        }

        if expecting_value {
            if c.is_whitespace() {
                i += 1;
                continue;
            }

            if c.is_alphabetic() || c == '_' {
                let start = i;

                while i < chars.len()
                    && !chars[i].is_whitespace()
                    && chars[i] != ','
                    && chars[i] != '}'
                    && chars[i] != ']'
                {
                    i += 1;
                }

                let token: String = chars[start..i].iter().collect();

                return !matches!(token.as_str(), "true" | "false" | "null");
            }

            expecting_value = false;
        }

        i += 1;
    }

    false
}

fn has_single_quotes(input: &str) -> bool {
    let chars: Vec<char> = input.chars().collect();

    let mut in_double = false;
    let mut escape = false;

    for c in chars {
        if in_double {
            if escape {
                escape = false;
            } else if c == '\\' {
                escape = true;
            } else if c == '"' {
                in_double = false;
            }
            continue;
        }

        if c == '"' {
            in_double = true;
            continue;
        }

        if c == '\'' {
            return true;
        }
    }

    false
}

fn has_unquoted_keys(input: &str) -> bool {
    let chars: Vec<char> = input.chars().collect();

    let mut in_string = false;
    let mut escape = false;
    let mut expecting_key = false;
    let mut i = 0;

    while i < chars.len() {
        let c = chars[i];

        if in_string {
            if escape {
                escape = false;
            } else if c == '\\' {
                escape = true;
            } else if c == '"' {
                in_string = false;
            }

            i += 1;
            continue;
        }

        if c == '"' {
            in_string = true;
            i += 1;
            continue;
        }

        if c == '{' || c == ',' {
            expecting_key = true;
            i += 1;
            continue;
        }

        if expecting_key {
            if c.is_whitespace() {
                i += 1;
                continue;
            }

            if c.is_alphabetic() || c == '_' {
                return true;
            }

            expecting_key = false;
        }

        i += 1;
    }

    false
}
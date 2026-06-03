use ext_php_rs::prelude::*;
use ext_php_rs::types::Zval;
use serde_json::Value;
use crate::utils::{encode_output, json_error};

const REPAIR_BOM: u32 = 1 << 0;
const REPAIR_JSONP: u32 = 1 << 1;
const REPAIR_COMMENTS: u32 = 1 << 2;
const REPAIR_TRAILING_COMMAS: u32 = 1 << 3;
const REPAIR_DOUBLE_ENCODED: u32 = 1 << 4;
const REPAIR_UNQUOTED_STRINGS: u32 = 1 << 5;
const REPAIR_SINGLE_QUOTES: u32 = 1 << 6;
const REPAIR_UNQUOTED_KEYS: u32 = 1 << 7;

pub fn repair(input: &str, flags: u32, output: u32) -> PhpResult<Zval> {
    let mut s = input.to_string();

    if flags & REPAIR_BOM != 0 {
        s = strip_bom(&s);
    }

    if flags & REPAIR_JSONP != 0 {
        s = strip_jsonp(&s);
    }

    if flags & REPAIR_COMMENTS != 0 {
        s = strip_comments(&s);
    }

    if flags & REPAIR_TRAILING_COMMAS != 0 {
        s = strip_trailing_commas(&s);
    }

    if flags & REPAIR_DOUBLE_ENCODED != 0 {
        s = unwrap_double_encoded(&s);
    }

    if flags & REPAIR_UNQUOTED_STRINGS != 0 {
        s = quote_unquoted_string_values(&s);
    }   

    if flags & REPAIR_SINGLE_QUOTES != 0 {
        s = convert_single_quotes(&s);
    }

    if flags & REPAIR_UNQUOTED_KEYS != 0 {
        s = quote_unquoted_keys(&s);
    }

    let value: Value = serde_json::from_str(&s)
        .map_err(|e| json_error(format!("Repair failed: {}", e)))?;

    encode_output(&value, output)
}

fn strip_bom(input: &str) -> String {
    input.trim_start_matches('\u{feff}').to_string()
}

fn strip_jsonp(input: &str) -> String {
    let trimmed = input.trim();

    if let Some(open) = trimmed.find('(') {
        if trimmed.ends_with(')') || trimmed.ends_with(");") {
            let close = trimmed.rfind(')').unwrap_or(trimmed.len());
            return trimmed[open + 1..close].trim().to_string();
        }
    }

    input.to_string()
}

fn unwrap_double_encoded(input: &str) -> String {
    match serde_json::from_str::<Value>(input) {
        Ok(Value::String(inner)) => inner,
        _ => input.to_string(),
    }
}

fn strip_trailing_commas(input: &str) -> String {
    let mut out = String::new();
    let chars: Vec<char> = input.chars().collect();

    let mut in_string = false;
    let mut escape = false;
    let mut i = 0;

    while i < chars.len() {
        let c = chars[i];

        if in_string {
            out.push(c);

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
            out.push(c);
            i += 1;
            continue;
        }

        if c == ',' {
            let mut j = i + 1;

            while j < chars.len() && chars[j].is_whitespace() {
                j += 1;
            }

            if j < chars.len() && (chars[j] == '}' || chars[j] == ']') {
                i += 1;
                continue;
            }
        }

        out.push(c);
        i += 1;
    }

    out
}

fn strip_comments(input: &str) -> String {
    let chars: Vec<char> = input.chars().collect();
    let mut out = String::new();

    let mut in_string = false;
    let mut escape = false;
    let mut i = 0;

    while i < chars.len() {
        let c = chars[i];

        if in_string {
            out.push(c);

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
            out.push(c);
            i += 1;
            continue;
        }

        if c == '/' && i + 1 < chars.len() {
            if chars[i + 1] == '/' {
                i += 2;
                while i < chars.len() && chars[i] != '\n' {
                    i += 1;
                }
                continue;
            }

            if chars[i + 1] == '*' {
                i += 2;
                while i + 1 < chars.len() {
                    if chars[i] == '*' && chars[i + 1] == '/' {
                        i += 2;
                        break;
                    }
                    i += 1;
                }
                continue;
            }
        }

        out.push(c);
        i += 1;
    }

    out
}

fn quote_unquoted_string_values(input: &str) -> String {
    let chars: Vec<char> = input.chars().collect();
    let mut out = String::new();

    let mut in_string = false;
    let mut escape = false;
    let mut expecting_value = false;
    let mut i = 0;

    while i < chars.len() {
        let c = chars[i];

        if in_string {
            out.push(c);

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
            out.push(c);
            i += 1;
            continue;
        }

        if c == ':' {
            expecting_value = true;
            out.push(c);
            i += 1;
            continue;
        }

        if expecting_value {
            if c.is_whitespace() {
                out.push(c);
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

                match token.as_str() {
                    "true" | "false" | "null" => {
                        out.push_str(&token);
                    }
                    _ => {
                        out.push('"');
                        out.push_str(&token);
                        out.push('"');
                    }
                }

                expecting_value = false;
                continue;
            }

            expecting_value = false;
        }

        out.push(c);
        i += 1;
    }

    out
}

fn convert_single_quotes(input: &str) -> String {
    let chars: Vec<char> = input.chars().collect();
    let mut out = String::new();

    let mut in_double = false;
    let mut in_single = false;
    let mut escape = false;

    for c in chars {
        if in_double {
            out.push(c);

            if escape {
                escape = false;
            } else if c == '\\' {
                escape = true;
            } else if c == '"' {
                in_double = false;
            }

            continue;
        }

        if in_single {
            if escape {
                out.push(c);
                escape = false;
                continue;
            }

            if c == '\\' {
                out.push(c);
                escape = true;
                continue;
            }

            if c == '\'' {
                out.push('"');
                in_single = false;
                continue;
            }

            out.push(c);
            continue;
        }

        if c == '"' {
            out.push(c);
            in_double = true;
            continue;
        }

        if c == '\'' {
            out.push('"');
            in_single = true;
            continue;
        }

        out.push(c);
    }

    out
}

fn quote_unquoted_keys(input: &str) -> String {
    let chars: Vec<char> = input.chars().collect();
    let mut out = String::new();

    let mut in_string = false;
    let mut escape = false;
    let mut expecting_key = false;
    let mut i = 0;

    while i < chars.len() {
        let c = chars[i];

        if in_string {
            out.push(c);

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
            out.push(c);
            i += 1;
            continue;
        }

        if c == '{' || c == ',' {
            out.push(c);
            expecting_key = true;
            i += 1;
            continue;
        }

        if expecting_key {
            if c.is_whitespace() {
                out.push(c);
                i += 1;
                continue;
            }

            if c.is_alphabetic() || c == '_' {
                let start = i;

                while i < chars.len()
                    && !chars[i].is_whitespace()
                    && chars[i] != ':'
                    && chars[i] != ','
                    && chars[i] != '}'
                    && chars[i] != ']'
                {
                    i += 1;
                }

                let token: String = chars[start..i].iter().collect();

                out.push('"');
                out.push_str(&token);
                out.push('"');

                expecting_key = false;
                continue;
            }

            expecting_key = false;
        }

        out.push(c);
        i += 1;
    }
    out
}
use serde_json::Value;
use std::collections::HashMap;

#[derive(Debug, Clone)]
enum Segment {
    Key(String),
    Index(usize),
    Wildcard,
}

pub fn get(json: &str, path: &str) -> Option<String> {
    let value: Value = serde_json::from_str(json).ok()?;
    let segments = parse_path(path);

    let found = get_value_at_segments(&value, &segments)?;

    Some(value_to_string(found))
}

pub fn get_value(json: &str, path: &str) -> Option<Value> {
    let value: Value = serde_json::from_str(json).ok()?;
    let segments = parse_path(path);

    get_value_at_segments(&value, &segments).cloned()
}

pub fn has(json: &str, path: &str) -> bool {
    get(json, path).is_some()
}

pub fn search(json: &str, path: &str) -> Vec<String> {
    let value: Value = match serde_json::from_str(json) {
        Ok(v) => v,
        Err(_) => return vec![],
    };

    let segments = parse_path(path);
    let mut results = Vec::new();

    collect_search_values(&value, &segments, &mut results);

    results.into_iter().map(value_to_string).collect()
}

pub fn search_values(json: &str, path: &str) -> Vec<Value> {
    let value: Value = match serde_json::from_str(json) {
        Ok(v) => v,
        Err(_) => return vec![],
    };

    let segments = parse_path(path);
    let mut results = Vec::new();

    collect_search_values(&value, &segments, &mut results);

    results.into_iter().cloned().collect()
}

pub fn extract(json: &str, paths: Vec<String>) -> HashMap<String, Option<String>> {
    let mut result = HashMap::new();

    for path in paths {
        result.insert(
            path.clone(),
            get(json, &path),
        );
    }

    result
}

pub fn extract_values(json: &str, paths: Vec<String>) -> Value {
    let mut object = serde_json::Map::new();

    for path in paths {
        let value = get_value(json, &path).unwrap_or(Value::Null);
        object.insert(path, value);
    }

    Value::Object(object)
}

fn parse_path(path: &str) -> Vec<Segment> {
    let mut segments = Vec::new();

    for part in path.split('.') {
        parse_part(part, &mut segments);
    }

    segments
}

fn parse_part(part: &str, segments: &mut Vec<Segment>) {
    let chars: Vec<char> = part.chars().collect();
    let mut key = String::new();
    let mut i = 0;

    while i < chars.len() {
        match chars[i] {
            '[' => {
                if !key.is_empty() {
                    segments.push(Segment::Key(key.clone()));
                    key.clear();
                }

                let mut inner = String::new();
                i += 1;

                while i < chars.len() && chars[i] != ']' {
                    inner.push(chars[i]);
                    i += 1;
                }

                if inner == "*" {
                    segments.push(Segment::Wildcard);
                } else if let Ok(index) = inner.parse::<usize>() {
                    segments.push(Segment::Index(index));
                }
            }
            '*' => {
                if !key.is_empty() {
                    segments.push(Segment::Key(key.clone()));
                    key.clear();
                }

                segments.push(Segment::Wildcard);
            }
            c => key.push(c),
        }

        i += 1;
    }

    if !key.is_empty() {
        segments.push(Segment::Key(key));
    }
}

fn get_value_at_segments<'a>(value: &'a Value, segments: &[Segment]) -> Option<&'a Value> {
    let mut current = value;

    for segment in segments {
        current = match segment {
            Segment::Key(key) => current.get(key)?,
            Segment::Index(index) => current.get(*index)?,
            Segment::Wildcard => return None,
        };
    }

    Some(current)
}

fn collect_search_values<'a>(value: &'a Value, segments: &[Segment], results: &mut Vec<&'a Value>) {
    if segments.is_empty() {
        results.push(value);
        return;
    }

    match &segments[0] {
        Segment::Key(key) => {
            if let Some(next) = value.get(key) {
                collect_search_values(next, &segments[1..], results);
            }
        }
        Segment::Index(index) => {
            if let Some(next) = value.get(*index) {
                collect_search_values(next, &segments[1..], results);
            }
        }
        Segment::Wildcard => match value {
            Value::Array(items) => {
                for item in items {
                    collect_search_values(item, &segments[1..], results);
                }
            }
            Value::Object(map) => {
                for item in map.values() {
                    collect_search_values(item, &segments[1..], results);
                }
            }
            _ => {}
        },
    }
}

pub(crate) fn value_to_string(value: &Value) -> String {
    match value {
        Value::String(s) => s.clone(),
        Value::Number(n) => n.to_string(),
        Value::Bool(b) => b.to_string(),
        Value::Null => "null".to_string(),
        Value::Array(_) | Value::Object(_) => serde_json::to_string(value).unwrap_or_default(),
    }
}
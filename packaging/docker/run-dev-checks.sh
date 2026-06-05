#!/usr/bin/env bash
set -euo pipefail

cd /app

# Linking libphp.so into the extension breaks runtime symbol resolution on Linux.
unset RUSTFLAGS

echo "== build =="
make build

echo "== tests =="
make test

echo "== examples =="
make examples

echo "== dev checks OK =="

#!/usr/bin/env bash
set -euo pipefail

if [ -f /etc/libphp-embed.env ]; then
  LIBPHP_DIR="$(grep '^LIBPHP_DIR=' /etc/libphp-embed.env | cut -d= -f2- | tr -d '\r')"
  LIBPHP_SO="$(grep '^LIBPHP_SO=' /etc/libphp-embed.env | cut -d= -f2- | tr -d '\r')"
  LIBCLANG_PATH="$(grep '^LIBCLANG_PATH=' /etc/libphp-embed.env | cut -d= -f2- | tr -d '\r' || true)"

  export LIBPHP_DIR LIBPHP_SO
  export LD_LIBRARY_PATH="${LIBPHP_DIR}"
  export LIBRARY_PATH="${LIBPHP_DIR}"

  if [ -n "${LIBCLANG_PATH}" ]; then
    export LIBCLANG_PATH
  fi
fi

# Do not set RUSTFLAGS here. Linking libphp.so into the extension .so makes PHP
# resolve zend internals from a second, uninitialized copy. .cargo/config.toml
# uses -undefined,dynamic_lookup so symbols bind to the running php binary.

export PATH="/root/.cargo/bin:${PATH}"
exec "$@"

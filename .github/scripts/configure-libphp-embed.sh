#!/usr/bin/env bash
set -euo pipefail

sudo apt-get update
sudo apt-get install -y libphp8.3-embed-dbgsym \
  || sudo apt-get install -y libphp8.3-embed

LIBPHP_SO="$(dpkg -L libphp8.3-embed-dbgsym 2>/dev/null | grep '/libphp\.so$' | head -1 || true)"
if [ -z "${LIBPHP_SO}" ]; then
  LIBPHP_SO="$(dpkg -L libphp8.3-embed | grep '/libphp\.so$' | head -1)"
fi

if [ -z "${LIBPHP_SO}" ] || [ ! -f "${LIBPHP_SO}" ]; then
  echo "::error::libphp.so not found after installing libphp8.3-embed"
  exit 1
fi

LIBPHP_DIR="$(dirname "${LIBPHP_SO}")"
echo "Using libphp embed library: ${LIBPHP_SO}"

if [ -n "${GITHUB_ENV:-}" ]; then
  {
    echo "LIBPHP_DIR=${LIBPHP_DIR}"
    echo "LD_LIBRARY_PATH=${LIBPHP_DIR}:${LD_LIBRARY_PATH:-}"
    echo "LIBRARY_PATH=${LIBPHP_DIR}:${LIBRARY_PATH:-}"
  } >> "${GITHUB_ENV}"
fi

export LIBPHP_DIR
export LD_LIBRARY_PATH="${LIBPHP_DIR}:${LD_LIBRARY_PATH:-}"
export LIBRARY_PATH="${LIBPHP_DIR}:${LIBRARY_PATH:-}"

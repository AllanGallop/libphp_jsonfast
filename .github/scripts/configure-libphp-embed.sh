#!/usr/bin/env bash
set -euo pipefail

sudo apt-get update
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y \
  libphp8.3-embed libphp8.3-embed-dbgsym

find_libphp_so() {
  local path=""

  for pkg in libphp8.3-embed libphp8.3-embed-dbgsym; do
    path="$(dpkg -L "${pkg}" 2>/dev/null \
      | grep -E '/libphp(-[0-9.]+)?\.so(\.[0-9]+)?$' \
      | head -1 || true)"
    if [ -n "${path}" ] && [ -f "${path}" ]; then
      echo "${path}"
      return 0
    fi
  done

  for path in /usr/lib/libphp.so /usr/lib/libphp8.3.so; do
    if [ -f "${path}" ]; then
      echo "${path}"
      return 0
    fi
  done

  if [ -L /etc/alternatives/libphp8 ]; then
    path="$(readlink -f /etc/alternatives/libphp8)"
    if [ -f "${path}" ]; then
      echo "${path}"
      return 0
    fi
  fi

  path="$(find /usr/lib -maxdepth 4 -name 'libphp*.so' -type f 2>/dev/null | head -1 || true)"
  if [ -n "${path}" ] && [ -f "${path}" ]; then
    echo "${path}"
    return 0
  fi

  return 1
}

LIBPHP_SO="$(find_libphp_so || true)"
if [ -z "${LIBPHP_SO}" ]; then
  echo "::error::libphp embed shared library not found after installing libphp8.3-embed"
  echo "Installed embed packages:"
  dpkg -L libphp8.3-embed 2>/dev/null | grep -E '\.so' || true
  exit 1
fi

LIBPHP_DIR="$(dirname "${LIBPHP_SO}")"
echo "Using libphp embed library: ${LIBPHP_SO}"

if [ -n "${GITHUB_ENV:-}" ]; then
  {
    echo "LIBPHP_DIR=${LIBPHP_DIR}"
    echo "LIBPHP_SO=${LIBPHP_SO}"
    echo "LD_LIBRARY_PATH=${LIBPHP_DIR}:${LD_LIBRARY_PATH:-}"
    echo "LIBRARY_PATH=${LIBPHP_DIR}:${LIBRARY_PATH:-}"
  } >> "${GITHUB_ENV}"
fi

export LIBPHP_SO
export LIBPHP_DIR
export LD_LIBRARY_PATH="${LIBPHP_DIR}:${LD_LIBRARY_PATH:-}"
export LIBRARY_PATH="${LIBPHP_DIR}:${LIBRARY_PATH:-}"

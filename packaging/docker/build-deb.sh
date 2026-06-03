#!/usr/bin/env bash
set -euo pipefail

cd /app

apt update
apt install -y \
  build-essential \
  debhelper \
  dh-php \
  php-dev \
  cargo \
  rustc \
  pkg-config \
  dos2unix \
  file \
  curl \
  ca-certificates \
  clang \
  libclang-dev

curl https://sh.rustup.rs -sSf | sh -s -- -y --profile minimal
. "$HOME/.cargo/env"

rustc --version
cargo --version

rm -rf /app/debian
cp -a /app/packaging/debian /app/debian

dos2unix /app/debian/rules
chmod 755 /app/debian/rules

echo "PWD: $(pwd)"
echo "== copied debian dir =="
ls -lsa /app/debian
echo "== rules =="
file /app/debian/rules
cat -A /app/debian/rules
echo "== make dry run =="
make -n -f /app/debian/rules clean

export LIBCLANG_PATH=/usr/lib/llvm-14/lib

dpkg-buildpackage -us -uc -b -d

mkdir -p /app/packaging/dist
mv /app/../*.deb /app/packaging/dist/ 2>/dev/null || true
mv /app/../*.changes /app/packaging/dist/ 2>/dev/null || true
mv /app/../*.buildinfo /app/packaging/dist/ 2>/dev/null || true

deb="$(ls /app/packaging/dist/php-jsonfast_*.deb | head -n1)"
cp "$deb" /app/packaging/dist/php-jsonfast-amd64.deb

ls -lah /app/packaging/dist
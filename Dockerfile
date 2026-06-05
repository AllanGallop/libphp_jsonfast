FROM ubuntu:24.04

RUN apt-get update && apt-get install -y \
    curl build-essential clang libclang-dev pkg-config git make \
    ca-certificates gnupg lsb-release software-properties-common \
    && add-apt-repository ppa:ondrej/php -y \
    && apt-get update \
    && (apt-get install -y php8.3-cli php8.3-dev libphp8.3-embed-dbgsym \
        || apt-get install -y php8.3-cli php8.3-dev libphp8.3-embed) \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY .github/scripts/configure-libphp-embed.sh /usr/local/bin/configure-libphp-embed.sh
RUN chmod +x /usr/local/bin/configure-libphp-embed.sh \
    && SKIP_EMBED_APT_INSTALL=1 WRITE_EMBED_ENV=/etc/libphp-embed.env \
       bash /usr/local/bin/configure-libphp-embed.sh \
    && llvm_lib="$(find /usr/lib/llvm-*/lib -name 'libclang.so*' 2>/dev/null | head -1)" \
    && if [ -n "${llvm_lib}" ]; then \
         echo "LIBCLANG_PATH=$(dirname "${llvm_lib}")" >> /etc/libphp-embed.env; \
       fi

RUN curl https://sh.rustup.rs -sSf | sh -s -- -y
ENV PATH="/root/.cargo/bin:${PATH}"

# Link libphp only while installing cargo-php (build-time). Runtime builds use
# .cargo/config.toml (-undefined,dynamic_lookup) so the .so binds to php-cli.
RUN LIBPHP_DIR="$(grep '^LIBPHP_DIR=' /etc/libphp-embed.env | cut -d= -f2-)" \
    && RUSTFLAGS="-L ${LIBPHP_DIR} -lphp" \
       cargo install cargo-php --locked --version 0.1.18

COPY packaging/docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /app
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["bash"]

FROM ubuntu:24.04

RUN apt-get update && apt-get install -y \
    curl build-essential clang libclang-dev pkg-config git make \
    ca-certificates gnupg lsb-release software-properties-common \
    && add-apt-repository ppa:ondrej/php -y \
    && apt-get update \
    && apt-get install -y \
    php8.3-cli php8.3-dev libphp8.3-embed \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN curl https://sh.rustup.rs -sSf | sh -s -- -y
ENV PATH="/root/.cargo/bin:${PATH}"
ENV LD_LIBRARY_PATH="/usr/lib"
ENV RUSTFLAGS="-L /usr/lib -lphp"

RUN cargo install cargo-php --locked --version 0.1.18

WORKDIR /app

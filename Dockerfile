FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y \
    curl build-essential clang libclang-dev pkg-config git \
    libpng-dev \
    python3 python3-pip python3-venv \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN curl https://sh.rustup.rs -sSf | sh -s -- -y
ENV PATH="/root/.cargo/bin:${PATH}"

RUN cargo install cargo-php --locked

WORKDIR /app
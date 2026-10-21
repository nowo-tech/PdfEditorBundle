# PHP 8.2 Alpine for development and tests (no frontend)
FROM php:8.2-cli-alpine

RUN apk add --no-cache \
    git \
    unzip \
    bash \
    libzip-dev \
    python3 \
    py3-pip

RUN docker-php-ext-install -j$(nproc) zip

# PCOV for code coverage
RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del $PHPIZE_DEPS

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN git config --global --add safe.directory /app

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="/app/vendor/bin:${PATH}"
# PyMuPDF 2.x has no musllinux wheel yet: stay on the 1.x line from engine/requirements.txt (binary wheels only).
RUN pip3 install --break-system-packages --only-binary=:all: "pymupdf>=1.24.0,<2"
# Python engine tests (REQ-TEST-004): Alpine packages, no compilation.
RUN apk add --no-cache py3-pytest py3-pytest-cov

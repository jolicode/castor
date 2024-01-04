# syntax=docker/dockerfile:1.4

FROM php:8.2-cli-alpine as builder

RUN apk add git

COPY --from=composer:2.5.2 /usr/bin/composer /usr/bin/composer

WORKDIR /castor/tools/phar
COPY tools/phar/composer.* .
RUN composer install --no-dev --no-interaction --no-progress

WORKDIR /castor
COPY composer.* ./
RUN composer install --no-dev --no-interaction --no-progress

COPY . ./

WORKDIR /castor/tools/phar
RUN vendor/bin/box compile -c box.linux-amd64.json

FROM php:8.2-cli-alpine

COPY --link --from=builder /castor/tools/phar/build/castor.linux-amd64.phar /usr/local/bin/castor

ARG WITH_DOCKER=0
RUN if [ "$WITH_DOCKER" = "1" ]; then \
        apk add --update \
            docker-cli \
            docker-cli-compose ; \
    fi

WORKDIR /project
ENTRYPOINT [ "/usr/local/bin/castor" ]

FROM boxproject/box:4.6.1 as box

FROM ubuntu:22.04 as dev

ARG DEBIAN_FRONTEND=noninteractive

WORKDIR /app

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    wget \
    software-properties-common \
    && add-apt-repository ppa:ondrej/php -y

RUN apt-get update && apt-get install -y \
    php8.2-cli \
    php8.2-curl \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=box /box.phar /usr/local/bin/box

COPY --from=composer:2.6.6 /usr/bin/composer /usr/bin/composer

COPY . ./

RUN ln -s /app/bin/castor /usr/local/bin/castor

COPY entrypoint.sh /
RUN chmod +x /entrypoint.sh

ENTRYPOINT [ "/entrypoint.sh" ]



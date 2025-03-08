#!/usr/bin/env bash

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$DIR")"

PHP_VERSION="$(grep -E -o 'FROM php:[0-9]+[.][0-9]+' "${DIR}/Dockerfile" | head -n1 | grep -E -o '[0-9]+[.][0-9]+')"
if [ -z "${PHP_VERSION}" ]; then
    echo "Cannot determine PHP version from Dockerfile"
    exit 1
fi
TAG=gameconcz/gamecon:"${PHP_VERSION}"

# docker buildx create --use
docker buildx build --platform linux/amd64,linux/arm --pull --tag "${TAG}" --push "${PROJECT_ROOT}/.docker"

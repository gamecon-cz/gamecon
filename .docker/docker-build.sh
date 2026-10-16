#!/usr/bin/env bash

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$DIR")"

PHP_VERSION="$(grep -E -o 'FROM php:[0-9]+[.][0-9]+' "${DIR}/Dockerfile" | head -n1 | grep -E -o '[0-9]+[.][0-9]+')"
if [ -z "${PHP_VERSION}" ]; then
    echo "Cannot determine PHP version from Dockerfile"
    exit 1
fi
# dev keeps the tag that docker-compose.yml and the workflows already pull; the preview and archive images build on runtime
TAG=gameconcz/gamecon:"${PHP_VERSION}"
RUNTIME_TAG=gameconcz/gamecon:"${PHP_VERSION}-runtime"

# a failed build must not push the other image
set -e

# docker buildx create --use

# dev goes first: it is the build that downloads (pecl, NodeSource), and the runtime build then reuses its layers
docker buildx build --network=host --platform linux/amd64 --pull --target dev --tag "${TAG}" --push "${PROJECT_ROOT}/.docker"
docker buildx build --network=host --platform linux/amd64 --pull --target runtime --tag "${RUNTIME_TAG}" --push "${PROJECT_ROOT}/.docker"

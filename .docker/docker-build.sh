#!/usr/bin/env bash

# Builds and pushes gameconcz/gamecon:<php> (dev) and gameconcz/gamecon:<php>-runtime for every PHP minor version given,
# the PHP_VERSION default of the Dockerfile when none is: ./docker-build.sh 8.3 8.2
#
# To try a build without publishing it, keep it local under a name nobody else pulls:
#   GAMECON_IMAGE_REPOSITORY=gamecon-local-test GAMECON_IMAGE_OUTPUT=--load ./docker-build.sh 8.2

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$DIR")"

# A name other than the public one must say what to do with the image, so a forgotten GAMECON_IMAGE_OUTPUT cannot push it.
if [ -n "${GAMECON_IMAGE_REPOSITORY:-}" ] && [ -z "${GAMECON_IMAGE_OUTPUT:-}" ]; then
    echo "GAMECON_IMAGE_REPOSITORY needs GAMECON_IMAGE_OUTPUT too: --load keeps the images local, --push publishes them"
    exit 1
fi
IMAGE_REPOSITORY="${GAMECON_IMAGE_REPOSITORY:-gameconcz/gamecon}"
IMAGE_OUTPUT="${GAMECON_IMAGE_OUTPUT:---push}"

PHP_VERSIONS=("$@")
if [ "${#PHP_VERSIONS[@]}" -eq 0 ]; then
    DEFAULT_PHP_VERSION="$(sed -n -E 's/^ARG PHP_VERSION=([0-9]+[.][0-9]+)$/\1/p' "${DIR}/Dockerfile" | head -n1)"
    if [ -z "${DEFAULT_PHP_VERSION}" ]; then
        echo "Cannot determine PHP version from Dockerfile"
        exit 1
    fi
    PHP_VERSIONS=("${DEFAULT_PHP_VERSION}")
fi

for PHP_VERSION in "${PHP_VERSIONS[@]}"; do
    if ! [[ "${PHP_VERSION}" =~ ^[0-9]+[.][0-9]+$ ]]; then
        echo "Expected a PHP minor version like 8.3, got '${PHP_VERSION}'"
        exit 1
    fi
done

# the first failed build stops the script, so nothing after it is pushed
set -e

# docker buildx create --use

for PHP_VERSION in "${PHP_VERSIONS[@]}"; do
    # dev keeps the tag that docker-compose.yml and the workflows already pull; the preview and archive images build on runtime
    TAG="${IMAGE_REPOSITORY}:${PHP_VERSION}"
    RUNTIME_TAG="${IMAGE_REPOSITORY}:${PHP_VERSION}-runtime"

    # dev goes first: it is the build that downloads (pecl, NodeSource), and the runtime build then reuses its layers
    docker buildx build --network=host --platform linux/amd64 --pull --build-arg "PHP_VERSION=${PHP_VERSION}" --target dev --tag "${TAG}" "${IMAGE_OUTPUT}" "${PROJECT_ROOT}/.docker"
    docker buildx build --network=host --platform linux/amd64 --pull --build-arg "PHP_VERSION=${PHP_VERSION}" --target runtime --tag "${RUNTIME_TAG}" "${IMAGE_OUTPUT}" "${PROJECT_ROOT}/.docker"
done

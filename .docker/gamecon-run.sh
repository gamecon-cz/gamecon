#!/usr/bin/env bash
set -x

bash /.docker/init-container.sh "$1"

apache2-foreground

#!/usr/bin/env bash
set -x

bash /.docker/init-container.sh "$1"

cron

apache2-foreground

#!/usr/bin/env bash
set -x

bash /.docker/init-container.sh "$1" && \

sudo -u www-data composer --working-dir=/var/www/html/gamecon install && \

apache2-foreground

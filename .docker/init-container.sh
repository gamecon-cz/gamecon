#!/usr/bin/env sh

set -x

# This script is to be executed when the docker container is started

# Set UID of user www-data on guest to match the UID of the user on the host machine
# `stat -c "%u" $1` gives user(owner) of given parameter (expected a file inside current Docker container)
usermod -u $(stat -c "%u" $1) www-data
# Set GID of group www-data on guest to match the GID of the users primary group on the host machine
groupmod -g $(stat -c "%g" $1) www-data

# Allow user www-data to log in to use development tools
usermod -s /bin/bash www-data

chown -R www-data:www-data /var/www

chmod -R u+rw /home/www-data/.composer
chmod -R u+rw /var/www/html/gamecon/cache
chmod -R u+rw /var/www/html/gamecon/web/soubory/systemove

# Install crontabs from /.docker/cron/ into /etc/cron.d/ with root:root and 644.
# Cron silently ignores files in /etc/cron.d that are not owned by root
# or are writable by group or other; bind-mounted files keep host ownership,
# so we copy rather than mount directly.
if [ -d /.docker/cron ]; then
  for cron_src in /.docker/cron/*; do
    [ -f "$cron_src" ] || continue
    install -o root -g root -m 644 "$cron_src" /etc/cron.d/"$(basename "$cron_src")"
  done
fi

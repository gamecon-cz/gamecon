#!/usr/bin/env bash
set -x

bash /.docker/init-container.sh "$PWD"

cron

# exec makes Apache PID 1, so it receives the SIGWINCH that `docker stop` sends
exec apache2-foreground

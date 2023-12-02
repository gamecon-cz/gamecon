#!/usr/bin/env bash

CURRENT_SCRIPT_DIR=$(realpath "$(dirname -- "${BASH_SOURCE[0]}")")

docker build --pull --tag jaroslavtyc/gamecon-8.2:latest --tag jaroslavtyc/gamecon:8.2 "${CURRENT_SCRIPT_DIR}" \
  && docker push jaroslavtyc/gamecon-8.2:latest && docker push jaroslavtyc/gamecon:8.2

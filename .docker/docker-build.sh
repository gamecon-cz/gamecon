#!/usr/bin/env bash
docker build --pull --tag gameconcz/gamecon:8.2 ./.docker && docker push gameconcz/gamecon:8.2

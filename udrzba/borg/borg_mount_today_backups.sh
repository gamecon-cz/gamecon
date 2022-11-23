#!/usr/bin/env bash

source gamecon_borgbase.env

TODAY_BACKUP_NAME="gamecon-$(date '+%Y-%m-%d')"

TODAY_BACKUP_DIR=".mounts/$TODAY_BACKUP_NAME"

mkdir -p "$TODAY_BACKUP_DIR"

borg mount "$BORG_REPO::$TODAY_BACKUP_NAME" "$TODAY_BACKUP_DIR" && echo "$TODAY_BACKUP_DIR"

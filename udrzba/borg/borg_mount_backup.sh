#!/usr/bin/env bash

source gamecon_borgbase.env

DATE=$1

BACKUP_NAME="gamecon-$DATE"

if [ $# -eq 0 ]; then
  echo "Missing date argument in format $(date '+%Y-%m-%d')"
  exit
fi

BACKUP_DIR="mounts/$BACKUP_NAME"

mkdir -p "$BACKUP_DIR"

borg mount "$BORG_REPO::$BACKUP_NAME" "$BACKUP_DIR" \
  && echo "$BACKUP_DIR" \
  && echo "WARNING mounting locks the repository - backup is now blocked. Use 'borg umount $BACKUP_DIR' to unlock it"

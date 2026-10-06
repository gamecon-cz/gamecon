#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Wires the versioned commit hook (.githooks/prepare-commit-msg) in via core.hooksPath.
# Called by `make init`; safe to run repeatedly, and skips quietly when there is nothing to wire.

cd "$(dirname "$DIR")"

if ! git rev-parse --git-dir > /dev/null 2>&1; then
	echo 'Git hooks: not a git repository, skipping'
	exit 0
fi

# core.hooksPath is relative to each checkout and a missing directory is ignored silently, so
# wiring it from a tree without the hook would make every such checkout lose its hook.
if [ ! -f .githooks/prepare-commit-msg ]; then
	echo 'Git hooks: .githooks/prepare-commit-msg not in this checkout, skipping'
	exit 0
fi

CURRENT="$(git config --local --get core.hooksPath || true)"
if [ -n "$CURRENT" ]; then
	echo "Git hooks: core.hooksPath already set to $CURRENT, leaving it"
	exit 0
fi

git config --local core.hooksPath .githooks
echo 'Git hooks: .githooks'
echo 'Git hooks: the setting is shared by every worktree of this repository, and a worktree on a branch without .githooks/ gets no hook'

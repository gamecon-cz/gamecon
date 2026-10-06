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

# core.hooksPath replaces the hooks directory wholesale, so wiring it would silently switch off
# any other hook the developer relies on (a linter, a secret scan), in .git/hooks or in a global
# hooks directory. A global prepare-commit-msg is the one thing this is meant to replace.
# (`git rev-parse --git-path hooks` would follow core.hooksPath, so the repo's own directory is spelled out.)
# Spelled out with a plain `git config --get` (no `--type=path`, which git before 2.18 lacks and
# which would make the check silently skip); a leading ~ is expanded by hand.
LOCAL_HOOKS="$(cd "$(git rev-parse --git-common-dir)/hooks" 2> /dev/null && pwd -P || true)"
CONFIGURED_HOOKS="$(git config --get core.hooksPath || true)"
CONFIGURED_HOOKS="${CONFIGURED_HOOKS/#\~/$HOME}"
CONFIGURED_HOOKS="$(cd "$CONFIGURED_HOOKS" 2> /dev/null && pwd -P || true)"
DIRECTORIES=("$LOCAL_HOOKS")
# The configured path may be the repo's own hooks directory; it is then listed once.
if [ "$CONFIGURED_HOOKS" != "$LOCAL_HOOKS" ]; then
	DIRECTORIES+=("$CONFIGURED_HOOKS")
fi
DISPLACED=()
for DIRECTORY in "${DIRECTORIES[@]}"; do
	[ -n "$DIRECTORY" ] && [ -d "$DIRECTORY" ] || continue
	for HOOK in "$DIRECTORY"/*; do
		[ -f "$HOOK" ] && [ -x "$HOOK" ] || continue
		NAME="$(basename "$HOOK")"
		# Git runs only files named exactly like a hook; a backup or a helper script is not one.
		case "$NAME" in
			applypatch-msg | pre-applypatch | post-applypatch | pre-commit | pre-merge-commit | prepare-commit-msg | commit-msg | post-commit | pre-rebase | post-checkout | post-merge | pre-push | pre-receive | update | proc-receive | post-receive | post-update | reference-transaction | push-to-checkout | pre-auto-gc | post-rewrite | sendemail-validate | fsmonitor-watchman | p4-changelist | p4-prepare-changelist | p4-post-changelist | p4-pre-submit | post-index-change) ;;
			*) continue ;;
		esac
		if [ "$DIRECTORY" != "$LOCAL_HOOKS" ] && [ "$NAME" = prepare-commit-msg ]; then
			continue
		fi
		DISPLACED+=("$HOOK")
	done
done
if [ "${#DISPLACED[@]}" -gt 0 ]; then
	echo 'Git hooks: not wiring .githooks, it would switch off hooks you already have:'
	printf '  %s\n' "${DISPLACED[@]}"
	echo 'Git hooks: the commit prefix hook (.githooks/prepare-commit-msg) is NOT active until you do'
	echo 'Git hooks: move or merge them into .githooks/ (one file per hook name), then run: git config --local core.hooksPath .githooks'
	exit 0
fi

git config --local core.hooksPath .githooks
echo 'Git hooks: .githooks'
echo 'Git hooks: the setting is shared by every worktree of this repository, and a worktree on a branch without .githooks/ gets no hook'

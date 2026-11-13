#!/usr/bin/env bash
# The brief of a lockfile change, usage: bin/security-update-brief.sh <base> <head> <output file> [<head label>]
# The workflow security-update-brief runs it for a Dependabot pull request; run it by hand to see a brief before one exists.
# Needs python3 (3.10 or newer) and git on this machine. Composer is the one on PATH on a GitHub runner and inside the dev
# container; anywhere else with docker it runs through the project's helper (bin-docker/composer), never the host's composer.
# The command in SECURITY_BRIEF_COMPOSER replaces that choice (set it to composer to use the one on PATH).
set -euo pipefail
IFS=$'\n\t'
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

base="${1:?usage: bin/security-update-brief.sh <base> <head> <output file> [<head label>]}"
head="${2:?usage: bin/security-update-brief.sh <base> <head> <output file> [<head label>]}"
output="${3:?usage: bin/security-update-brief.sh <base> <head> <output file> [<head label>]}"
# a relative output path is the caller's, not the project root's
case "$output" in /*) ;; *) output="$PWD/$output" ;; esac

# project root
cd "$(dirname "$DIR")"

missing=''
if ! command -v git >/dev/null 2>&1; then
    missing='git'
fi
if ! python3 -I -c 'import sys; sys.exit(sys.version_info < (3, 10))' >/dev/null 2>&1; then
    missing="${missing:+$missing and }python3 3.10 or newer"
fi
if [ -n "$missing" ]; then
    echo "bin/security-update-brief.sh needs $missing on this machine: a Python script writes the brief and reads the repository with git. Install it and run again." >&2
    exit 1
fi

composer_command="${SECURITY_BRIEF_COMPOSER:-composer}"
if [ -z "${SECURITY_BRIEF_COMPOSER:-}" ] && [ -z "${GITHUB_ACTIONS:-}" ] && command -v docker >/dev/null 2>&1; then
    for helper in bin/composer bin-docker/composer docker-bin/composer; do
        if [ -x "$helper" ]; then
            composer_command="$(python3 -I -c 'import shlex, sys; print(shlex.quote(sys.argv[1]))' "$PWD/$helper")"
            break
        fi
    done
fi
composer_arguments=()
if [ "$composer_command" != composer ]; then
    # a docker helper sees only the project it belongs to, so the temporary project of the advisory lookup is made inside this one
    audit_directory="$(mktemp -d "$PWD/.security-update-brief-audit.XXXXXX")"
    chmod 755 "$audit_directory"
    trap '[ ! -d "$audit_directory" ] || rmdir "$audit_directory" 2>/dev/null || echo "bin/security-update-brief.sh: $audit_directory is not empty and stays; remove it." >&2' EXIT
    composer_arguments=(--composer-command "$composer_command" --audit-directory "$audit_directory")
elif ! composer --version >/dev/null 2>&1; then
    echo "bin/security-update-brief.sh: composer does not run on this machine, and no docker helper (bin/composer, bin-docker/composer or docker-bin/composer) is in use. Set SECURITY_BRIEF_COMPOSER to the command that starts composer; without it the brief says the advisory lookup failed." >&2
fi

python3 -I .github/scripts/security-update-brief.py brief \
    --repository . \
    --base-sha "$base" \
    --head-sha "$head" \
    --head-label "${4:-$head}" \
    --scan-directories admin bin migrace model nastaveni preview symfony tests udrzba web \
    --instructions-file .github/security-update-brief.md \
    ${composer_arguments[@]+"${composer_arguments[@]}"} \
    --output "$output"

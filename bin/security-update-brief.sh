#!/usr/bin/env bash
# The brief of a lockfile change, usage: bin/security-update-brief.sh <base> <head> <output file> [<head label>]
# The workflow security-update-brief runs it for a Dependabot pull request; run it by hand to see a brief before one exists.
# Needs python3, git and composer on PATH, which the dev container and the GitHub runners have.
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

python3 -I .github/scripts/security_update_brief.py brief \
    --repository . \
    --base-sha "$base" \
    --head-sha "$head" \
    --head-label "${4:-$head}" \
    --scan-directories admin bin migrace model nastaveni preview symfony tests udrzba web \
    --instructions-file .github/security-update-brief.md \
    --output "$output"

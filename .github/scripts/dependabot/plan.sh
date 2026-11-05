#!/usr/bin/env bash
# The planning step of the workflow fold-dependabot-composer: which open Dependabot composer pull requests to fold, and whether the
# folded pull request is already up to date. Read-only. Writes fold, pull_requests, heads, signature and branch_tip to $GITHUB_OUTPUT.
#
# Environment (the workflow sets what is not a variable of GitHub itself):
#   GH_TOKEN      a token that may read pull requests
#   REQUESTED     the numbers the person asked for, separated by spaces; empty or blank means every open one
#   BATCH_BRANCH  the branch of the folded pull request
#   GITHUB_OUTPUT the file the step writes its outputs to
set -euo pipefail
IFS=$'\n\t'
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# project root
cd "$DIR/../../.."

list=$(bin/fold-dependabot-composer.sh --list)

# any blank (space, tab, newline) separates the numbers, as it did when the shell of the step had the default IFS
IFS=' ' read -r -a requested <<< "$(printf '%s' "${REQUESTED:-}" | tr -s '[:space:]' ' ')"
if [ ${#requested[@]} -gt 0 ]; then
    for number in "${requested[@]}"; do
        if [[ ! "$number" =~ ^[0-9]+$ ]]; then
            echo "::error::Not a pull request number: $number"
            exit 1
        fi
    done
    # exit 1 of grep is "no match", which is an answer; anything else is a failure
    list=$(printf '%s\n' "$list" | grep -E "^($(printf '%s\n' "${requested[@]}" | paste -sd'|')) " || [ $? -eq 1 ])
fi

if [ -z "$list" ]; then
    echo "::notice::No open Dependabot composer pull request to fold."
    echo "fold=false" >> "$GITHUB_OUTPUT"
    exit 0
fi

pull_requests=$(printf '%s\n' "$list" | cut -d' ' -f1 | paste -sd' ')
heads=$(printf '%s\n' "$list" | awk '{print $1 "@" $2}' | paste -sd' ')
signature="main=$(git rev-parse HEAD) $heads"

# a pull request from a fork with the same branch name is not ours
existing_body=$(gh pr list --head "$BATCH_BRANCH" --state open --json body,isCrossRepository --jq '[.[] | select(.isCrossRepository | not)] | .[0].body // ""')
if printf '%s' "$existing_body" | grep -qF "<!-- fold-dependabot-composer: $signature -->"; then
    echo "::notice::The folded pull request is up to date ($signature)."
    echo "fold=false" >> "$GITHUB_OUTPUT"
    exit 0
fi

# a failing ls-remote fails the step: an empty tip means "the branch does not exist" to the lease of the push
branch_tip=$(git ls-remote --heads origin "refs/heads/$BATCH_BRANCH" | cut -f1)

{
    echo "fold=true"
    echo "pull_requests=$pull_requests"
    echo "heads=$heads"
    echo "signature=$signature"
    echo "branch_tip=$branch_tip"
} >> "$GITHUB_OUTPUT"

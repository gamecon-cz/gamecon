#!/usr/bin/env bash
# The build step of the workflow fold-dependabot-composer: folds the Dependabot pull requests onto the checked-out commit with
# bin/fold-dependabot-composer.sh, which runs `composer install` and so package code, and hands the result on as a git bundle.
# It runs in a job that has no token and no secrets.
#
# Environment:
#   HEADS         <number>@<head sha> of the pull requests to fold, separated by spaces
#   RUNNER_TEMP   a directory outside the checkout, for the summary and the bundle (the script refuses a tree that is not clean)
#   GITHUB_SHA    the commit the run started from, the base of the bundle
#   GITHUB_OUTPUT the file the step writes its output (folded=true|false) to
#   GIT_AUTHOR_* and GIT_COMMITTER_*  the identity of the commits it makes
set -euo pipefail
IFS=$'\n\t'
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# project root
cd "$(dirname "$DIR")"

IFS=' ' read -r -a pull_requests <<< "$HEADS"
bin/fold-dependabot-composer.sh "${pull_requests[@]}" | tee "$RUNNER_TEMP/fold-summary.txt"

if [ "$(git rev-list --count "$GITHUB_SHA..HEAD")" -eq 0 ]; then
    echo "::notice::Nothing was folded; see the log of this step."
    echo "folded=false" >> "$GITHUB_OUTPUT"
    exit 0
fi

git bundle create "$RUNNER_TEMP/fold.bundle" "$GITHUB_SHA..HEAD"
echo "folded=true" >> "$GITHUB_OUTPUT"

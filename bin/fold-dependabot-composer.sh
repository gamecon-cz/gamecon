#!/usr/bin/env bash
# Folds the open Dependabot composer pull requests into the checked-out branch: their lock commits as they are,
# then `composer install` in the dev image to sync the committed vendor/ as one more commit.
#
# usage: bin/fold-dependabot-composer.sh --list                   the open pull requests that only change composer.lock, "<number> <head sha>" per line
#        bin/fold-dependabot-composer.sh <number>[@<head sha>] ...  fold these onto the checked-out branch (a clean tree, normally main);
#                                                                 with a head, a pull request that has moved on since is skipped
#
# Never pushes and never touches a Dependabot branch. --list needs gh; folding needs git, python3 and docker.
# stdout holds only the lines of this script (folded, skipped, synced); what composer and the checks print goes to stderr, because
# the workflow puts the stdout into a pull request and composer runs package code.
# Tests replace the tools with DEV_IMAGE_RUN (the command prefix that runs a command in the dev image) and GH.
set -euo pipefail
IFS=$'\n\t'
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# project root
cd "$(dirname "$DIR")"

if [ -n "${DEV_IMAGE_RUN:-}" ]; then
    IFS=' ' read -r -a dev_image_run <<< "$DEV_IMAGE_RUN"
else
    dev_image_run=(docker run --rm --user "$(id -u):$(id -g)" -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer -v "$PWD:$PWD" -w "$PWD" gameconcz/gamecon:8.3)
fi

list_pull_requests() {
    "${GH:-gh}" pr list --state open --author 'app/dependabot' --base main --limit 1000 --json number,headRefName,headRefOid,files | python3 -I -c '
import json, sys
for pull_request in sorted(json.load(sys.stdin), key=lambda item: item["number"]):
    if not pull_request["headRefName"].startswith("dependabot/composer/"):
        continue
    paths = [file["path"] for file in pull_request["files"]]
    if paths != ["composer.lock"]:
        print("skipped %d: it changes %s, not only composer.lock" % (pull_request["number"], ", ".join(paths)), file=sys.stderr)
        continue
    print(pull_request["number"], pull_request["headRefOid"])
'
}

if [ "${1:?usage: bin/fold-dependabot-composer.sh --list | <pull request number> [...]}" = "--list" ]; then
    list_pull_requests
    exit 0
fi

# untracked files too: the check after the install tells "composer changed this" from "was already here" by the tree being clean
if [ -n "$(git status --porcelain)" ]; then
    echo "The working tree is not clean; fold onto a clean checkout." >&2
    exit 1
fi

folded=()
for argument in "$@"; do
    # `<number>@<head sha>` folds the pull request only if its head is still the one listed
    number=${argument%%@*}
    expected=""
    case "$argument" in *@*) expected=${argument#*@} ;; esac
    reference="refs/dependabot/$number"
    # `+`: Dependabot force-pushes its branch when it rebases, and a second run in the same checkout must follow it
    git fetch --quiet origin "+pull/$number/head:$reference"
    if [ -n "$expected" ] && [ "$(git rev-parse "$reference")" != "$expected" ]; then
        echo "skipped $number: its head moved since the list was made"
        continue
    fi
    base=$(git merge-base HEAD "$reference")
    if [ "$(git diff --no-renames --name-only "$base" "$reference")" != "composer.lock" ]; then
        echo "skipped $number: it changes more than composer.lock"
        continue
    fi
    before=$(git rev-parse HEAD)
    # a merge commit ("Update branch") brings nothing of its own: what it merged is already in the base or is not Dependabot's
    for commit in $(git rev-list --reverse --no-merges "$base..$reference"); do
        if git cherry-pick "$commit" > /dev/null 2>&1; then
            continue
        fi
        # a cherry-pick that stopped with nothing staged and nothing unmerged is empty: the change is already on the branch
        if [ -f "$(git rev-parse --git-path CHERRY_PICK_HEAD)" ] && git diff --cached --quiet && [ -z "$(git ls-files --unmerged)" ]; then
            git cherry-pick --skip > /dev/null
        else
            git cherry-pick --abort > /dev/null 2>&1 || true
            git reset --quiet --hard "$before"
            echo "skipped $number: its lock commit does not apply to this branch"
            continue 2
        fi
    done
    if [ "$(git rev-parse HEAD)" = "$before" ]; then
        echo "skipped $number: already applied"
    else
        folded+=("$number")
        echo "folded $number"
    fi
done

if [ ${#folded[@]} -eq 0 ]; then
    echo "nothing to fold"
    exit 0
fi

"${dev_image_run[@]}" composer install --no-interaction >&2

# -z: a path with a space or a non-ASCII character is not quoted
outside=$(git status --porcelain -z --untracked-files=all | tr '\0' '\n' | cut -c4- | grep -v '^vendor/' || true)
if [ -n "$outside" ]; then
    echo "composer install changed files outside vendor/:" >&2
    echo "$outside" >&2
    exit 1
fi

# the root block of installed.php once flipped to `1.0.0+no-version-set`; it has to stay as committed
git show HEAD:vendor/composer/installed.php | python3 -I -c '
import sys
old = sys.stdin.read()
new = open("vendor/composer/installed.php").read()
if old.split("\x27versions\x27")[0] != new.split("\x27versions\x27")[0]:
    sys.exit("The root block of vendor/composer/installed.php changed; the sync is not trustworthy.")
'

git add -A vendor
if ! git diff --cached --quiet; then
    git commit --quiet -m 'Sync vendor/ with composer.lock'
fi

"${dev_image_run[@]}" php bin/check-vendor-matches-lock.php >&2
"${dev_image_run[@]}" php bin/check-dockerignore-dev-vendor.php >&2
( IFS=' '; echo "synced vendor/ for ${folded[*]}" )

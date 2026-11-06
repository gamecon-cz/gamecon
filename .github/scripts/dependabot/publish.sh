#!/usr/bin/env bash
# The steps of the publishing job of the workflow fold-dependabot-composer, which is the job that has the write token. None of them
# runs package code or folds anything: the build job's result arrives as a git bundle, and what it makes is checked before it is used.
#
# usage: .github/scripts/dependabot/publish.sh <check|body|push|pull-request|record>
#
# Environment (the workflow sets what is not a variable of GitHub itself):
#   RUNNER_TEMP, GITHUB_SHA, GITHUB_REPOSITORY, GITHUB_OUTPUT   of GitHub; RUNNER_TEMP/fold holds the artifact of the build job
#   BATCH_BRANCH   the branch of the folded pull request
#   GH_TOKEN       for push, pull-request and record only
#   PULL_REQUESTS  the numbers the plan listed (body); BRANCH_TIP  the tip of the branch the plan saw (push)
#   NUMBER, SIGNATURE  the pull request and the signature the plan made (record)
set -euo pipefail
IFS=$'\n\t'
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# project root
cd "$DIR/../../.."

# The build job is trusted with nothing but git objects, and what it made is checked: built on the commit this run started from (not on
# the main of now), at most 100 commits, a change only of composer.lock and of plain files under vendor/. GitHub checks the objects again
# when they are pushed. The summary of the build is read only for the lines the script itself can print.
check() {
    git fetch --quiet "$RUNNER_TEMP/fold/fold.bundle" HEAD:refs/heads/fold-incoming
    if [ "$(git merge-base "$GITHUB_SHA" fold-incoming)" != "$GITHUB_SHA" ]; then
        echo "The folded branch is not built on the commit this run started from." >&2
        exit 1
    fi
    if [ "$(git rev-list --count "$GITHUB_SHA..fold-incoming")" -gt 100 ]; then
        echo "The folded branch has more than 100 commits." >&2
        exit 1
    fi
    git diff --raw -z --no-renames "$GITHUB_SHA" fold-incoming | python3 -I .github/scripts/dependabot/check-fold-changes.py
    grep -E '^(skipped [0-9]+: (it changes more than composer\.lock|its lock commit does not apply to this branch|already applied|its head moved since the list was made)|folded [0-9]+)$' \
        "$RUNNER_TEMP/fold/fold-summary.txt" > "$RUNNER_TEMP/summary.txt" || [ $? -eq 1 ]
}

# Everything the pull request says is made before anything is pushed. The marker with the signature is written last, when the pull
# request has been opened or updated: a run that fails halfway is not taken for up to date by the next one.
body() {
    BRIEF_INSTRUCTIONS=.github/security-update-brief-batch.md bin/security-update-brief.sh "$GITHUB_SHA" fold-incoming brief.md "$(git rev-parse fold-incoming)"
    IFS=' ' read -r -a planned <<< "$PULL_REQUESTS"
    {
        echo "Složeno workflow \`fold-dependabot-composer\` z těchto PR od Dependabota, které zavře Dependabot sám po sloučení:"
        for planned_number in "${planned[@]}"; do
            if grep -qx "folded $planned_number" "$RUNNER_TEMP/summary.txt"; then
                echo "- [PR #$planned_number](https://github.com/$GITHUB_REPOSITORY/pull/$planned_number)"
            fi
        done
        echo
        echo "Větev \`$BATCH_BRANCH\` se při změně vstupů přestaví a přepíše; nepushuj do ní."
        if grep -q '^skipped' "$RUNNER_TEMP/summary.txt"; then
            echo
            echo "Nesloženo:"
            grep '^skipped' "$RUNNER_TEMP/summary.txt" | sed 's/^/- /'
        fi
    } > notes.md
    python3 -I .github/scripts/security-update-brief.py splice --body-file notes.md --brief-file brief.md --output body.md
    # at the end, where the shortening of a long body cannot cut it off
    printf '\n<!-- fold-dependabot-composer: pending -->\n' >> body.md
}

# The lease is the tip the plan job saw: a commit pushed to the branch since then, or a run that overtook this one, fails the push.
push() {
    local authorization
    authorization=$(printf 'x-access-token:%s' "$GH_TOKEN" | base64 -w0)
    echo "::add-mask::$authorization"
    git -c "http.https://github.com/.extraheader=AUTHORIZATION: basic $authorization" \
        push --force-with-lease="refs/heads/$BATCH_BRANCH:$BRANCH_TIP" origin "fold-incoming:refs/heads/$BATCH_BRANCH"
}

# The repository setting "Allow GitHub Actions to create and approve pull requests" has to be on.
# GitHub creates the `pull_request` runs for it but holds them until a person presses "Approve and run workflows"; nothing here starts them.
pull_request() {
    local existing url
    existing=$(gh pr list --head "$BATCH_BRANCH" --state open --json number,isCrossRepository --jq '[.[] | select(.isCrossRepository | not)] | .[0].number // empty')
    if [ -n "$existing" ]; then
        gh api --method PATCH "repos/$GITHUB_REPOSITORY/pulls/$existing" --field body=@body.md > /dev/null
        echo "number=$existing" >> "$GITHUB_OUTPUT"
    else
        url=$(gh pr create --base main --head "$BATCH_BRANCH" --title 'Zranitelnosti v Composeru: dávka z Dependabota' --body-file body.md)
        echo "number=${url##*/}" >> "$GITHUB_OUTPUT"
    fi
}

record() {
    sed "s|fold-dependabot-composer: pending|fold-dependabot-composer: $SIGNATURE|" body.md > body-final.md
    grep -qF "<!-- fold-dependabot-composer: $SIGNATURE -->" body-final.md
    gh api --method PATCH "repos/$GITHUB_REPOSITORY/pulls/$NUMBER" --field body=@body-final.md > /dev/null
}

case "${1:?usage: .github/scripts/dependabot/publish.sh <check|body|push|pull-request|record>}" in
    check) check ;;
    body) body ;;
    push) push ;;
    pull-request) pull_request ;;
    record) record ;;
    *) echo "unknown step: $1" >&2; exit 2 ;;
esac

# Folding Dependabot composer updates into one pull request

A Dependabot pull request is never merged on GitHub, because `vendor/` is committed (see `docs/security-update-brief.md`). Every batch of security fixes used to be reproduced by hand: take the lock commits, `composer install`, commit `vendor/`, run the checks, open a pull request, wait for the whole CI. The workflow `.github/workflows/fold-dependabot-composer.yml` does the mechanical part and leaves the judgement and the merge to a person.

## How to use it

Actions → "Fold Dependabot composer updates" → Run workflow, on `main`. The optional input takes pull request numbers separated by spaces, to fold only those. The one setting it needs is Settings → Actions → General → "Allow GitHub Actions to create and approve pull requests", which is on in this repository; without it the step that opens the pull request fails (the branch is already pushed by then, and a new run builds it again).

It finishes in a few minutes, and then there is one pull request from the branch `security-updates/composer`:

- its first commits are the lock commits of the Dependabot pull requests, as Dependabot wrote them, and the last is `Sync vendor/ with composer.lock`;
- its body has the review brief (packages, versions, advisories, where we use them, the steps for taking the update) and the list of the Dependabot pull requests it replaces;
- `Run tests` has been started on it, so by the time you read the brief the whole CI is running or done.

Read the brief, give each package a verdict (the steps are in the pull request), and merge. Dependabot closes its own pull requests when the bump is on `main`. A person always merges: the workflow never does. A running or finished folded pull request is rebuilt (the branch force-pushed) when you run the workflow again and the set of Dependabot pull requests or `main` has changed; if nothing changed, the run ends at once with a notice.

## What it does

1. **plan** (read-only token): lists the open Dependabot pull requests against `main` whose branch starts with `dependabot/composer/` and which change only `composer.lock`; the others (a manifest bump) are named in the log of this step, not in the pull request. The input `pull_requests` must be numbers, nothing else. It builds a signature from the head of `main` and the heads of those pull requests, and compares it with the one written in the body of the open folded pull request. Equal: nothing to do. The build then folds those very heads: a pull request that Dependabot has pushed to since is skipped, so the signature always describes what was built.
2. **build** (no token, no secrets): `bin/fold-dependabot-composer.sh` cherry-picks the lock commits onto `main`, runs `composer install` in the dev image `gameconcz/gamecon:8.3`, refuses the result if anything outside `vendor/` changed or the root block of `vendor/composer/installed.php` did, commits the sync, and runs `check-vendor-matches-lock` and `check-dockerignore`. This is the job that runs package code (composer plugins and scripts), so it has no credentials. The result leaves it as a git bundle.
3. **publish** (the write token, runs no package code): fetches the bundle and checks it: built on the commit the run started from, at most 100 commits, and a change only of `composer.lock` and of plain files under `vendor/` (a rename counts as removing the old file; a symlink, a submodule, `.gitmodules` and `.gitattributes` are refused). GitHub checks the objects again when they are pushed. It writes the brief with `bin/security-update-brief.sh` and the instructions in `.github/security-update-brief-batch.md`, and only then pushes the branch. The push has the lease of the tip that the plan saw, so a commit someone pushed to the branch meanwhile makes it fail instead of being overwritten. Then it opens or updates the pull request (a pull request from a fork with the same branch name is not taken for it), starts `Run tests` on the branch, and only then writes the signature into the body, so that a run which fails halfway is not taken for up to date by the next one. Only the push, the pull request, the start of the tests and the signature get the token; the checkout keeps no credentials. What reaches the body from the build is limited to the lines the script itself prints (`folded N` and the four `skipped N: …` messages); what composer prints goes to stderr.

The pull request is opened with the workflow token, and GitHub starts no `pull_request` workflow for such a pull request. That is why `Run tests` is started by `workflow_dispatch`, which is allowed. Its checks belong to the head commit of the branch and are shown on the pull request; the required check `Run tests job` has the same name.

## Locally

`bin/fold-dependabot-composer.sh --list` lists the open Dependabot composer pull requests it would fold. `bin/fold-dependabot-composer.sh 1419 1420` does the folding on the checked-out, clean branch (normally a worktree off `main`) in the dev image, and stops after the commits: it pushes nothing. This is the same code the workflow runs, so a failure in the workflow can be reproduced there. `make test-security-brief` runs its tests.

## When it does not work

- **A patch no longer applies, or composer cannot install:** the `build` job is red, and no pull request is opened or changed. The log of the step `Fold` has the composer message; then take the update by hand as `.github/security-update-brief.md` says.
- **A lock commit does not apply to `main`** (two Dependabot pull requests touch the same package): that one is skipped, the rest are folded, and the pull request says which was skipped. Run the workflow again after the folded pull request is merged.
- **Dependabot pull requests that also change `composer.json`** (a manifest bump) are not folded and not mentioned in the pull request, only in the log of the `plan` step; handle them by hand.
- **Nothing could be folded** (every pull request skipped): no pull request is opened or changed, the `build` job says so, and nothing records that the inputs were seen. A scheduled run would repeat the whole build every time, so look at the log and settle the skipped pull requests before a schedule is added.
- **The pull request is behind `main`:** `main` requires an up-to-date branch. Run the workflow again; the branch is rebuilt on the new `main` and the tests start again.
- **The push is refused (stale info):** someone pushed to `security-updates/composer` after the run started, or a second run overtook this one. Run it again; nothing was lost, because the branch is rebuilt from `main` and the Dependabot pull requests.
- **A Dependabot pull request has a merge commit** (someone pressed "Update branch" on it): the merge commit is left out and the lock commits are folded.

## Limits

- Composer only. UI bumps need a baseline build of the bundles first and are folded by hand for now.
- Only what Dependabot opened pull requests for. Advisories that exist only in Packagist's database (no GitHub alert) have no pull request and need a targeted update of their own; the brief lists them as remaining advisories.
- It is started by hand. A schedule (for example every three hours) is one `schedule:` entry, to be added after a manual run has produced a good pull request.

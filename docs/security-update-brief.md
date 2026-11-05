# Review brief in Dependabot pull requests

A Dependabot pull request is never merged on GitHub: `vendor/` and the built UI bundles are committed, so a raw bump of `composer.lock` or `ui/yarn.lock` leaves them stale. A composer bump fails `Run tests` at `make check-vendor-matches-lock` by design, a UI bump at `make check-ui-bundles` when the built bundle changes. The bump is reproduced locally, one pull request per ecosystem (see the bullet about JS libraries under "Best Practices" in `CLAUDE.md`).

To decide what a bump needs, the reviewer used to look up everything by hand. The workflow `.github/workflows/security-update-brief.yml` now writes a **brief** at the top of every Dependabot pull request body.

## How it works

- The workflow runs on `pull_request` (opened, reopened, synchronize, edited) and only for pull requests authored by `dependabot[bot]`. It reads the merge commit GitHub builds for the pull request: `HEAD^1` is `main`, `HEAD^2` the head of the pull request, `HEAD` has the Dependabot change on top. It has to be on `main` before it runs for a Dependabot pull request.
- `bin/security-update-brief.sh` runs `.github/scripts/security-update-brief.py` (Python 3, standard library only; `composer audit` for PHP, the npm bulk advisory endpoint for JS) on the lockfiles that the pull request changed. The wrapper holds the directories to search and the file with the steps, so the workflow and a local run print the same brief. Then the workflow reads the body, puts the brief first (a previous brief is replaced) and writes it back with `gh api --method PATCH`. Nothing in it is AI generated.
- The job token is read-only on Dependabot pull requests unless the job asks for more; this one asks for `contents: read` and `pull-requests: write`, and has no secrets. The script that splices the body runs without the token. The workflow runs only the repository's own files.
- Dependabot rewrites the whole body after a force-push, which drops the brief; the `synchronize` and `edited` runs put it back within seconds. A run whose head is no longer the head of the pull request writes nothing, so re-running an old run is harmless; to get a missing brief, re-run the latest one. A running run is never cancelled and at most one more waits behind it: cancelling the run for the new head in favour of a stale one could leave the body without a brief.
- A Dependabot pull request that changes no lockfile gets no brief.

## What the brief holds

Per changed lockfile (`composer.lock`, `ui/yarn.lock`): a table of the packages that matter (fix an advisory, carry a `patches/` patch, jump a major version; the package name links to its repository on GitHub, GitLab or Bitbucket (the name looks like a trusted reference, so no other host and no address with user info), or to its page on npm, the compare link has its own column, and an advisory id links to the advisory: `GHSA-…` to the GitHub Advisory Database, any other id to the address `composer audit` gave; an address that is not a short, plain `https://` one, or a name that does not look like an npm name, stays unlinked text), then one JSON line per package with versions, the jump, the library's repository and a compare link, release date, what requires it, what the new version requires that the old one did not, the `composer.json` / `ui/package.json` range and the range before, the patches, and `used_in`: how many of our files per top-level directory mention the package's PHP namespaces (or its name in quotes in `ui/`). Then the advisories the update fixes and the ones that remain, and the steps for taking the update in `.github/security-update-brief.md`. The script's docstring and `tests/security_update_brief/` describe the details.

`used_in` is a text search, not an analysis:

- PHP namespaces (PSR-4 and PSR-0 prefixes from the lock) are looked for in `*.php`, `*.yaml`, `*.yml`, `*.xml`, `*.twig`, `*.neon` and `*.dist` files under `admin bin migrace model nastaveni preview symfony tests udrzba web` (the list is in `bin/security-update-brief.sh`) and in the files of the repository root.
- Directories named `vendor`, `node_modules`, `var`, `build`, `.git`, `.yarn` or `.svelte-kit` are skipped at any depth, and so are files over 1 MB.
- A package that autoloads without a namespace prefix (`"no_namespaces": true` in its line: classmap or files autoload, as with `setasign/tfpdf`, `dg/mysql-dump`, `phpunit/phpunit`, `phpstan/phpstan` and `rector/rector`) has nothing to look for and so no `used_in`; neither has one used only through another package's configuration or through XTemplate files.

## Changing it

- The steps for taking an update live in `.github/security-update-brief.md` and are put in the brief as they are (at most 8,000 characters). `tests/security_update_brief/test_gamecon_setup.py` checks that every `make` target and wrapper they name exists, and that the directories in `bin/security-update-brief.sh` exist.
- The script is a copy of a shared one, and the `Version:` line in its docstring says which. A change that every project would want is made in the shared copy first, and then the script here and `tests/security_update_brief/test_security_update_brief.py` are both replaced from it (the path of the script at the top of the test file is set again); a Gamecon difference goes into the wrapper or the instructions file, never into the script.
- Run the unit tests with `make test-security-brief` (also part of `make tests` and of `Run tests`).
- To look at a brief before Dependabot has opened a pull request: `bin/security-update-brief.sh <old commit> <new commit> /tmp/brief.md` in a checkout where both commits exist, with `python3`, `git` and `composer` on `PATH` (the dev container has them; if git there reports dubious ownership of the checkout, the script fails with git's message, and `git config --global --add safe.directory <the checkout>` fixes it).

## Limits

- The brief stays under the 65,536 characters of a pull request body: advisory titles and the details of resolver-only packages are shortened first, JS before PHP, and Dependabot's own text is cut at a paragraph end to make room. The lockfile diff is the full record.
- `package-lock.json` is not read; the repository has none that holds packages (`ui/` uses yarn 1).
- Advisories come from `composer audit` (audit settings of the base branch's `composer.json`, never the pull request's) and from the npm advisory database. When a lookup fails, the brief says so instead of claiming that nothing is left. The list of remaining advisories is the one of today, also when the pull request is old.
- Reading the body and writing it back is one step, but the API has no conditional update: a Dependabot rewrite that lands in those seconds is overwritten: the body then keeps Dependabot's previous text under a fresh brief until Dependabot writes it again.

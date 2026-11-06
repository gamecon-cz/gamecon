<details>
<summary>How to take this update (instructions for Claude)</summary>

This pull request is built by the workflow `fold-dependabot-composer`: it folds every open Dependabot composer pull request into one branch, with their lock commits as Dependabot wrote them and `vendor/` synced by `composer install`, so nothing has to be reproduced by hand. **Do not push to its branch**: the workflow rebuilds it from `main` and force-pushes whenever the set of Dependabot pull requests or `main`'s `composer.lock` changes. What is left is the judgement, and the merge, which is a person's. Take it in this order:

1. Read the tables and the `jsonl` blocks below, one JSON object per line. A package with `fixes` is the reason for this pull request; one without was pulled in by the resolver, and `required_by` says by whom. Look hardest at:
   - `"jump": "major"` and `"minor"`; `"deduplicated"` and `"duplicated"` mean the lockfile now holds fewer or more versions of the package (whoever used the dropped version now gets `to`); `"reference"` is the same version from a newer commit.
   - `patches`: a composer-patches patch (`patches/`) on that package, which has to still apply to the new version. A patch that does not apply fails the workflow, so a pull request that exists has passed that.
   - `requires`: what the new version needs that the old one did not (including the PHP version), or no longer needs.
   - `constraint` and `constraint_was`: the `composer.json` range of a direct dependency, and the one before this pull request.
   - The advisories that remain after the update: they are not covered by Dependabot pull requests (Packagist-only advisories have none) and need a targeted update of their own.
2. Fetch the change between the two versions: `repository` with `from_ref` and `to_ref` (GitHub: `<repository>/compare/<from_ref>...<to_ref>`); clone it and `git diff <from_ref> <to_ref>`, and read the CHANGELOG, UPGRADE files and release notes in between. `composer show <package>` and `composer why <package>` give more.
3. See what we use. `used_in` counts the files per top-level directory (`model`, `web`, `admin`, `symfony`, `tests`, … ) that mention the package's `namespaces`. It is a grep hint, and a missing `tests` entry does not prove a gap. `"no_namespaces": true` marks a package that autoloads only classes or files (for example `setasign/tfpdf`, `dg/mysql-dump`, `dg/ftp-deployment`): it has no `used_in` at all, so search for its class names yourself. Search for the classes, services, config keys and imports the diff touches yourself, across `model/`, `web/`, `admin/` and `symfony/`.
4. Give each package one verdict.
   - `NOT_AFFECTED`: nothing we use has changed.
   - `AFFECTED_AND_COVERED`: we use changed behaviour; name the test that would fail.
   - `AFFECTED_NOT_COVERED`: we use changed behaviour and no test would notice.
5. For every `AFFECTED_NOT_COVERED`, write the missing test first, in a separate pull request off `main`; it must pass on the old versions. Merge that first; the workflow then rebuilds this pull request on the new `main`.
6. `Run tests` on this branch runs only after a person presses "Approve and run workflows" on this pull request (GitHub holds the runs of a pull request that a workflow opened, again after every rebuild); if nothing has run, say so and ask for it. Read its result, which a green run makes necessary but not sufficient: it says nothing about behaviour no test covers. Then report what you found and what you ran; a person merges this pull request. Never merge, approve or rebase a Dependabot pull request itself: Dependabot closes them once the bump is on `main`.
</details>

# Composer audit

The workflow `.github/workflows/composer-audit.yml` runs `composer audit --locked` every day (04:17 UTC), on demand (Actions → "Composer audit" → Run workflow) and on a pull request into `main` that changes the workflow itself. It checks out only `composer.json` and `composer.lock`, fails when the lock holds a package with a known security advisory, and puts the result in the summary of the run. Dev-only packages count too: a vulnerable test or build tool is a problem for whoever runs it. An abandoned package is listed but does not fail the run (`composer audit` fails on one by default; `--abandoned=report`, Composer 2.7 or newer, turns that off). The log starts with the Composer version of the runner.

## Why Dependabot is not enough

Dependabot reads only GitHub's Advisory Database. `composer audit` reads Packagist (FriendsOfPHP/security-advisories and GitHub's own data), which Symfony feeds on the day of a security release. Observed on 2026-11-06: `composer audit` reported 11 advisories that Dependabot did not.

- Ten were published by Symfony on its own repository on 2026-11-04 and were not in GitHub's Advisory Database two days later (the advisories API answered 404, and `advisories?ecosystem=composer&affects=<package>@<version>` returned nothing for our versions). One earlier advisory had needed 12 days to get from Symfony's repository into that database (2026-10-15 → 2026-10-27), and its alert on this repository appeared on 2026-11-03.
- One (`symfony/mime`, CVE-2026-45070) was in GitHub's database with the range "fixed in 7.4.12", while Symfony had widened it to "fixed in 7.4.22". GitHub's copy had not been touched since 2026-05-27, so we were outside GitHub's range and inside Symfony's.

So a quiet Dependabot says nothing about advisories that GitHub has not imported or has not refreshed; this run covers them.

## When the run is red

The log and the summary name the packages, the advisories and the fixed versions. Dependabot will usually not open a pull request for them, so the update is ours:

1. For Symfony the components cannot be updated one by one, because they require each other: `composer update 'symfony/*' --with-dependencies` in the dev image (it changed 41 packages the first time).
2. Sync the committed `vendor/` and run the checks as described in `docs/security-update-brief.md`, "Reproducing a bump locally": `composer install`, a separate commit with `vendor/`, `check-vendor-matches-lock`, `check-dockerignore`.
3. Check that the advisories are gone: `composer audit --locked --abandoned=report` (in the dev image or with a local Composer).

## Notifications

GitHub documents that a failed scheduled run is e-mailed to the user who last changed the cron line of the workflow, subject to that user's notification settings; whoever edits the line later becomes the recipient. A run is always visible in Actions. No issue is opened automatically. GitHub disables scheduled workflows of a public repository after 60 days without activity, and a disabled schedule sends nothing.

## Not covered

`ui/` (npm): GitHub's database is reliable for npm and Dependabot handles it. `composer audit` does not look at npm packages.

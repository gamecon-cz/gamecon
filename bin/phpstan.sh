#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# project root
cd "$(dirname "$DIR")"

set -x

# The dead-code detector builds its call graph from the compiled container, so this has
# to be current, not merely present. Locally the cache survives a branch switch on the
# bind mount, and a stale one reports a new service as dead and a deleted one as alive —
# so warm it every time rather than only when the file is missing.
php bin/console --env=dev cache:warmup --no-optional-warmers

php -d memory_limit=1G vendor/bin/phpstan analyse \
	--configuration phpstan.dist.neon \
	"$@"

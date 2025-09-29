#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# project root
cd "$(dirname "$DIR")"

set -x

# to avoid crash
# [PHPStan\Symfony\XmlContainerNotExistsException]
# Container /src/var/cache/dev/App_KernelDevDebugContainer.xml does not exist
# (note: /src/var/cache/dev/srcApp_KernelDevDebugContainer.xml is legacy path)
if [ ! -f var/cache/dev/App_KernelDevDebugContainer.xml ] && [ ! -f var/cache/dev/srcApp_KernelDevDebugContainer.xml ]; then
  php bin/console --env=dev cache:warmup --no-optional-warmers
fi

php -d memory_limit=1G vendor/bin/phpstan analyse \
	--configuration phpstan.dist.neon \
	"$@"

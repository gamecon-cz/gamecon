#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# project root
cd "$(dirname "$DIR")"

set -x

bin/console --no-interaction lint:yaml --parse-tags config symfony/src symfony/translations
bin/console --no-interaction lint:container
bin/console --no-interaction lint:twig templates
bin/console --no-interaction doctrine:schema:validate --skip-sync

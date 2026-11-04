#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# project root
cd "$(dirname "$DIR")"

# The dev image installs python3 only as a dependency of nodejs; if a rebuild loses it, say so here.
command -v python3 > /dev/null || { echo "python3 is missing in this image: add it to .docker/Dockerfile" >&2; exit 1; }

set -x

python3 -B -I -m unittest discover -s tests/security_update_brief -t tests/security_update_brief

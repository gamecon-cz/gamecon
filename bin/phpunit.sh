#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# project root
cd "$(dirname "$DIR")"

# Jen jeden běh nad jednou testovací databází naráz. Bootstrap (tests/_zavadec.php) na
# konci zabije všechny procesy nad `gamecon_test_%` a ty databáze zahodí, takže druhý
# souběžný běh ten první rozstřílí — naměřeno 175 chyb v běhu, který jinak projde zeleně.
# Vlastní jméno databáze pro každý běh nepomůže, protože úklid je plošný podle prefixu.
#
# Klíčem je jméno compose projektu, ne cesta k checkoutu: worktrees běžně sdílejí jeden
# běžící stack, takže dvě různé cesty můžou mířit do téže databáze.
COMPOSE_PROJECT="$(docker compose config --format json 2>/dev/null | sed -n 's/.*"name": *"\([^"]*\)".*/\1/p' | head -1)"
LOCK="/tmp/gamecon-phpunit-${COMPOSE_PROJECT:-$(pwd | md5sum | cut -c1-12)}.lock"
exec 9>"$LOCK"
if ! flock -w "${GC_PHPUNIT_LOCK_WAIT:-1800}" 9; then
	echo "phpunit.sh: jiný běh drží $LOCK (čekáno ${GC_PHPUNIT_LOCK_WAIT:-1800} s)" >&2
	exit 75
fi

set -x

php -d memory_limit=1G vendor/bin/phpunit \
	"$@"

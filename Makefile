.PHONY: init start-docker-foreground run cache bash phpstan ecs fix static ci tests migrations-run migrations-diff

MAKEFLAGS += --no-print-directory # to disable "make: Entering directory ..." messages

init:
	which docker > /dev/null || (echo "Please install docker binary" && exit 1)
	if command -v direnv &> /dev/null; then \
		[ -f .envrc ] || cp .envrc.dist .envrc; \
		direnv allow; \
	fi
	docker compose up -d
	# has to explicitly use direnv exec to use the freshly allowed .envrc in current prompt instance
	direnv exec bin-docker/composer install
	# install ui deps before building — restores a wiped/incomplete ui/node_modules
	# (e.g. after a hard reset mid-install), otherwise the build fails with "tsc: not found"
	direnv exec bin-docker/yarn install --frozen-lockfile
	direnv exec bin-docker/yarn build
	@make cache
	@echo 'Gamecon initialized ✅'

run: init
	@PORT=$$(docker compose port web 80 2>/dev/null | cut -d: -f2); \
	echo "App runs on http://localhost:$${PORT} http://localhost:$${PORT}/admin"

cache:
	docker compose run --rm --user=root --entrypoint=sh web -c 'find cache -mindepth 2 -maxdepth 2 ! -name ".htaccess" -exec rm -fr {} +'
	docker compose run --rm --user=root --entrypoint=sh web -c 'rm -fr cache/public/program cache/private/program'
	docker compose run --rm --user=root --entrypoint=sh web -c 'find symfony/var -mindepth 1 -maxdepth 1 ! -name ".htaccess" -exec rm -fr {} +'
	mkdir -p symfony/var/log
	touch symfony/var/log/test.log
	touch symfony/var/log/dev.log
	chmod -R 0777 symfony/var
	./bin-docker/php ./bin/console cache:clear --no-optional-warmers

bash:
	./bin-docker/docker-bash

ci: init static tests

tests:
	./bin-docker/docker-bash bin/phpunit.sh

phpstan:
	./bin-docker/docker-bash bin/phpstan.sh

ecs:
	./bin-docker/docker-bash bin/ecs.sh

fix:
	./bin-docker/php vendor/bin/rector process --config rector-ci.php
	./bin-docker/docker-bash bin/ecs.sh --fix

static: fix phpstan

migrations-run:
	./bin-docker/php ./bin/console migrations:continue
	# invalidate Doctrine caches so the app picks up the migrated schema
	# (app:cache:doctrine:invalidate never existed — was a dangling reference)
	./bin-docker/php ./bin/console doctrine:cache:clear-metadata
	./bin-docker/php ./bin/console doctrine:cache:clear-result

migrations-diff:
	./bin-docker/php ./bin/console --env=test migrations:continue
	./bin-docker/php ./bin/console --env=test migrations:create structures rename-me
	@find ./symfony/migrations/structures -type f -name '*-rename-me.sql' -empty -exec echo NO CHANGES, removing empty {} \;
	@find ./symfony/migrations/structures -type f -name '*-rename-me.sql' -empty -exec rm {} \;

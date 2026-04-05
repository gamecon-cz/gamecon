.PHONY: init start-docker-foreground run cache bash phpstan ecs fix static ci tests migrations-run migrations-diff ui-build yarn

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
	@make yarn
	@make cache
	@echo 'Gamecon initialized ✅'

yarn:
	yarn --cwd=ui install --frozen-lockfile && yarn --cwd=ui build

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

tests: phpunit

phpunit:
	./bin-docker/docker-bash bin/phpunit.sh

phpstan:
	./bin-docker/docker-bash bin/phpstan.sh

ecs:
	./bin-docker/docker-bash bin/ecs.sh

fix:
	./bin-docker/php vendor/bin/rector process --config rector-ci.php
	./bin-docker/docker-bash bin/ecs.sh --fix

static: fix phpstan symfony-lint doctrine-lint composer-lint

symfony-lint:
	./bin-docker/docker-bash bin/symfony-lint.sh

doctrine-lint:
	./bin-docker/docker-bash bin/doctrine-lint.sh

composer-lint:
	./bin-docker/composer validate --no-check-lock

migrations-run:
	./bin-docker/php ./bin/console migrations:continue
	./bin-docker/php ./bin/console app:cache:doctrine:invalidate

migrations-diff:
	./bin-docker/php ./bin/console --env=test migrations:reset
	./bin-docker/php ./bin/console --env=test migrations:create structures rename-me
	@find ./symfony/migrations/structures -type f -name '*-rename-me.sql' -empty -exec echo NO CHANGES, removing empty {} \;
	@find ./symfony/migrations/structures -type f -name '*-rename-me.sql' -empty -exec rm {} \;

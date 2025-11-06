.PHONY: init start-docker-foreground run bash phpstan ecs fix static ci tests

MAKEFLAGS += --no-print-directory # to disable "make: Entering directory ..." messages

ifndef APP_ENV
	# for Symfony
	APP_ENV = dev
endif

init:
	which docker > /dev/null || (echo "Please install docker binary" && exit 1)
	if command -v direnv &> /dev/null; then \
		cp --update=none .envrc.dist .envrc; \
		direnv allow; \
	fi
	docker compose up -d
	# has to explicitly use direnv exec to use the freshly allowed .envrc in current prompt instance
	direnv exec bin-docker/composer install
	@echo 'Gamecon initialized ✅'

start-docker-foreground:
	docker compose up

run: init start-docker-foreground

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
	./bin-docker/docker-bash bin/ecs.sh --fix

static: fix phpstan

migrations-diff:
	./bin-docker/php ./bin/console --env=test migrations:continue
	./bin-docker/php ./bin/console --env=test migrations:create structures rename-me
	@find ./symfony/migrations/structures -type f -name '*-rename-me.sql' -empty -exec echo NO CHANGES, removing empty {} \;
	@find ./symfony/migrations/structures -type f -name '*-rename-me.sql' -empty -exec rm {} \;

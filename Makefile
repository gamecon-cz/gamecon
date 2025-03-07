ifndef APP_ENV
	# for Symfony
	APP_ENV = dev
endif

init:
	cp --no-clobber .envrc.dist .envrc
	direnv allow
	docker compose up -d
	# has to explicitly use direnv exec to use the freshly allowed .envrc in current prompt instance
	direnv exec bin-docker/composer install
	echo 'Gamecon initialized ✅'

start-docker-foreground:
	docker compose up

run: init start-docker-foreground

bash:
	./bin-docker/docker-bash

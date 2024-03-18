ifndef APP_ENV
	# for Symfony
	APP_ENV = dev
endif

init:
	cp --no-clobber .envrc.dist .envrc
	direnv allow
	docker compose up -d
	composer install
	echo 'Gamecon initialized ✅'

bare-phpunit:
	cp --no-clobber phpunit.xml.dist phpunit.xml
	bin/phpunit-local.sh --stop-on-error --testdox

start-docker-foreground:
	docker compose up

run: init start-docker-foreground

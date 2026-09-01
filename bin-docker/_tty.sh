# Where docker-compose.yml mounts the sources inside the container. The image
# bakes the same path in (APACHE_DOCUMENT_ROOT and WORKDIR in .docker/Dockerfile,
# DocumentRoot in the apache config), so it does not depend on the host directory
# name — and must not. Deriving it from the project basename broke every git
# worktree whose directory is not named "gamecon": the wrappers then failed with
# "chdir to cwd ... failed".
GAMECON_CONTAINER_DIR="/var/www/html/gamecon"

if [ -t 0 ] && [ -t 1 ]; then
	DC_INTERACTIVITY=""
else
	DC_INTERACTIVITY="-T"
fi

function docker_run {
	if [ -t 0 ] && [ -t 1 ]; then
		docker run --rm --interactive --tty=true "$@"
	else
		docker run --rm --interactive --tty=false "$@"
	fi
}

function docker_compose_run {
  docker compose up -d
	docker compose exec $DC_INTERACTIVITY "$@"
	docker compose stop
}

function docker_compose_exec {
	docker compose exec $DC_INTERACTIVITY "$@"
}

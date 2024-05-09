# -t is for testing if stdin (file descriptor 0) and stdout (file descriptor 1) are a terminal
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
	docker compose run --rm $DC_INTERACTIVITY "$@"
}

function docker_compose_exec {
	docker compose exec $DC_INTERACTIVITY "$@"
}

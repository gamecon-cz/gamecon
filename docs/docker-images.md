# Docker images

Every Docker environment of gamecon starts from an image published on Docker Hub as `gameconcz/gamecon`. They are built
from `.docker/Dockerfile` by `.docker/docker-build.sh`.

| Image | What it is | Used by |
|---|---|---|
| `gameconcz/gamecon:8.3` | PHP 8.3, Apache, the extensions and the development tools (xdebug, git, Node, ...) | `docker-compose.yml`, CI, the build stage of `Dockerfile.preview` |
| `gameconcz/gamecon:8.3-runtime` | the same without the development tools | the final stage of `Dockerfile.preview`, `Dockerfile.archive` |
| `gameconcz/gamecon:8.2` | the recipe of `8.3` on PHP 8.2 | the archives of 2022-2025 (`deploy-year-archive.yml`), previews of branches that still say `FROM gameconcz/gamecon:8.2` |
| `gameconcz/gamecon:8.2-runtime` | the same without the development tools | nothing, it is built together with `8.2` |

## When to rebuild

- `.docker/Dockerfile` changed (extensions, system tools, Node).
- The environments should move to a newer release of the official `php:<version>-apache` image. Build only after that
  tag exists on Docker Hub.

## How to rebuild

1. Build without publishing, under a name nobody else pulls:

   ```bash
   GAMECON_IMAGE_REPOSITORY=gamecon-local-test GAMECON_IMAGE_OUTPUT=--load .docker/docker-build.sh 8.3 8.2
   ```

   Check that `docker run --rm --entrypoint php gamecon-local-test:8.3 -v` (and `8.2`) shows the expected release. To run
   the checks on it, point the `web` service at it in the git-ignored `docker-compose.override.yml`
   (`image: gamecon-local-test:8.3`, `pull_policy: never`) and run `make check-ui-bundles` (the Node of the image can move)
   and `make tests-be`.

   Check an archive too, before step 2 replaces the published `8.2`, because a GET is not enough: the home page does not
   boot the Symfony kernel. Pull the published `gameconcz/gamecon:8.2` and, from the archive branch, build the archive
   image twice with `docker build -f Dockerfile.archive` (its `FROM` pointing at the published image and at
   `gamecon-local-test:8.2`). Start both against a copy of the year's database and compare `GET /prihlaseni` and a
   `POST` of the login form to it.

2. Publish the images that passed. This overwrites the public tags, so it needs push rights to the `gameconcz`
   organisation:

   ```bash
   for tag in 8.3 8.3-runtime 8.2 8.2-runtime; do
       docker tag gamecon-local-test:$tag gameconcz/gamecon:$tag
       docker push gameconcz/gamecon:$tag
   done
   ```

   (`.docker/docker-build.sh 8.3 8.2` without the two variables builds and pushes in one go, when there is nothing to
   check first.)

3. Deploy the previews and the archives again. They are built at deploy time, so they use a rebuilt base image once their
   branch is deployed again.

   The admin page Previews (`/admin/web/previews`) lists the previews. The slug in a preview's address is its branch name
   lowercased, transliterated and cut to 30 characters, so match each one to its branch and run, for every preview that is
   still needed:

   ```bash
   gh workflow run deploy-preview.yml --ref main -f branch=<branch>
   ```

   A preview whose pull request is merged or whose branch is deleted is a leftover; remove it rather than building it
   again: `gh workflow run teardown-preview.yml --ref main -f branch=<branch>`.

   The archives of 2022-2025:

   ```bash
   gh workflow run deploy-year-archive.yml --ref main -f branch=archive/2025
   ```

   (and the same for `archive/2024`, `archive/2023` and `archive/2022`). Both workflows run from `main` and build the
   branch they are given.

   The server runs deploys one at a time, and a queued one gives up after 30 minutes, so queue about five at a time and
   look at `gh run list --workflow <file>` for failures before the next batch. A failed run is queued again the same way.

4. Tell the developers to run `docker compose pull`: the tags moved.

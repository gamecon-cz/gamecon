# Archive 2010 — static reconstruction

`2010.gamecon.cz` is a **static archive**, like `2011.gamecon.cz` and unlike the
later year-archives (2012–2025), which run their original PHP against their
original database.

## Why static

The live 2010 site was DB-driven (the same `dbgacon`-era stack as 2011) with
pretty URLs rewritten into a front controller. Both the **database and the live
router are gone** — they predate the `d16779_*` backup era and were never
captured in any backup we can reach (host `/srv` tree, Google Drive, host
MariaDB). The current production DB holds `rok=2010` rows but in the *modern*
schema, incompatible with the 2010 code.

Note there was never a `2010.gamecon.cz` subdomain in 2010 — the site lived at
`gamecon.cz`. This archive *adopts* the per-year subdomain convention (as 2011
does) to serve the frozen 2010 snapshot at a stable address.

## What this archive is

A faithful **static snapshot** of the 2010 site as the Internet Archive captured
it during the 2010 site's lifetime (the **2010-01 … 2011-04** window, before the
2011 site took over the same URLs):

- **~223 content pages** under `rpg/`, `prednasky/`, `deskove-hry/`,
  `organizatori/`, `larp/`, `wargaming/`, `mistrovstvi-v-drd/`, `gamecon/`,
  `gamecon-trojboj/`, `galerie-materialy/`, `dilny/`, `bonusy/`,
  `bonusove-hry/`, `novinky/` plus root-level pages — the captured 2010 news
  feed, program, organizer profiles, copy and menu. Restored from
  web.archive.org (raw `id_` captures, one per distinct URL in the window),
  cleaned of the Google Analytics tracker.
- `system_styly/`, `lightbox/` — the theme assets. 2010 shares the same base
  `system_styly/styl1.css` + `pics_system_styl1` theme as 2011 (the difference
  is the `program-gc-2010.gif` banner vs 2011's `…-2011.gif`), so the assets are
  copied from the 2011 archive tree, which is a superset of what the 2010 pages
  reference.
- `.htaccess` — maps each original pretty URL onto its static file. Unmapped
  URLs return a themed `404.html` ("Stránka nebyla archivována") instead of
  silently rendering the News home page, so genuinely uncaptured links read as
  honestly dead.
- `404.html` — themed 404 carrying the authentic 2010 chrome/menu.

### Known gaps

A handful of sidebar thumbnails the captured HTML references
(`system_styly/side/podraz.gif`, `…/spina.jpg`,
`…/tvorite-rolove-hry-pomozeme-vam-ich-obohatit-.gif`) were never present under
those exact names on the live site or in any host tree, so they 404 (broken
sidebar image icon). The pages themselves render fine. This mirrors the
equivalent gap in the 2011 archive.

A few captured `organizatori/<name> o sobě` / `<name> - herní profil` URLs are
**not real pages** — they are the placeholder `href` values of the JavaScript
"O sobě píše" / "Herní profil" toggle links, which the Internet Archive recorded
as if they were URLs. They are intentionally not mapped.

Dynamic features (login, registration, forum, search) are inert — there is no
backend or database. That is the nature of a static archive of a lost dynamic
site.

## Deploy

Built and deployed exactly like the other year-archives, via
`.github/workflows/deploy-year-archive.yml` (on `main`) dispatched by this
branch's `archive-push-redeploy.yml` on push. The host's
`deploy-year-archive.sh` (ansible `year_archive_deployer` role) starts the
`gamecon-archive-2010` container and writes its Caddy vhost. 2010 builds on the
period-correct `php:5.6-apache` base with no PHP extensions (static, like 2011).

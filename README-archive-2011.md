# Archive 2011 — static reconstruction

`2011.gamecon.cz` is a **static archive**, unlike the other year-archives
(2012–2025), which run their original PHP against their original database.

## Why static

The live 2011 site was DB-driven (database `dbgacon` on the old
`sql.gamecon.cz` server) with pretty URLs rewritten into a front controller.
Both are **lost**:

- **The database is gone.** `dbgacon` predates the `d16779_*` era and was never
  captured in any backup we can reach (Google Drive, Wedos FTP, the host's 2011
  tree, the host MariaDB). The current production DB holds `rok=2011` rows but
  in the *modern* schema — incompatible with the 2011 code.
- **The live router is gone.** The host's 2011 tree has the theme
  (`system_styly/`), the photo galleries, the `akce/` program backend, and three
  *development* forks (`_ver1/2/3`), but no root `index.php` and no root
  `.htaccess` — the glue that served the live site was deleted. The dev forks
  use a thinner menu and `/verN/` asset paths, so they are **not** what was live
  in late 2011.

## What this archive is

A faithful **static snapshot** of the site as the Internet Archive captured it
on **2011-12-31**, served as flat HTML:

- `index.html` plus **174 content pages** under `gamecon/`, `organizatori/`,
  `rpg/`, `deskove-hry/`, `prednasky/`, `larp/`, `mistrovstvi-v-drd/`,
  `wargaming/`, `galerie-materialy/`, … — the captured pages (real 2011 news
  feed, program, organizer profiles, copy and menu), cleaned of the Google
  Analytics tracker. Four were hand-cleaned in the initial reconstruction; the
  rest were bulk-restored from the Internet Archive (raw `id_` captures, latest
  2011 snapshot per URL). Captures that were themselves the News front page
  (URLs the 2011 site already served News for) were dropped so they 404
  honestly instead of misleadingly showing News.
- `system_styly/`, `lightbox/` — the **real** theme assets, copied from the
  host's 2011 tree (the captured HTML already used root-relative paths, so they
  resolve directly).
- **Photo galleries (`/galerie/`)** — the original ~45 MB of photos
  (`fotogalerie-fotosoutez2011`, the `fotogalerie-gamecon08/09/10` event
  galleries, the `materialy-*` promo materials, …) survive in the host's
  bare-metal 2011 tree but are too large to bake into the image. They are
  **bind-mounted read-only** from the host at container start
  (`/srv/.../2011/galerie` → `/var/www/html/gamecon/galerie`), wired up in the
  `year_archive_deployer` role's `deploy-year-archive.sh` (ansible repo). The
  Internet Archive never captured these galleries, so the host tree is the only
  source. `galerie/materialy-2010/gc-pf-2011-2.jpg` (the homepage PF image) is
  also baked into the image as a fallback; the host mount is a superset and does
  not hide it.
- **Downloads (`/download/`)** — the original ~79 MB of PDFs/JPGs (DrD newspapers
  `noviny/sluncesvit-*.pdf`, the císařství/vinný-kraj maps, `charaktery-2011.*`
  character sheets, and the `starsi-dobrodruzstvi/GC0*_*.pdf` older adventures)
  also survive in the host's bare-metal 2011 tree but are too large to bake into
  the image. Like the galleries they are **bind-mounted read-only** from the host
  at container start (`/srv/.../2011/download` → `/var/www/html/gamecon/download`),
  wired up in the same `year_archive_deployer` role's `deploy-year-archive.sh`
  (ansible repo). The Internet Archive never captured these binaries either, so
  the host tree is the only source. Until the mount is in place these links 404;
  the files themselves are **not lost**.
- `.htaccess` — maps each original pretty URL onto its static file. Unmapped
  URLs return a themed `404.html` ("Stránka nebyla archivována") instead of
  silently rendering the News home page, so genuinely uncaptured links read as
  honestly dead rather than misleading.

Dynamic features (login, registration, forum, search) are inert — there is no
backend or database. That is the nature of a static archive of a lost dynamic
site.

## `db/` — reconstructed schema (artifact, not used at runtime)

- `db/dbgacon-reconstructed-schema.sql` — reverse-engineers the `dbgacon`
  schema (16 tables) from the SQL in the surviving `_ver3` dev fork.
- `db/dbgacon-reconstructed-seed.sql` — a minimal hand-written seed (menu tree
  + a few core pages, backfilled from the developers' page-copy doc).
- `db/dbgacon-wayback-seed.sql` — a **full** seed: the same 232 Internet Archive
  captures that this static archive serves, transformed into the schema's
  tables (`stranky_obsah`/`menu_seznam`/`stranky_meta`/`stranky_fulltext`). So
  the dynamic path carries identical *content* to the static archive — it adds
  no new pages, only a different (DB + PHP 5.6) serving mechanism plus a working
  search box.

These are **not** loaded by this static archive. They are preserved because the
dynamic path was built and validated end-to-end (the `_ver3` engine on PHP 5.6,
served at the domain root with the live root theme, rendering all 232 pages from
this seed). The **static archive was chosen as the deployed artifact** — same
content, zero moving parts, nothing to break — so this SQL is documentation /
a head-start for anyone who later wants the dynamic version instead.

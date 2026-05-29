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

- `index.html`, `gamecon/o-gameconu.html`, `organizatori/organizacni-tym.html`,
  `kontakt.html` — the captured pages (real 2011 news feed, copy and menu),
  cleaned of the Google Analytics tracker and dead third-party banners.
- `system_styly/`, `lightbox/`, `galerie/materialy-2010/` — the **real** theme
  and images, copied from the host's 2011 tree (the captured HTML already used
  root-relative paths, so they resolve directly).
- `.htaccess` — maps the original pretty URLs onto the static files and renders
  the home page for any unresolved URL (mirroring the original behaviour, whose
  404 branch was disabled).

Dynamic features (login, registration, forum, search) are inert — there is no
backend or database. That is the nature of a static archive of a lost dynamic
site.

## `db/` — reconstructed schema (artifact, not used at runtime)

`db/dbgacon-reconstructed-schema.sql` + `db/dbgacon-reconstructed-seed.sql`
reverse-engineer the `dbgacon` schema (16 tables) from the SQL in the surviving
`_ver3` dev fork, with seed data backfilled from the developers' page-copy doc.
They are **not** loaded by this static archive — they are preserved so that, if
anyone later wants to stand up a *dynamic* 2011 site from the `_ver3` fork, the
schema work is already done.

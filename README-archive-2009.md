# Archive 2009 — static reconstruction

`2009.gamecon.cz` is a **static archive**, like `2010.gamecon.cz` /
`2011.gamecon.cz` and unlike the later year-archives (2012–2025), which run
their original PHP against their original database.

GameCon 2009 was held **16.–19. 7. 2009** on the **University of Pardubice**
campus (the festival moved to Česká Třebová only in later years).

## Why static

The live 2009 site was DB-driven (the same `dbgacon`-era stack as 2010/2011)
with pretty URLs rewritten into a front controller. Both the **database and the
live router are gone** — they predate the `d16779_*` backup era and were never
captured in any backup we can reach (host `/srv` tree, Google Drive, host
MariaDB).

Note there was never a `2009.gamecon.cz` subdomain in 2009 — the site lived at
`gamecon.cz`. This archive *adopts* the per-year subdomain convention (as 2010
and 2011 do) to serve the frozen 2009 snapshot at a stable address.

## What this archive is

A faithful **static snapshot** of the 2009 site as the Internet Archive
captured it during the 2009 site's lifetime (the post-festival **2009-07 …
2010-04** window, before the 2010 site took over the same URLs):

- **~153 content pages** under `rpg/`, `prednasky/`, `deskove-hry/`,
  `organizatori/`, `larp/`, `wargaming/`, `mistrovstvi-v-drd/`, `dilny/`,
  `gamecon/`, `gamecon-trojboj/`, `galerie-materialy/`, plus root-level pages —
  the captured 2009 news feed, program, organizer profiles, copy and menu.
  Restored from web.archive.org (raw `id_` captures, latest per distinct URL in
  the window), cleaned of the Google Analytics tracker, with absolute
  `gamecon.cz` links relativized so navigation stays inside the archive.
- `system_styly/`, `lightbox/` — the theme assets. 2009 uses the same base
  `system_styly/styl1.css` + `pics_system_styl1` theme as 2010/2011, so the
  assets are copied from the 2011 archive tree, which is a superset of what the
  2009 pages reference.
- `.htaccess` — maps each original pretty URL onto its static file. Unmapped
  URLs return a themed `404.html` ("Stránka nebyla archivována") instead of
  silently rendering the News home page, so genuinely uncaptured links read as
  honestly dead. `/forum` is mapped before the `-d` short-circuit (it is also a
  real—if empty—directory, which would otherwise 403).
- `404.html` — themed 404 carrying the authentic 2009 chrome/menu.

### Logo

The Internet Archive never captured a genuine 2009 masthead logo (the only
captured `pics_system_styl1/logo.gif` is from Dec 2011 and shows the 2012
wordmark). The logo here is **reconstructed**: the authentic GameCon
wordmark + dice graphic with the correct 2009 date/place line composited on
(`16. – 19. 7. 2009   Pardubice`). It is accurate in content, not a
pixel-for-pixel period asset.

### Known gaps

- The "O sobě píše" / "Herní profil" toggles on the organizer pages were driven
  by a `ukaz()` function in the lost `/java.js`. `java.js` is reimplemented
  inline on the 9 pages that use it (toggle `display` of `#objektN`); the dead
  `<script src="/java.js">` tag is stripped from every page. The toggle links'
  bogus placeholder hrefs (`href="<name> o sobě"`) are set to `href="#"`.
- A few captured `organizatori/<name> o sobě` / `<name> - herní profil` URLs are
  **not real pages** — they are those placeholder toggle hrefs the Internet
  Archive recorded as if they were URLs. They are intentionally not mapped.
- Gallery photos (`/galerie/**/*.jpg`), most download PDFs
  (`/download/starsi-dobrodruzstvi/*.pdf`), forum threads (`/forum/...`) and
  dynamic `.php` endpoints were never captured by the Internet Archive → they
  404. The one referenced PDF that *did* survive
  (`galerie/materialy/gamecon-brozura.pdf`) is included.
- Dynamic features (login, registration, forum, search) are inert — there is no
  backend or database. That is the nature of a static archive of a lost dynamic
  site.

## Deploy

Built and deployed exactly like the other year-archives, via
`.github/workflows/deploy-year-archive.yml` (on `main`) dispatched by this
branch's `archive-push-redeploy.yml` on push. The host's
`deploy-year-archive.sh` (ansible `year_archive_deployer` role) starts the
`gamecon-archive-2009` container and writes its Caddy vhost. 2009 builds on the
period-correct `php:5.6-apache` base with no PHP extensions (static, like
2010/2011).

Reconstruction process (for 2008 and earlier): see
`docs/generated/archiv-rekonstrukce-z-wayback.md` in the main branch.

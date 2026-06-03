# Archive 2004 — static reconstruction (Altar publisher-site era)

`2004.gamecon.cz` is a **static archive**, like 2005–2011 and unlike the later
year-archives (2012–2025) which run their original PHP against their database.

GameCon 2004 was held **8.–11. 7. 2004 in OLOMOUC** (DDM, tř. 17. listopadu 47,
plus nearby university dorms). It was organised by the **Altar** publishing
house.

## Why static — the `altar.cz/gamecon/` path era (same as 2005)

Like 2005 (and unlike the 2006–2008 `gamecon.altar.cz` subdomain era, and the
2009+ `gamecon.cz` era), in 2004 GameCon was **a section of the whole `altar.cz`
publisher website**, living under the **path `altar.cz/gamecon/`** and sharing
Altar's global site navigation:

- **Source path** `altar.cz/gamecon/`. Pages are served here at the **root**
  (`/gamecon/X.html` → `/X.html`); the in-page `/gamecon/` link prefix was
  rewritten to `/`.
- **Windows-1250 (cp1250) encoding** → transcoded to UTF-8, with an injected
  `<meta charset="utf-8">` (the source declared charset only via the HTTP
  header). NB: cp1250, **not** ISO-8859-2 — Latin-2 silently drops `š`/`ž`
  (bytes 0x9a/0x9e); byte-verified before fetching.
- **Embedded in the publisher site**: the global nav links all over the parent
  `altar.cz` site (`/altar/*`, `/drd/`, `/kontakt/`, `/cgi/*`, …).
  **Scope: GameCon pages + the publisher-site nav menu** (section landing pages
  + one level deeper), fetched from the **2004 capture window** so the publisher
  content matches the year. Dynamic CGI (`/cgi/search.cgi`, `/cgi/doc/*`), the
  catalog, and deeper links fall to the themed 404. The two still-live external
  game-world subdomains (`asterion.altar.cz`, `proroctvi.altar.cz`) keep their
  links (upgraded to https); `taria`/`risesnu` are dead and left as-is.

There was never a `2004.gamecon.cz` subdomain in 2004; this archive adopts the
per-year convention to serve the frozen snapshot at a stable address.

## What this archive is

A faithful **static snapshot** of the GameCon 2004 section as the Internet
Archive captured `altar.cz/gamecon/` in the 2004 window (Apr–Dec 2004, latest
capture per URL before the 2005 rollover; the saved homepage is the
post-festival state, "…GameCon **konal** 8.–11. července … v Olomouci"):

- **31 pages** — homepage (info + registration + payment), aktuality, anotace,
  družiny, mistrovství (DrD), přihláška/přihlášení/přihláška2, program, tabulka,
  the tournament pages (`turnaj_arena`, `_battletech`, `_carcassonne`, `_magic`,
  `_osadnici`, `_proroctvi`, `_risesnu`, `_talisman`, `_wh_fig`, `turnaje`,
  `turnaj_ostatni`), and the **Mistrovství ČR v DrD results archive
  `vysledky95`…`vysledky04`** (1995–2004). Restored from web.archive.org
  (`id_` raw captures), transcoded cp1250 → UTF-8.
- **Theme** — `stylesheets/default.css` (Altar's global stylesheet) +
  `img/gamecon/*` (map, tournament diplomas), kept at their absolute paths.
- `.htaccess` — serves the flat root files directly; cross-site Altar links and
  any uncaptured URL → themed 404.
- `404.html` — themed 404 carrying the authentic 2004 Altar chrome.

### Known gaps

- **Cross-site Altar publisher pages** (`/altar/*`, `/drd/`, `/kontakt/`,
  `/cgi/*`, the shop, etc.) are out of scope and 404 — they were the surrounding
  publisher site, not GameCon content.
- The `logo1998.jpg` masthead image (referenced inside an HTML comment, as in
  2005) was never captured by Wayback — invisible, since the page identity is
  the text `<h1>`.

## Deploy

Built and deployed exactly like the other year-archives, via
`.github/workflows/deploy-year-archive.yml` (on `main`) dispatched by this
branch's `archive-push-redeploy.yml` on push. The host's `deploy-year-archive.sh`
(ansible `year_archive_deployer` role) starts the `gamecon-archive-2004`
container and writes its Caddy vhost. 2004 builds on `php:5.6-apache` with no
PHP extensions (static, like 2005–2011).

Reconstruction process: see `docs/generated/archiv-rekonstrukce-z-wayback.md`
in the main branch (the `altar.cz/gamecon/` path era; cp1250 encoding —
landmines #6 window-rollover and #7 charset-only-in-header both apply).

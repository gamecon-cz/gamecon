# Archive 2005 — static reconstruction (Altar publisher-site era)

`2005.gamecon.cz` is a **static archive**, like 2006–2011 and unlike the later
year-archives (2012–2025) which run their original PHP against their database.

GameCon 2005 was held **7.–10. 7. 2005 in OLOMOUC** (DDM, tř. 17. listopadu 47,
plus nearby university dorms). It was still organised by the **Altar** publishing
house.

## Why static — and the oldest, messiest source layout so far

2005 predates even the `gamecon.altar.cz` subdomain (2006–2008). In 2005 GameCon
was **a section of the whole `altar.cz` publisher website**, living under the
**path `altar.cz/gamecon/`** and sharing Altar's global site navigation. So this
is a *third* source generation, older than both the `gamecon.cz` era (2009+) and
the `gamecon.altar.cz` era (2006–2008):

- **Source path** `altar.cz/gamecon/` (not a subdomain). Pages are served here at
  the **root** (`/gamecon/X.html` → `/X.html`), and the in-page `/gamecon/` link
  prefix was rewritten to `/` so internal navigation works.
- **ISO-8859-2 (Latin-2) encoding** → transcoded to UTF-8. (Note: the late-2005
  captures had already rolled over to advertise 2006 — the fetch window stops at
  2005-08-31 to capture the genuine 2005 state; see landmine #6 in the playbook.)
- **Embedded in the publisher site**: the pages' global nav links all over the
  parent `altar.cz` site (`/altar/*`, `/drd/`, `/kontakt/`, `/cgi/*`, the shop,
  …). **Scope decision: only the GameCon pages were reconstructed** — those
  cross-site links are intentionally left to fall to the themed 404.

There was never a `2005.gamecon.cz` subdomain in 2005; this archive adopts the
per-year convention to serve the frozen snapshot at a stable address.

## What this archive is

A faithful **static snapshot** of the GameCon 2005 section as the Internet
Archive captured `altar.cz/gamecon/` in the genuine 2005 window (Jan–Aug 2005,
latest capture per URL before the autumn 2006 rollover):

- **29 pages** — homepage (info + registration + payment), aktuality, anotace,
  družiny, mistrovství (DrD), přihláška/přihlášení, tabulka, the tournament pages
  (`turnaj_arena`, `_battletech`, `_carcassonne`, `_magic`, `_osadnici`,
  `_proroctvi`, `_risesnu`, `_talisman`, `_wh_fig`, `turnaje`, `turnaj_ostatni`),
  and the **Mistrovství ČR v DrD results archive `vysledky95`…`vysledky04`**
  (1995–2004 championship results). Restored from web.archive.org (`id_` raw
  captures), transcoded to UTF-8.
- **Theme** — `stylesheets/default.css` (Altar's global stylesheet) +
  `img/gamecon/*` (map, tournament diplomas), kept at their absolute paths.
- `.htaccess` — serves the flat root files directly; cross-site Altar links and
  any uncaptured URL → themed 404.
- `404.html` — themed 404 carrying the authentic 2005 Altar chrome.

### Known gaps

- **The `logo1998.jpg` masthead image was never captured** by Wayback — but on
  the live 2005 page that `<img>` was already **commented out** in the HTML, so
  its absence is invisible (the page identity is the text `<h1>GameCon …</h1>`).
- **Cross-site Altar publisher pages** (`/altar/*`, `/drd/`, `/kontakt/`,
  `/cgi/*`, the shop, etc.) are out of scope and 404 — they were the surrounding
  publisher site, not GameCon content.
- A handful of decorative shared `/img/` chrome the global CSS/nav expects may be
  missing; the layout renders on `default.css` with its fallbacks.

## Deploy

Built and deployed exactly like the other year-archives, via
`.github/workflows/deploy-year-archive.yml` (on `main`) dispatched by this
branch's `archive-push-redeploy.yml` on push. The host's `deploy-year-archive.sh`
(ansible `year_archive_deployer` role) starts the `gamecon-archive-2005`
container and writes its Caddy vhost. 2005 builds on `php:5.6-apache` with no
PHP extensions (static, like 2006–2011).

Reconstruction process: see `docs/generated/archiv-rekonstrukce-z-wayback.md`
in the main branch. **2005 extends the Altar-era path further**: the source is
the `altar.cz/gamecon/` PATH (publisher-site section), older than the
`gamecon.altar.cz` subdomain — and landmine #6 (CDX-window rollover) bit hard
here, so the window is capped at 2005-08-31.

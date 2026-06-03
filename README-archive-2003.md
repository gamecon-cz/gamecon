# Archive 2003 — static reconstruction (Altar publisher-site era)

`2003.gamecon.cz` is a **static archive**, like 2004–2011 and unlike the later
year-archives (2012–2025) which run their original PHP against their database.

GameCon 2003 was held **10.–13. 7. 2003 in OLOMOUC** (DDM, tř. 17. listopadu,
plus nearby university dorms). It was organised by the **Altar** publishing
house.

## Why static — the `altar.cz/gamecon/` path era (same as 2004/2005)

Like 2004 and 2005 (and unlike the 2006–2008 `gamecon.altar.cz` subdomain era,
and the 2009+ `gamecon.cz` era), in 2003 GameCon was **a section of the whole
`altar.cz` publisher website**, living under the **path `altar.cz/gamecon/`**
and sharing Altar's global site navigation:

- **Source path** `altar.cz/gamecon/`. Pages are served here at the **root**
  (`/gamecon/X.html` → `/X.html`); the in-page `/gamecon/` link prefix was
  rewritten to `/`.
- **Windows-1250 (cp1250) encoding** → transcoded to UTF-8, with an injected
  `<meta charset="utf-8">` (the source declared charset only via the HTTP
  header). NB: cp1250, **not** ISO-8859-2 — Latin-2 silently drops `š`/`ž`
  (bytes 0x9a/0x9e); byte-verified.
- **Embedded in the publisher site**: the global nav links all over the parent
  `altar.cz` site (`/altar/*`, `/drd/`, `/kontakt/`, `/cgi/*`, the shop, …).
  **Scope: only GameCon pages were reconstructed** — cross-site links fall to
  the themed 404.

There was never a `2003.gamecon.cz` subdomain in 2003; this archive adopts the
per-year convention to serve the frozen snapshot at a stable address.

## What this archive is

A faithful **static snapshot** of the GameCon 2003 section as the Internet
Archive captured `altar.cz/gamecon/`.

### Epoch switcher (Před / Po)

A static archive has no DB, so it can't answer the operational question a
current organiser asks ("how did the year *end* — final numbers, results").
To serve both that and the "what was *planned*" view, the homepage exists in
**two captured states**, toggled by a sticky clock pill (bottom-right) on every
page:

- **`index.html` = Po** (default) — the **post-festival** state (Aug 2003:
  "Ztráty a nálezy z GameConu 2003…", retrospective, teases the 2004 jubilee).
- **`index-pred.html` = Před** — the **pre-festival** state (June 2003: "…bude
  **10. až 13. 7. v Olomouci**…", full program, pricing, registration).

The chosen epoch lives in `?epoch=pred|po` and is remembered across navigation.
Only the homepage differs by epoch in 2003; inner pages were epoch-invariant
(single capture) and carry `data-epoch-pred="none"` so the mode persists without
trying to swap a non-existent variant. Widget assets: `gc-archive/epoch-switch.{js,css}`.

The post-festival `index.html` was captured Aug 2003; the pre-festival
`index-pred.html` June 2003:

- **27 pages** — homepage (info + registration + payment), anketa/anketa2,
  anotace, družiny, mistrovství (DrD), přednášky, přihláška/přihláška2, program,
  tabulka, the tournament pages (`turnaj_arena`, `_battletech`, `_magic`,
  `_proroctvi`, `_talisman`, `_wh_fig`, `turnaj_ostatni`), and the **Mistrovství
  ČR v DrD results archive `vysledky95`…`vysledky03`** (1995–2003). Restored
  from web.archive.org (`id_` raw captures), transcoded cp1250 → UTF-8.
- **Theme** — `stylesheets/default.css` (Altar's global stylesheet) +
  `img/gamecon/*` (map, tournament diplomas), kept at their absolute paths.
- `.htaccess` — serves the flat root files directly; cross-site Altar links and
  any uncaptured URL → themed 404.
- `404.html` — themed 404 carrying the authentic 2003 Altar chrome.

### Known gaps

- **Cross-site Altar publisher pages** (`/altar/*`, `/drd/`, `/kontakt/`,
  `/cgi/*`, the shop, etc.) are out of scope and 404 — they were the surrounding
  publisher site, not GameCon content.
- **Several award-diploma thumbnails** on the `vysledky*` results pages
  (`drd.jpg`, `arena_single/double.jpg`, `talisman_*.jpg`, `bt_3D/special.jpg`,
  `bankovka.jpg`, `arena_melee.jpg`) and the `logo1998.jpg` masthead image were
  **never captured by Wayback** in any era — they render as broken images; the
  pages remain fully readable. The diplomas that *were* captured
  (`arena_mistrovstvi`, `armady`, `bt_mistrovstvi/standard/warrior`,
  `proroctvi_standard`) are included.

## Deploy

Built and deployed exactly like the other year-archives, via
`.github/workflows/deploy-year-archive.yml` (on `main`) dispatched by this
branch's `archive-push-redeploy.yml` on push. The host's `deploy-year-archive.sh`
(ansible `year_archive_deployer` role) starts the `gamecon-archive-2003`
container and writes its Caddy vhost. 2003 builds on `php:5.6-apache` with no
PHP extensions (static, like 2004–2011).

Reconstruction process: see `docs/generated/archiv-rekonstrukce-z-wayback.md`
in the main branch (the `altar.cz/gamecon/` path era; cp1250 encoding —
landmines #6 window-rollover and #7 charset-only-in-header both apply).

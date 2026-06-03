# Archive 2002 — static reconstruction (Altar publisher-site era)

`2002.gamecon.cz` is a **static archive**, like 2003–2011 and unlike the later
year-archives (2012–2025) which run their original PHP against their database.

**GameCon 2002 was special** — it did **not** run in its usual Olomouc venue.
Instead it was held as part of **Avalcon / Eurocon in CHOTĚBOŘ** (the
all-European SF/fantasy/horror convention, hosted in the Czech Republic that
year). It was organised by the **Altar** publishing house.

## Why static — the `altar.cz/gamecon/` path era

Like 2003–2005 (and unlike the 2006–2008 `gamecon.altar.cz` subdomain era and
the 2009+ `gamecon.cz` era), in 2002 GameCon was **a section of the whole
`altar.cz` publisher website**, living under the **path `altar.cz/gamecon/`**:

- **Source path** `altar.cz/gamecon/`. Pages are served here at the **root**
  (`/gamecon/X.html` → `/X.html`).
- **Windows-1250 (cp1250) encoding** → transcoded to UTF-8, with an injected
  `<meta charset="utf-8">` (the source declared charset only via the HTTP
  header).

## What this archive is

A faithful **static snapshot** of the GameCon 2002 section from the Internet
Archive's `altar.cz/gamecon/` captures.

### Epoch switcher (Před / Po)

The homepage exists in two captured states, toggled by the sticky clock pill:

- **`index.html` = Po** (default) — the **post-festival** state (Aug 2002:
  "…**letošní rok, kdy byl Gamecon součástí Euroconu**…", past tense).
- **`index-pred.html` = Před** — the **pre-festival** state (Feb 2002: "Gamecon
  2002 **bude** součástí Avalconu", pointing to the combined con).

(A December 2002 capture also exists but its content cells came back empty —
a degenerate capture — so the substantive August state is used for Po.)

### Pages

- **~10 GameCon pages** — homepage, mistrovství (DrD), přihláška, the tournament
  pages (`turnaj_arena`, `_battletech`, `_magic`, `_talisman`), and the
  Mistrovství ČR v DrD results archive (`vysledky98`, `99`, `00`, `02`).
- **Altar publisher nav menu** — the section landing pages it links to
  (`/altar/`, `/arena/`, `/battletech/`, `/drd/`, `/drdplus/`, `/dech/`,
  `/hexaedr/`, `/akce/`, `/odkazy/`, `/kontakt/`, `/help/`), fetched from the
  2002 capture window.
- **Theme** — the full 2002-era `stylesheets/default.css` + `/img/altar.gif`
  (sidebar logo) + the GameCon images (tournament diploma thumbnails, map,
  `logo1998.jpg`), served at their absolute paths.
- `.htaccess` / `404.html` — themed 404 carrying the authentic Altar chrome.

### Known gaps

- **The Avalcon special page** (`www.avalcon.cz`) was a **separate domain** (the
  combined con's own site), now dead — left as a captured external link, not
  reconstructed (it was never part of `altar.cz`).
- **Dynamic CGI** (`/cgi/*`) and a few publisher pages uncaptured in the 2002
  window (`/prog/`, `/kontakt/dotazy.html`) fall to the themed 404.
- `small.logo1998.gif` (one thumbnail) was never captured — the full
  `logo1998.jpg` is present.

## Deploy

Built and deployed like the other year-archives, via
`.github/workflows/deploy-year-archive.yml` (on `main`) dispatched by this
branch's `archive-push-redeploy.yml` on push. 2002 builds on `php:5.6-apache`
with no PHP extensions (static, like 2003–2011).

Reconstruction process: see `docs/generated/archiv-rekonstrukce-z-wayback.md`
in the main branch (the `altar.cz/gamecon/` path era; cp1250 encoding;
landmines #7 charset, #8 dead-ads, #9 missing CSS-linked theme assets all apply).

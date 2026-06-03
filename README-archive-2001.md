# Archive 2001 — static reconstruction (Altar publisher-site era, minimal)

`2001.gamecon.cz` is a **static archive**, like 2002–2011 and unlike the later
year-archives (2012–2025) which run their original PHP against their database.

GameCon 2001 ran in **OLOMOUC** (per Wikipedia; no exact date recorded). It was
organised by the **Altar** publishing house, as a section of `altar.cz/gamecon/`.

## Why this archive is deliberately minimal

**Wayback never preserved a genuine GameCon 2001 website.** Verified from every
angle: there was no `gamecon.cz` domain or `gamecon.altar.cz` subdomain yet
(zero captures 2000–2002), and a web/Wikipedia search turned up no separate 2001
site — GameCon 2001 only ever lived at `altar.cz/gamecon/`. Within that path,
the only 2001-window homepage capture (Apr 2001) is the **off-season
placeholder**: it still shows the 2000 recap and explicitly says *"informace o
dalším ročníku, tedy Gameconu 2001, zatím nejsou k dispozici"* (last modified
Nov 2000). The actual 2001 program/tournament pages were never published or
captured.

So rather than fabricate a festival site, this archive serves **only what
genuinely exists** — and is honest about it.

## What this archive is

- **Homepage** (`index.html`) — the captured Apr-2001 off-season state (2000
  recap + "2001 info not yet available"). This is the truthful 2001 web state.
- **Mistrovství ČR v DrD results** — `vysledky01.html` (the **real GameCon 2001
  results**, recovered from a 2004 capture where the page persisted) +
  `vysledky98/99/00`.
- **Altar publisher-site nav menu** — the section landing pages it links to
  (`/altar/`, `/arena/`, `/battletech/`, `/drd/`, `/dech/`, `/hexaedr/`,
  `/akce/`, `/prog/`, `/odkazy/`, `/kontakt/`, `/help/`), from the 2001 window.
- **Theme** — the 2001-era `stylesheets/default.css` + `/img/altar.gif` + the
  GameCon diploma/map/logo images, at their absolute paths. Dead ad embeds
  (billboard.cz, eReklama) stripped.
- `.htaccess` / `404.html` — themed 404 with the authentic Altar chrome.

**No epoch switcher** — there is no distinct pre/post-festival 2001 page to
toggle between (only the single off-season placeholder exists).

### Known gaps

- **The 2001 festival site proper** (program, tournament rules, schedule) was
  never published/captured by Wayback — those URLs 404. This is the honest
  limit of what survives.
- Dynamic CGI (`/cgi/*`), `/altar/novinky.html` (uncaptured in 2001), and
  `small.logo1998.gif` (one thumbnail) fall to the themed 404 / render as a
  broken image. The full `logo1998.jpg` masthead is present.

## Deploy

Built and deployed like the other year-archives (`deploy-year-archive.yml`,
`php:5.6-apache`, no PHP extensions). Reconstruction process: see
`docs/generated/archiv-rekonstrukce-z-wayback.md` (the `altar.cz/gamecon/` path
era; cp1250; landmines #7 charset, #8 dead-ads, #9 missing theme assets apply).

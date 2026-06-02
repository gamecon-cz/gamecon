# Archive 2006 — static reconstruction (Altar era)

`2006.gamecon.cz` is a **static archive**, like 2007–2011 and unlike the later
year-archives (2012–2025) which run their original PHP against their database.

GameCon 2006 was held **13.–16. 7. 2006 in OLOMOUC** (DDM, tř. 17. listopadu 47,
plus nearby university dorms) — **not Pardubice**. The festival moved to
Pardubice only from 2007; 2006 was the last Olomouc edition in this archive
series. It was still organised by the **Altar** publishing house — GameCon was
handed over to its fan community only between 2008 and 2009.

## Why static — and why it's the Altar-era layout

Like 2007/2008 (and unlike 2009+), the 2006 site was **not** on `gamecon.cz`. In
the Altar era it lived at **`gamecon.altar.cz`** — flat hand-written `.html`
files at the host root, its own stylesheets (`stylesheets/layout.css` +
`content.css` + `print.css`) and `layout/` background tiles, **ISO-8859-2
(Latin-2) encoding**. It does **not** share the `system_styly/styl1` theme used
by 2009–2011; everything was fetched from `gamecon.altar.cz` and transcoded to
UTF-8.

There was never a `2006.gamecon.cz` subdomain in 2006; this archive adopts the
per-year subdomain convention to serve the frozen snapshot at a stable address.

## What this archive is

A faithful **static snapshot** of the GameCon 2006 site as the Internet Archive
captured `gamecon.altar.cz` during the 2006 cycle (**Jun 2006 – Dec 2006**,
latest capture per URL — capped before the homepage rolled over to the 2007
edition in early 2007; the saved homepage is the post-festival state, "…GameCon
**konal** 13.–16. července … v Olomouci"):

- **35 content pages** — homepage, organisational info, RPG / LARP / board-game
  / lectures sections, the full Mistrovství ČR v Dračím doupěti material (rules,
  character creation, síň slávy, score table 2006), board-game tournament pages,
  lodging/board/fees, organiser list, history. Restored from web.archive.org
  (`id_` raw captures), transcoded to UTF-8, with `gamecon.altar.cz` absolute
  links rewritten to root-relative.
- **Theme** — `stylesheets/*.css`, `layout/logotype.gif`, `img/org/*.gif`
  (organiser photos), `favicon.ico`. The authentic Altar-era grey GameCon logo
  is kept as-is (`layout/logotype.gif`); it carries **no date line** — the 2006
  date/place lived in the homepage text, not the logo.
- `.htaccess` — serves the flat files directly. The 2006 pages reference **no
  internal `.php`** (only external links), so no `.php` redirects are needed;
  uncaptured URLs → themed 404.
- `404.html` — themed 404 carrying the authentic 2006 Altar chrome.

### Known gaps

- **Theme background tiles** — the CSS references decorative `layout/back*.gif` /
  `gradient-*.gif` images the Internet Archive never captured (only
  `logotype.gif` survived). They have solid-colour CSS fallbacks, so the layout
  renders cleanly but without the original gradient/rounded-corner decoration.
- **1 organiser photo** (`img/org/lumik.gif`) was never captured → one
  broken-image icon on `poradatele.html`; the other 16 are present.

## Deploy

Built and deployed exactly like the other year-archives, via
`.github/workflows/deploy-year-archive.yml` (on `main`) dispatched by this
branch's `archive-push-redeploy.yml` on push. The host's `deploy-year-archive.sh`
(ansible `year_archive_deployer` role) starts the `gamecon-archive-2006`
container and writes its Caddy vhost. 2006 builds on `php:5.6-apache` with no
PHP extensions (static, like 2007–2011).

Reconstruction process: see `docs/generated/archiv-rekonstrukce-z-wayback.md`
in the main branch (the Altar-era path — different domain, CMS and Latin-2
encoding — applies to 2006–2008; note landmine #6, the CDX-window rollover,
which is why the fetch window stops at 2007-01-31).

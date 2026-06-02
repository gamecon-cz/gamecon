# Archive 2007 — static reconstruction (Altar era)

`2007.gamecon.cz` is a **static archive**, like 2008–2011 and unlike the later
year-archives (2012–2025) which run their original PHP against their database.

GameCon 2007 was held **12.–15. 7. 2007 in Pardubice** (under the umbrella of
the international Czech Open festival), and was still organised by the **Altar**
publishing house — GameCon was handed over to its fan community only between
2008 and 2009.

## Why static — and why it's the Altar-era layout

Like 2008 (and unlike 2009+), the 2007 site was **not** on `gamecon.cz`. In the
Altar era it lived at **`gamecon.altar.cz`** — flat hand-written `.html` files
at the host root, its own stylesheets (`stylesheets/layout.css` + `content.css`
+ `print.css`) and `layout/` background tiles, **ISO-8859-2 (Latin-2)
encoding**. It does **not** share the `system_styly/styl1` theme used by
2009–2011, so nothing is reused from those archives; everything was fetched from
`gamecon.altar.cz` and transcoded to UTF-8.

There was never a `2007.gamecon.cz` subdomain in 2007; this archive adopts the
per-year subdomain convention to serve the frozen snapshot at a stable address.

## What this archive is

A faithful **static snapshot** of the GameCon 2007 site as the Internet Archive
captured `gamecon.altar.cz` during the 2007 festival cycle (**Jan 2007 – Jan
2008**, latest capture per URL — capped before the homepage rolled over to
advertise GameCon 2008 in early 2008):

- **44 content pages** — homepage, organisational info, RPG / LARP / board-game
  / lectures sections, the full Mistrovství ČR v Dračím doupěti material (rules,
  character creation, síň slávy, score tables 2006/2007), ~18 board-game
  tournament pages, lodging/board/fees, organiser list, history. Restored from
  web.archive.org (`id_` raw captures), transcoded to UTF-8, with
  `gamecon.altar.cz` absolute links rewritten to root-relative.
- **Theme** — `stylesheets/*.css`, `layout/logotype.gif`, `img/org/*.gif`
  (organiser photos). The authentic Altar-era grey GameCon logo is kept as-is
  (`layout/logotype.gif`); unlike 2009+ it carries **no date line** — the 2007
  date/place lived in the homepage text, not the logo.
- `.htaccess` — serves the flat files directly; redirects the four `.php` URLs
  (`prihlaska`, `dotaz`, `seznam_druzin`, `seznam_prihlasenych`) to their
  captured `.html` snapshots (root-relative targets, so the [R] redirect doesn't
  resolve against the filesystem path); everything else → themed 404.
- `404.html` — themed 404 carrying the authentic 2007 Altar chrome.

### Known gaps

- **Dynamic `.php`** (registration, contact form, live lists) had no backend;
  each redirects to its captured static `.html` snapshot.
- **Theme background tiles** — the CSS references decorative `layout/back*.gif` /
  `gradient-*.gif` images the Internet Archive never captured (only
  `logotype.gif` survived). They have solid-colour CSS fallbacks, so the layout
  renders cleanly but without the original gradient/rounded-corner decoration.
- **4 organiser photos** (`img/org/aquila.gif`, `dalcor.gif`, `jiron.gif`,
  `markus.gif`) were never captured → broken-image icons on `poradatele.html`;
  the other 13 are present.

## Deploy

Built and deployed exactly like the other year-archives, via
`.github/workflows/deploy-year-archive.yml` (on `main`) dispatched by this
branch's `archive-push-redeploy.yml` on push. The host's `deploy-year-archive.sh`
(ansible `year_archive_deployer` role) starts the `gamecon-archive-2007`
container and writes its Caddy vhost. 2007 builds on `php:5.6-apache` with no
PHP extensions (static, like 2008–2011).

Reconstruction process: see `docs/generated/archiv-rekonstrukce-z-wayback.md`
in the main branch (the Altar-era path — different domain, CMS and Latin-2
encoding — applies to 2008 and 2007 alike).

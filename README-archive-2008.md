# Archive 2008 — static reconstruction (Altar era)

`2008.gamecon.cz` is a **static archive**, like 2009–2011 and unlike the later
year-archives (2012–2025) which run their original PHP against their original
database.

GameCon 2008 was held **10.–13. 7. 2008 in Pardubice** (under the umbrella of
the international Czech Open festival), and was still organised by the **Altar**
publishing house — GameCon was handed over to its fan community only between
2008 and 2009.

## Why static — and why it's different from 2009–2011

The 2008 site was **not** on `gamecon.cz`. In the Altar era it lived at
**`gamecon.altar.cz`** (earlier still at `altar.cz/gamecon`), and it was a
completely different website from the 2009+ one:

- **Different host** — `gamecon.altar.cz`.
- **Different CMS / theme** — flat hand-written `.html` files at the host root,
  its own stylesheets (`stylesheets/layout.css` + `content.css` + `print.css`)
  and `layout/` background tiles. It does **not** share the `system_styly/styl1`
  theme used by 2009–2011, so nothing is reused from the other archives.
- **ISO-8859-2 (Latin-2) encoding** — every page was transcoded to UTF-8
  during reconstruction (charset meta rewritten accordingly).

There was never a `2008.gamecon.cz` subdomain in 2008; this archive adopts the
per-year subdomain convention to serve the frozen snapshot at a stable address.

## What this archive is

A faithful **static snapshot** of the GameCon 2008 site as the Internet Archive
captured `gamecon.altar.cz` during the 2008 festival cycle (Jan 2008 – early
2009, latest capture per URL):

- **42 content pages** — homepage, organisational info, RPG / LARP / board-game
  / lectures sections, the full Mistrovství ČR v Dračím doupěti material
  (rules, character creation, sín slávy, score tables 2007/2008), ~20 board-game
  tournament pages, lodging/board/fees, organiser list, history. Restored from
  web.archive.org (`id_` raw captures), transcoded to UTF-8, with
  `gamecon.altar.cz` absolute links rewritten to root-relative.
- **Theme** — `stylesheets/*.css`, `layout/logotype.gif`, `img/org/*.gif`
  (organiser photos). The authentic Altar-era grey GameCon logo is kept as-is
  (`layout/logotype.gif`); unlike 2009+ it carries **no date line** — the 2008
  date/place lived in the homepage text, not the logo.
- `.htaccess` — serves the flat files directly; redirects the four `.php` URLs
  that have a captured `.html` snapshot (`prihlaska`, `prihlaska3`,
  `seznam_druzin`, `seznam_prihlasenych`) to that snapshot; everything else
  (uncaptured / dynamic) → themed 404.
- `404.html` — themed 404 carrying the authentic 2008 Altar chrome.

### Known gaps

- **Dynamic `.php` pages** (registration form `prihlaska.php`, guestbook,
  questionnaires `dotaznik_*`, live participant/team lists) had no backend to
  capture and are not served — they 404 (or redirect to a static snapshot where
  one exists). This is the nature of a static archive of a lost dynamic site.
- **Theme background tiles** — the CSS references ~14 decorative
  `layout/back*.gif` / `gradient-*.gif` background images that the Internet
  Archive never captured (only `logotype.gif` survived). They have solid-colour
  CSS fallbacks (white background, grey borders), so the layout renders cleanly
  but without the original gradient/rounded-corner decoration.
- **3 organiser photos** (`img/org/aquila.gif`, `dalcor.gif`, `markus.gif`) were
  never captured → broken-image icons on `poradatele.html`; the other 10 are
  present.

## Deploy

Built and deployed exactly like the other year-archives, via
`.github/workflows/deploy-year-archive.yml` (on `main`) dispatched by this
branch's `archive-push-redeploy.yml` on push. The host's `deploy-year-archive.sh`
(ansible `year_archive_deployer` role) starts the `gamecon-archive-2008`
container and writes its Caddy vhost. 2008 builds on `php:5.6-apache` with no
PHP extensions (static, like 2009–2011).

Reconstruction process: see `docs/generated/archiv-rekonstrukce-z-wayback.md`
in the main branch. **Note:** 2008 extends that playbook — pre-2009 years used
the Altar-era `gamecon.altar.cz` domain with a different CMS and Latin-2
encoding, so the "find the source" step must check the old domain.

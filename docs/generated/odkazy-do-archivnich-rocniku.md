# Odkazy z ostrého adminu do archivních ročníků

TL;DR: Proklik z ostré na tentýž objekt v archivním ročníku potřebuje na URL **tři** nezávislé věci najednou (`?gate=` na bránu, `?gcsso=` na přihlášení, vlastní parametr akce). Archivy běží na `ROK.gamecon.cz/admin`, **ne** na `admin.ROK.gamecon.cz`. Ne každý ročník umí totéž — dolní meze se liší podle toho, co daná archivní větev obsahuje.

## Tvar URL

```
https://2023.gamecon.cz/admin/uzivatel?pracovni_uzivatel=102&gcsso=<token>&gate=<token>
```

**Hostname:** archiv je jeden kontejner na ročník, admin sedí na cestě `/admin` — stejně jako v lokálním vývoji. Subdoména `admin.ROK.gamecon.cz` **neexistuje**; jediné, co jede na `admin.` subdoméně, je ostrá (`URL_ADMIN` v `nastaveni/verejne-nastaveni-produkce.php`). Skládat hostname ručně je chyba — reálně nasazené ročníky a jejich URL dává `DeploymentsReader::readArchives()` (`model/Dev/DeploymentsReader.php`), tj. odkaz na nenasazený ročník vůbec nevznikne.

## Tři brány na jedné URL

| Parametr | K čemu | Kdo podepisuje | Bez tajemství |
|----------|--------|----------------|---------------|
| `?gate=` | Caddy brána před archivy chce basic auth; vložené `user:heslo@host` prohlížeč při kliknutí zahodí. Gate-validator token vymění za session cookie. | `GateLink::podepis()`, `ARCHIVE_GATE_SECRET` | URL zůstane čistá → basic-auth dialog |
| `?gcsso=` | Magické přihlášení do archivu. Token nese **číselné `id_uzivatele`** (stabilní napříč snapshoty; e-mail se může časem přiřadit jinému člověku). Párovací cookie `.gamecon.cz` zajistí, že *sdílený* odkaz nikoho nepřihlásí. | `CrossSiteLogin::podepis()` klíčem **odvozeným pro ročník** `hash_hmac('sha256', rok, GAMECON_SSO_SECRET)` | Nepřipojí se → přihlašovací obrazovka |
| akční parametr | Až tenhle něco udělá (`?pracovni_uzivatel=` otevře uživatele) | — | — |

Master `GAMECON_SSO_SECRET` žije **jen na ostré**; archiv dostane přes deploy jen svůj odvozený `GAMECON_SSO_KEY`. Popadnutý archiv tak umí podvrhnout přihlášení jen do sebe. Viz `docs/` + ansible role `year_archive_deployer`.

## Proč smí být `gcsso` a `pracovni_uzivatel` na jedné URL (gotcha)

Vypadá to jako konflikt — obojí zpracovává `admin/scripts/prihlaseni.php` a `back()` končí `exit`. Funguje to díky pořadí a zachování query:

1. `gcsso` se vyřídí první (`prihlaseni.php:48`) → `back(getCurrentUrlWithQuery(['gcsso' => null]))`.
2. `getCurrentUrlWithQuery()` (`model/funkce/fw-general.php:122`) **maže jen uvedené klíče a ostatní zachová** → `pracovni_uzivatel` přežije do redirectu.
3. Druhý request: uživatel už je přihlášený, zpracuje se `pracovni_uzivatel` (`prihlaseni.php:93`).

Kdyby `getCurrentUrlWithQuery()` query zahazovalo, odkaz by uživatele přihlásil, ale pracovního uživatele by neotevřel.

## Dolní meze podle ročníku (nejsou stejné!)

Ověřeno čtením archivních větví `origin/archive/NNNN` (existence `admin/index.php` resp. `app/admin/index.php` a výskyt `gcsso` / `pracovni_uzivatel` v `prihlaseni.php`):

| Ročníky | Kam odkaz vede | Co na URL je |
|---------|----------------|--------------|
| **2022+** | rovnou na uživatele, přihlášeného | `pracovni_uzivatel` + `gcsso` + `gate` |
| **2014–2021** | do adminu, přihlášeného | `gcsso` + `gate` |
| **2011–2013** | do adminu na přihlašovací obrazovku | jen `gate` |
| **≤2010** | nikam (prostý text) | — |

- **`?pracovni_uzivatel=` umí až od 2022.** Větve 2012–2021 ten parametr v `prihlaseni.php` vůbec nečtou (mají jen POST `vybratUzivateleProPraci`).
- **`?gcsso=` umí 2014–2025** (2015–2016 v layoutu `app/`). 2012–2013 nemá SSO záměrně — starší layout `admin/` + `sdilene/` bez `admin/scripts/prihlaseni.php`, `get()` vrací `''` místo `null`.
- **`/admin` jako živá aplikace** je od **2011** (2011 v layoutu `app/`). 2009–2010 jsou statické kopie z Wayback, ≤2008 éra Altaru — admin tam neexistuje. Pozor: `stare-rocniky.php` má vlastní mez `epochaRocniku() === 'ziva'` na **2012** a odkaz na 2011 nenabízí; tady je mez o rok níž, protože 2011 admin fyzicky má.

Odkaz se **degraduje, nemizí** — i „jen do adminu" ušetří hledání hostname a projde bránou. Když přidáváš nový typ prokliku, **ověř mez pro svůj parametr zvlášť**; to, že ročník umí `gcsso`, neznamená, že umí tvou akci.

## Vstupní body

- `model/Dev/OdkazDoArchivnihoAdmina.php` — složení odkazu na uživatele (gate + sso + `pracovni_uzivatel`, respektuje meze i nasazenost)
- `admin/scripts/modules/uzivatel.php` (blok „historie účasti") — konzument; ročníky bez odkazu vypíše jako prostý text
- `admin/scripts/modules/web/stare-rocniky.php` — původní vzor (seznam archivů), `previews.php` totéž pro preview
- `admin/scripts/prihlaseni.php` — ověřovací strana obou tokenů
- `model/Dev/{GateLink,CrossSiteLogin,SsoParovaciCookie,ArchivSsoPrihlaseni,DeploymentsReader}.php`

Testy: `tests/Dev/OdkazDoArchivnihoAdminaTest.php`, `tests/Dev/CrossSiteLoginTest.php`.

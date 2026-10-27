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

⚠️ **Dvě různé identity na jedné URL.** `gcsso` nese id **operátora** (přihlášeného admina, který klikl — `$u`), `pracovni_uzivatel` nese id **prohlíženého** uživatele (`$uPracovni`). Záměna admina přihlásí do cizího účtu — reálně se to stalo, protože oba údaje šly do jedné metody jako jeden parametr. Regresní test: `testSsoPrihlasujeOperatoraNePracovnihoUzivatele`.
| akční parametr | Až tenhle něco udělá (`?pracovni_uzivatel=` otevře uživatele) | — | — |

Master `GAMECON_SSO_SECRET` žije na ostré, betě a preview, **ne v archivech**; archiv dostane přes deploy jen svůj odvozený `GAMECON_SSO_KEY`. Popadnutý archiv tak umí podvrhnout přihlášení jen do sebe. Viz `docs/` + ansible role `year_archive_deployer`.

## Proč smí být `gcsso` a `pracovni_uzivatel` na jedné URL (gotcha)

Vypadá to jako konflikt — obojí zpracovává `admin/scripts/prihlaseni.php` a `back()` končí `exit`. Funguje to díky pořadí a zachování query:

1. `gcsso` se vyřídí první (`SsoPrihlaseniZUrl::zpracuj()`, volaná z `prihlaseni.php`) → `back(getCurrentUrlWithQuery(['gcsso' => null]))`.
2. `getCurrentUrlWithQuery()` (`model/funkce/fw-general.php`) **maže jen uvedené klíče a ostatní zachová** → `pracovni_uzivatel` přežije do redirectu.
3. Druhý request: uživatel už je přihlášený, zpracuje se `pracovni_uzivatel` (blok `get('pracovni_uzivatel')` v `prihlaseni.php`).

Kdyby `getCurrentUrlWithQuery()` query zahazovalo, odkaz by uživatele přihlásil, ale pracovního uživatele by neotevřel.

## Preview: odkaz na web i na admin přihlásí stejně

Stránka Previews (`admin/scripts/modules/web/previews.php`) vede oba odkazy řádku (Web, Admin) přes `web/previews?web=<slug>`, resp. `?admin=<slug>`. Token `?gcsso=` se podepíše až po kliknutí (platí 5 minut) spolu s nastavením párovací cookie, pak následuje redirect na preview (`<url>`, resp. `<url>/admin`) s `?gate=`.

- `?gcsso=` čte `SsoPrihlaseniZUrl::zpracuj()` na obou vstupech: `admin/scripts/prihlaseni.php` i `web/index.php`. Admin a web preview sdílejí session (cookie `.host`, `session_name` podle `URL_WEBU`), takže přihlášení na jedné straně platí i na druhé.
- Ověřovací klíč: archiv svým klíčem ročníku `GAMECON_SSO_KEY`, preview masterem `GAMECON_SSO_SECRET`, ostrá a beta masterem nikdy (`getOverovaciKlic()`, hlídá `SsoPrihlaseniZUrlTest`). Veřejný web je přes `?gcsso=` dostupný všem, proto výběr klíče žije na jednom místě. Bez klíče (ostrá, beta, lokál) se `?gcsso=` vůbec nezpracuje a požadavek se nepřesměruje; hodnota, která není text (`?gcsso[]=x`), se taky ignoruje.
- Web a Admin sdílejí jednu párovací cookie. Druhé kliknutí proto převezme nonce z cookie (`SsoParovaciCookie::nastavNonceProKliknuti()`): nový nonce by přepsal cookie, na kterou se karta otevřená prvním kliknutím ještě spoléhá, a ta by zůstala nepřihlášená.
- ⚠️ „Kopírovat adresu odkazu" u sloupce Web už nedá URL preview, ale odkaz na rozcestník (ten chce přihlášení do adminu). Ke sdílení se opisuje zobrazený hostname.
- Preview ze starší větve `?gcsso=` na webu nečte: odkaz nepřihlásí, jinak funguje.
- Lokální ověření: v `docker-compose.override.yml` stejná hodnota pro `GAMECON_SSO_SECRET` i `GAMECON_SSO_KEY` a mount složky s `previews/<slug>.json` do `/var/lib/gamecon/deployments`. Lokální Apache zahazuje query při `/admin?x` → `/admin/?req=`, token pro admin se tedy zkouší na `/admin/?req=&gcsso=`.

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
- `admin/scripts/prihlaseni.php` a `web/index.php` — ověřovací strana obou tokenů (volají `SsoPrihlaseniZUrl::zpracuj()`)
- `model/Dev/{GateLink,CrossSiteLogin,SsoParovaciCookie,ArchivSsoPrihlaseni,SsoPrihlaseniZUrl,DeploymentsReader}.php`

Testy: `tests/Dev/OdkazDoArchivnihoAdminaTest.php`, `tests/Dev/CrossSiteLoginTest.php`, `tests/Dev/SsoPrihlaseniZUrlTest.php`.

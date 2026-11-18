TL;DR: korektura textů aktivit (název, krátký a dlouhý popis) = příznak `akce_seznam.probehla_korekce` plus nástroj v adminu (Aktivity → Korektury textů), který texty po balících posílá AI (přes API Anthropicu, nebo ručně souborem do AI chatu), čte odpověď zpět a nechá korektora návrhy přijmout, upravit, nebo zamítnout.

## Příznak `probehla_korekce`

- Jeden boolean na aktivitě, sdílený celou rodinou instancí: `Aktivita::nastavKorekci()` ho nastaví všem s týmž `patri_pod`. Instance sdílejí i texty (`Aktivita::uloz()` mění název a krátký popis všem, `Aktivita::popis()` dlouhý popis všem).
- Smaže se jen změnou **dlouhého popisu** uživatelem bez práva `Pravo::PROVADI_KOREKCE` (`Aktivita::popis(..., resetujKorekci)`). **Změna názvu nebo krátkého popisu ho nesmaže** — rozpor se záměrem „opravený text se od korektury nezměnil" (hint v `admin/scripts/modules/aktivity/aktivity.xtpl`).
- Nová aktivita ho dostane rovnou, když existuje opravená aktivita se stejným názvem, krátkým i dlouhým popisem (`Aktivita::uloz()`, větev vkládání).
- Historie textů se neukládá a aktivity mezi ročníky nic nepropojuje; „loňská verze" = aktivita dřívějšího ročníku se stejným `nazev_akce` a `probehla_korekce = 1`.
- V některých popisech je ruční značka `<p hidden> Korektura 2025 </p>`, pokyny pro AI ji zakazují měnit.

## Nástroj v adminu

Vstup: `admin/scripts/modules/aktivity/korektury.php` (právo `PROVADI_KOREKCE`), logika v `model/Aktivita/Korektura/`, tabulka `akce_korektura`.

- **Export** (`KorekturyTextu::exportuj()`): hlavní aktivity ročníku bez korektury a bez otevřené korektury, bez technických, brigádnických a systémových (`KorekturyTextuRepository::dejTextyKExportu()`). Rozdělí je do balíků a u každé uloží texty, jak byly při exportu (`puvodni_*`).
- **Soubor pro AI** (`SouborKorektur`): pokyny + bloky `<gc-aktivita id="…">` se značkami `<gc-nazev>`, `<gc-popis-kratky>`, `<gc-popis>`; dřívější opravená verze v `<gc-drivejsi rok="…">`. Značky místo JSONu, protože dlouhý markdown v JSONu se musí escapovat a model to kazí; rozbitá značka pokazí jen svou aktivitu.
- **Odpověď** (`OdpovedAi`): AI vrací jen změněná pole celá, prázdný blok = bez chyb. Text okolo bloků se ignoruje, useknutý blok se přeskočí s varováním. Import jde po částech a opakovaně, pozdější návrh přepíše dřívější, dokud není vyřízený.
- **Schválení** (`KorekturyTextu::prijmi()`): uloží texty celé rodině instancí a nastaví `probehla_korekce`. Odmítne, když se text aktivity od exportu změnil (porovnání s `puvodni_*`, oříznuté a s jednotnými konci řádků). Pole, které korektor nezměnil, zůstane v DB bajt po bajtu (stará data mají CRLF).
- Stavy (`StavKorekturyEnum`): `exportovano` → `navrzeno` → `prijato` / `zamitnuto`. Zamítnutá aktivita zůstane neopravená a jde exportovat znovu.

## Přes API (dávky)

Vedle souboru jde balík poslat AI rovnou: `KorekturyTextu::posliAi()`, rozhraní `DavkyAi`, implementace `AnthropicDavkyAi` (model `claude-opus-5-5`, effort `high`, hlavička `anthropic-version: 2026-10-21`). Bez klíče `ANTHROPIC_API_KEY` je `AnthropicDavkyAi::zNastaveni()` null a stránka nabízí jen soubor.

- **Přímé HTTP přes Guzzle, ne SDK**: korektury potřebují čtyři volání (založit dávku, stav, výsledky, zrušit). Oficiální `anthropic-ai/sdk` by do commitnutého `vendor/` přidalo kolem 4 300 souborů a 28 MB, proto ho projekt nemá. Chyba API, sítě i odpověď, kterou nejde přečíst (stránka proxy místo JSON, výsledek bez `result`, prázdné výsledky skončené dávky), je `ChybaApiAi` se zprávou pro korektora a s HTTP kódem (`kodHttp`, null = API neodpovědělo). Opakování při chybě neděláme, dávka se zkusí při další kontrole.
- **Výsledky (JSONL) se dělí jen podle `\n`**, ne `preg_split('~\R~')`: `\R` bez `/u` bere za konec řádku i bajt 0x85, který je uvnitř 📅, 😅, 🍅 nebo Å, a rozseknutý řádek pak nejde dekódovat. JSON řetězec má konce řádků escapované, takže `explode("\n")` je bezpečné.
- **Message Batches, ne jednotlivé požadavky**: dávka stojí polovinu a webový požadavek nečeká na AI (odpověď bývá do hodiny, nejpozději do 24 h). Server-side `fallbacks` při odmítnutí Batches API nebere, odmítnutý text se vrátí k odeslání.
- **Jedna aktivita = jeden požadavek** (`custom_id` = `korektura-<id_korektury>`), pokyny jsou tytéž jako v souboru (`SouborKorektur::pokyny()`) a jdou jako systémový prompt, zpráva je blok `<gc-aktivita>` (`SouborKorektur::blokAktivity()`). Odpověď se čte stejným `OdpovedAi` a importuje stejně jako soubor; blok jiné aktivity se nepřijme.
- **Stav v `akce_korektura`**: `id_davky_ai` (`utf8mb4_bin`, ID dávky rozlišuje velikost písmen) a `odeslano_ai_kdy`. Vyplněné `id_davky_ai` = řádek čeká na odpověď té dávky; po skončení dávky se vymaže všem jejím řádkům. Odeslaný řádek se znovu neposílá, stáhnout do chatu ho jde dál.
- **Pozdější návrh přepíše dřívější, ať přišel kudykoli**: na dávku čekají otevřené řádky (`exportovano` i `navrzeno`), takže odpověď API došlá po návrhu z chatu ho přepíše, stejně jako novější odpověď z chatu přepíše návrh z API. Když dostane návrh z API a dávka pro řádek nic nepřinese, návrh z chatu zůstane.
- **Odeslání běží v zámku** (`KorekturyTextuRepository::vZamkuOdesilaniAi()`, `GET_LOCK` pojmenovaný podle databáze, ostrá a beta sdílí server): dvojklik nebo druhá karta počká a pak najde texty odeslané, místo aby se platila druhá dávka. Nedočká-li se do 60 s, korektor dostane `OdesilaniAiUzBezi`. `oznacOdeslaneAi()` navíc značí jen řádky bez dávky. Co neumíme: když POST na API vyprší, ale API dávku přijalo, o dávce nevíme a další odeslání založí novou.
- **Odpovědi se načtou při otevření stránky, nejvýš jednou za minutu** (`nactiOdpovediAi()`, žádný cron; omezení drží admin stránka v session, aby každé Přijmout nečekalo na API). Skončená dávka se naimportuje a řádky bez návrhu (chyba, odmítnutí, useknutá odpověď) jdou poslat znovu nebo stáhnout. Chyba jedné dávky nezdrží ostatní (vrátí se v `VysledekNacteniAi::$chyby`, dávka se zkusí příště); chyba celého API (bez odpovědi, 401, 403, 429, 5xx) zastaví dotazy na další dávky; dávku, kterou API nezná (404, výsledky drží 29 dní, klíč jiného workspace), přestaneme čekat a její texty jdou poslat znovu.
- **Dávka, na kterou nic nečeká, se zruší** (`DavkyAi::zrus()`, chyba se jen zaloguje): při zrušení balíku a při kontrole, když korektor mezitím všechny její texty vyřídil.
- **Testy**: `AnthropicDavkyAiTest` podstrčí vlastní PSR-18 klient a kontroluje URL, hlavičky a tvar požadavku i čtení JSONL výsledků bez sítě; `KorekturyTextuTest` jede s `FalesneDavkyAi` (umí i selhat, `selze()`). `SouborKorekturTest` drží vzorek skutečných textů (`tests/Model/Aktivita/Korektura/data/`): JSON je vstup, `korektura-vzorek.txt` přesný soubor pro AI. Změna pokynů nebo formátu ho rozbije schválně, nový výstup se do vzorku uloží.

## Otevřené otázky

- Klíč k API: GitHub secret `ANTHROPIC_API_KEY` (společný pro ostrou i betu). Preview ho zatím nedostává (ansible `deploy-preview-branch.sh.j2`), viz tabulka env proměnných v `CLAUDE.md`.
- Pokyny pro AI jsou v kódu (`SouborKorektur::POKYNY`), domácí pravidla korektora (GameCon, typografie) zatím nejdou upravit z adminu.

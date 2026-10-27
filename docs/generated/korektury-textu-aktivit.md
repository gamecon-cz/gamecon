TL;DR: korektura textů aktivit (název, krátký a dlouhý popis) = příznak `akce_seznam.probehla_korekce` plus nástroj v adminu (Aktivity → Korektury textů), který texty posílá ručně do AI chatu po balících, čte odpověď zpět a nechá korektora návrhy přijmout, upravit, nebo zamítnout.

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

## Otevřené otázky

- (záměr) Automatická varianta přes API Anthropicu: stejná tabulka i schvalování, místo ručního souboru by se balík poslal API (strukturovaný výstup). Cena za ročník v řádu jednotek dolarů. Zatím nerozhodnuto, kdo by zakládal účet.
- Pokyny pro AI jsou v kódu (`SouborKorektur::POKYNY`), domácí pravidla korektora (GameCon, typografie) zatím nejdou upravit z adminu.

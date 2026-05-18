# docs/generated — index a pravidla

Dokumenty v `docs/generated/` jsou **vstupní bod pro Claude** při práci s většími koncepty GameConu. Záměr: místo opakovaného prohledávání kódu má Claude připravený destilovaný souhrn.

Pravidla **kdy** konzultovat / vytvořit dokument jsou v kořenovém `CLAUDE.md`. Tento soubor popisuje **jak** ho napsat a udržuje index.

## Index

<!-- Formát: `- [nazev-souboru](nazev-souboru.md) — jedna věta o čem to je` -->

_(zatím prázdné)_

## Povinné minimum dokumentu

1. **TL;DR** na prvním řádku souboru (1–2 věty: co to je, co dokument pokrývá).
2. **Vstupní body v kódu** přes `path/file.php:123` — ne kopírované bloky.
3. **Pravidla / invarianty** s označením zdroje, pokud není triviálně ověřitelný z kódu:
   - `(záměr)` — sděleno uživatelem, nemusí odpovídat implementaci
   - `(nejisté)` — neověřeno, odhad
   - bez značky = ověřeno čtením kódu
4. **Rozpor** mezi záměrem a implementací zaznamenej explicitně — je to cenná informace, ne chyba v dokumentu.

Zbylé sekce piš jen pokud dávají smysl, žádná rigidní šablona. Běžné sekce (volitelné): Rozsah · Datový model · Jak to funguje · Gotchas · Otevřené otázky.

## Pravidla efektivity

- **Kebab-case** názvy souborů, česky, výmluvné: `prihlasovani-na-aktivity.md`, `tymy-a-zamykani.md`.
- **Odkazuj, nekopíruj** — `path:line` je levnější než blok kódu pro čtenáře i pro udržování. Kód vkládej jen když je klíčový a nesnadno dohledatelný.
- **Krátce, skim-friendly** — bullet points, krátké odstavce, žádné úvody, opakování ani meta-komentáře.
- **Nezaduplikuj** `CLAUDE.md` ani existující `docs/` — raději odkaz (`viz docs/aktivita-instance.md`).
- **Neopisuj** kompletní DB schémata — jen sloupce a vztahy podstatné pro koncept.
- Když dokument přeroste ~300 řádků nebo pokrývá víc konceptů, **rozděl**.
- **Stabilita názvu** — na dokument lze odkazovat z kódu/PR, nepřejmenovávej bez důvodu.

## Údržba

- **Update > create**: pokud existuje blízký dokument, rozšiř / oprav ho místo vytváření nového.
- **Stale info přepiš**, nepřidávej rozporný text vedle.
- **Oprava od uživatele** → zaznamenej do dokumentu, ne jen do odpovědi v chatu.
- **Index v tomto souboru aktualizuj** při každém vytvoření / přejmenování / smazání dokumentu.

## Ochrana proti driftu

Kód se mění rychleji než docs — bez aktivní ochrany se dokumenty stanou zavádějícími a dělají víc škody než užitku.

### Při konzultaci dokumentu
- Čti dokument jako **hypotézu**, ne pravdu. Před rozhodnutím ověř klíčová tvrzení proti aktuálnímu kódu.
- Ověřuj primárně podle **jména symbolu** (třída, metoda, konstanta, tabulka) — čísla řádků driftují s každou editací, symboly přežijí.
- Pokud klíčové referenční soubory neexistují / byly přejmenovány → dokument je podezřelý jako celek, ne jen v tom jednom místě.

### Když najdeš drift (doc ≠ kód)
- **Neopravuj in-place jedinou větu.** Drift v jednom místě = silný signál, že dokument je nespolehlivý i jinde.
- Prověř celý dokument proti aktuálnímu kódu.
- Pokud je **přes polovinu obsahu zastaralé**, raději **smaž** a v případě potřeby napiš znovu — čistý restart je levnější a srozumitelnější než záplata na záplatě.
- Index v tomto souboru po smazání / přepsání aktualizuj.

Pokud drift-detekce začne být nákladná a opakovaná, **napiš příště menší a abstrahovanější dokumenty** — méně implementačních detailů = méně míst, kde může dokument ztratit přesnost.

### Prevence při psaní
- Preferuj `path.php::MetodaNebo::symbol` nebo `path.php + popis funkce` před `path.php:123` — symbolické odkazy jsou robustnější.
- `path:line` používej jen jako sekundární pomůcku, ne jako jediný ukazatel.
- Pro **rozpracovanou / nestabilní část kódu** drž dokument na **úrovni konceptu a business pravidel**, ne implementačních detailů. Implementace odteče, koncept zůstane.
- Pokud zaznamenáváš business pravidlo získané z promptu, napiš ho tak, aby mu rozuměl čtenář i po refactoru kódu (nepřipoutávej k aktuální struktuře tříd).

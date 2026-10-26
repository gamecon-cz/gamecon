TL;DR: Stránky linií (`/rpg`, `/epic`, `/larpy`, …) mají nad seznamem aktivit lištu s hledáním, štítky, dny, „Jen volná místa“ a přepínačem Karty / Seznam. Karty dál vykresluje PHP, Preact je jen schovává a přepíná jejich vzhled.

## Vstupní body v kódu

- `web/moduly/aktivity.php`: během vykreslování karet sbírá `$kartyProFiltr` a posílá je v `window.gameconPřednačtení.filtrAktivit` (typ `ui/src/api/filtrAktivit.ts`).
- `ui/src/pages/aktivityFiltr/index.tsx` (`AktivityFiltr`): lišta, renderuje se do `#preact-aktivity-filtr` v `web/sablony/blackarrow/aktivity.xtpl`.
- `ui/src/pages/aktivityFiltr/filtr.ts`: čistá logika filtru a čtení/zápis adresy (testy vedle).
- `Aktivita::isVolnoKPrihlaseni()`: co znamená „volné místo“.
- Vzhled seznamu a schovaných karet: `.aktivity_aktivity-seznam`, `.aktivity_aktivita[hidden]` ve `web/soubory/blackarrow/aktivity/aktivity.less`.

## Pravidla

- Lišta je jen u stránky s aspoň 15 kartami (`count($kartyProFiltr) >= 15`). U menších linií by překážela. Karta = skupina aktivit (`patriPod`), ne jednotlivý termín.
- **Den je skutečný den termínu (`denSkutecny()`), ne programový (`denProgramu()`).** Program řadí noční aktivity po půlnoci do předchozího dne. Karta ale ukazuje skutečný den a filtr se s ní nesmí rozcházet.
- „Jen volná místa“ = aspoň jeden termín je přihlašovatelný a má místo. Přihlášenému se počítají jen místa pro jeho pohlaví, nepřihlášenému stačí místo pro kohokoli. Týmové aktivity se počítají po týmech jako v `obsazenostHtml()`. Tlačítko se ukáže, jen když je na stránce aspoň jeden přihlašovatelný termín (`lzePrihlasovat`). Jinak by vždy vrátilo nulu.
- Štítky se kombinují stejně jako v programu (`filtrujAktivity`): uvnitř jedné kategorie stačí kterýkoli vybraný, mezi kategoriemi musí platit všechny.
- Hledá se bez diakritiky a velikosti písmen, všechna slova musí v kartě být. Prohledává se text detailu (`#aktivity_nahledText{id}`: název, štítky, celý popis) a vypravěč z karty. Text se bere z DOM, není podruhé v JSON.
- Filtr je v adrese (`?hledat=…&stitky=1,2&den=patek,sobota&volne=1`), takže jde poslat odkaz. Zapisuje se přes `replaceState`. Hodnoty, které na stránce nejsou (štítek jiné linie, den mimo termíny), se při načtení zahodí. Volba Karty / Seznam je osobní a ukládá se do `localStorage`.

## Gotchas

- **Scroll po přihlášení:** `zachovejScroll` (šablona) obnovuje pozici po POSTu. Musí běžet až po schování karet, jinak skočí na špatné místo. Proto ho šablona volá na `DOMContentLoaded` a Preact karty schovává v `useLayoutEffect`. Bundle je `type="module"`, takže běží před `DOMContentLoaded`, a `useLayoutEffect` proběhne synchronně při `render()`.
- `odstranDiakritiku()` vytváří při každém volání transliterátor. Pro 177 termínů RPG to stálo asi 60 ms na stránku. Klíč dne se proto kešuje podle názvu dne (`$klicePodleNazvuDne`).
- Karta se schovává atributem `hidden`. `.aktivity_aktivita` má ale `display: inline-block`, které výchozí styl `[hidden]` přebije, proto je v `aktivity.less` vlastní pravidlo.

TL;DR: Stránky linií (`/rpg`, `/epic`, `/larpy`, …) mají nad seznamem aktivit lištu s hledáním, štítky, vypravěči, dny, „Jen volná místa“, přepínačem Karty / Seznam a přepínačem ročníku. Karty dál vykresluje PHP, Preact je jen schovává a přepíná jejich vzhled. Jiný ročník načte PHP znovu.

## Vstupní body v kódu

- `web/moduly/aktivity.php`: během vykreslování karet sbírá `$kartyProFiltr` a posílá je v `window.gameconPřednačtení.filtrAktivit`, ročníky v `rocnikyAktivit` (typy `ui/src/api/filtrAktivit.ts`).
- `ui/src/pages/aktivityFiltr/index.tsx` (`AktivityFiltr`): lišta, renderuje se do `#preact-aktivity-filtr` v `web/sablony/blackarrow/aktivity.xtpl`.
- `ui/src/pages/aktivityFiltr/filtr.ts`: čistá logika filtru a čtení/zápis adresy (testy vedle).
- `Aktivita::isVolnoKPrihlaseni()`: co znamená „volné místo“.
- Vzhled seznamu a schovaných karet: `.aktivity_aktivity-seznam`, `.aktivity_aktivita[hidden]` ve `web/soubory/blackarrow/aktivity/aktivity.less`.

## Pravidla

- Celá lišta je jen u stránky s aspoň 15 kartami (`count($kartyProFiltr) >= 15`). U menších linií by překážela. Karta = skupina aktivit (`patriPod`), ne jednotlivý termín. Menší stránka dostane jen přepínač ročníku, pokud tu nějaké aktivity byly i dřív.
- **Ročník („wayback“):** `?rocnik=2024` ukáže aktivity toho ročníku *místo* letošních, ne dohromady (záměr, pořadatelé to chtějí hlavně interně). Nabízí se jen ročníky, ve kterých linie (na stránce vypravěče on) měla aspoň jednu kartu: dotaz na ročníky musí mít stejné podmínky jako `zFiltru` pod ním (`JEN_VIDITELNE`, `BEZ_DALSICH_KOL`), jinak by šel vybrat prázdný ročník (vypravěč, který vedl jen semifinále DrD). Letošní je v nabídce vždy, jiný nebo nesmyslný ročník (2020, budoucí) se tiše přepne na letošní. Ročník načítá PHP, přepnutí znovu načte stránku a filtr v adrese nechá. Co v tom ročníku není (štítek, vypravěč, den), se zahodí. Minulý ročník má rok v titulku stránky a v odkazu `#` na aktivitu a lišta ukazuje „Aktivity z ročníku …, Zpět na letošní“. Přihlašovátka, tlačítka týmů ani „Jen volná místa“ tam nejsou.
- **Den je programový (`denProgramu()`), stejně jako v programu:** noční hra po půlnoci (před `PROGRAM_ZACATEK`) patří k předchozímu dni (záměr, pořadatelé to tak chtějí). Rozpor: termín na kartě (`denCasSkutecny()`) ukazuje skutečný den, takže hra „pátek 0–3“ je pod filtrem „čtvrtek“.
- „Jen volná místa“ = aspoň jeden termín je přihlašovatelný a má místo. Přihlášenému se počítají jen místa pro jeho pohlaví, nepřihlášenému stačí místo pro kohokoli. Týmové aktivity se počítají po týmech jako v `obsazenostHtml()`. Tlačítko se ukáže, jen když je na stránce aspoň jeden přihlašovatelný termín (`lzePrihlasovat`). Jinak by vždy vrátilo nulu.
- Štítky se kombinují stejně jako v programu (`filtrujAktivity`): uvnitř jedné kategorie stačí kterýkoli vybraný, mezi kategoriemi musí platit všechny.
- **Vypravěči:** karta patří všem organizátorům ze všech svých termínů (u DrD a skupin má každý termín svého). Z vybraných stačí kterýkoli, s ostatními filtry se kombinují jako další kategorie. Výběr se ukáže od dvou vypravěčů na stránce. Hledání vypravěče najde i textem.
- Hledá se bez diakritiky a velikosti písmen, všechna slova musí v kartě být. Prohledává se text detailu (`#aktivity_nahledText{id}`: název, štítky, celý popis) a vypravěč z karty. Text se bere z DOM, není podruhé v JSON.
- Filtr je v adrese (`?hledat=…&stitky=1,2&vypravec=67&den=patek,sobota&volne=1`), takže jde poslat odkaz. Zapisuje se přes `replaceState`. Hodnoty, které na stránce nejsou (štítek jiné linie, den mimo termíny), se při načtení zahodí. Volba Karty / Seznam je osobní a ukládá se do `localStorage`.
- **Seznam:** jedna karta na řádek, malý obrázek vlevo, bez popisu. Vypravěč termínu stojí vpravo od času, ne pod ním (záměr: zbytečně by natahoval řádek).
- Menu štítků a vypravěčů sahá až ke spodku okna (záměr: kdo vybírá, nekouká na aktivity pod ním). Výška se počítá při otevření (`useMenuDoSpodkuOkna`), nejméně výchozích 300 px.

## Gotchas

- **Scroll po přihlášení:** `zachovejScroll` (šablona) obnovuje pozici po POSTu. Musí běžet až po schování karet, jinak skočí na špatné místo. Proto ho šablona volá na `DOMContentLoaded` a Preact karty schovává v `useLayoutEffect`. Bundle je `type="module"`, takže běží před `DOMContentLoaded`, a `useLayoutEffect` proběhne synchronně při `render()`.
- `odstranDiakritiku()` vytváří při každém volání transliterátor. Pro 177 termínů RPG to stálo asi 60 ms na stránku. Klíč dne se proto kešuje podle názvu dne (`$klicePodleNazvuDne`).
- Karta se schovává atributem `hidden`. `.aktivity_aktivita` má ale `display: inline-block`, které výchozí styl `[hidden]` přebije, proto je v `aktivity.less` vlastní pravidlo.
- **Části termínu jsou v šabloně zvlášť** (`aktivity_terminCas`, `…Obsazenost`, `…Prihlasit`, `…Vypravec`) a bez `&ensp;`. Seznam je řadí flexem přes `order`. `&ensp;` není sbalitelná mezera, takže by z něj byla anonymní flex položka, a ta má `order: 0` a skočila by na začátek. Mezery na kartách dává `margin-right`.
- Starší ročníky často nemají obrázek (soubor podle `url_akce`), šablona pak vypíše `<img src="">`. `.aktivity_obrazek[src=""]` je schovaný jako u DrD, jinak by byla vidět ikona rozbitého obrázku.
- **`prihlasovatelna()` nehlídá ročník**, jen stav a dnešní termín registrace. Ve starých ročnících zůstaly aktivity ve stavu AKTIVOVANA (v roce 2021 7, v letech 2010–2011 přes 200). Bez schování přihlašovátka by na `?rocnik=2021` během letošní registrace šlo opravdu se přihlásit na loňskou aktivitu. Stránka proto u minulého ročníku přihlašovátko nevykreslí. Ručně poskládaný POST by ale pořád prošel, protože `prihlas()` rok taky nekontroluje.
- Přihlašovátko je v `<div class="aktivity_terminPrihlasit">` s `display: inline`, ne ve `<span>`. Je v něm `<form>` a ten do `<span>` nesmí.
- Odkaz „Zpět na letošní“ skládá adresu až při kliknutí. Prvek se vytváří jednou v `AktivityFiltr` a Preact stejný vnode znovu nevykreslí, takže `href` by zůstal z načtení stránky a ztratil by filtr změněný potom.
- Šířka menu vypravěčů je v `AktivityFiltr.less` přes `classNamePrefix`, protože inline `styles` react-selectu neumí media query. Selektor potřebuje dvě třídy, jinak ho přebije třída od emotion.

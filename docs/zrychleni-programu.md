# Zrychlení API aktivityProgram

## Proč je starý PHP program rychlý a API pomalé

### Starý PHP program (`Program::tisk` + `Aktivita::zProgramu`)

- **1 průchod aktivitami** – renderuje HTML přímo při iteraci
- **In-process statická cache** (`self::$objekty`) – v RAM, nulový I/O overhead
- **Bulk load dat** – `prednacitat: true` načte přihlášení + uživatele 2–3 SQL dotazy pro celý ročník najednou
- Data se načtou jednou a zbytek requestu jsou jen čtení z RAM

### API (`aktivityProgram.php` + `Aktivita::zFiltru`)

- **5 samostatných průchodů** přes stejné aktivity (`aktivityNeprihlasen`, `aktivitySkryte`, `aktivityUzivatel`, `obsazenosti`, `popisy`)
- **File-based cache** (`TableDataDependentCache`) – JSON soubory na disku; pomalejší než RAM, plus write při každém cache miss
- `preloadTableDataVersions()` – DB dotaz při každém requestu kvůli invalidaci cache
- `aktivityNeprihlasen` a `aktivitySkryte` jsou téměř totéž – stejná iterace, stejná volání `organizatori()`, jiný filtr `viditelnaPro()`
- 4 samostatné cache klíče = 4 čtení/zápisů souborů, 4 nezávislé invalidace

---

## Navrhované úpravy

### 1. Sloučit 5 průchodů do 1 (`aktivityProgram.php`)

**Problém:** Každá z pěti closure iteruje `$aktivity` znovu. I když `prednacitat: true` plní statické cache při prvním průchodu, opakované iterace zbytečně volají PHP metody na každém objektu.

**Řešení:** Jeden průchod, který současně staví všechny 4 datasety:

```php
// místo 4 samostatných closure s foreach
$dotahniVse = function (DataSourcesCollector $dataSourcesCollector) use (&$aktivity, &$u, &$skryteAktivityViditelnePro) {
    Aktivita::organizatoriDSC($dataSourcesCollector);
    Aktivita::stavPrihlaseniDSC($dataSourcesCollector);
    Aktivita::soucinitelCenyAktivityDSC($dataSourcesCollector);
    Aktivita::obsazenostObjDSC($dataSourcesCollector);

    $aktivityNeprihlasen = [];
    $aktivitySkryte      = [];
    $aktivityUzivatel    = [];
    $aktivityObsazenost  = [];

    foreach ($aktivity as $aktivita) {
        $zacatek = $aktivita->zacatek();
        $konec   = $aktivita->konec();
        if (!$zacatek || !$konec) {
            continue;
        }

        $verejneViditelna    = $aktivita->viditelnaPro(null);
        $viditelnaProUzivatele = $aktivita->viditelnaPro($u);

        // obsazenost pro všechny bez ohledu na viditelnost
        $aktivityObsazenost[] = [
            'idAktivity' => $aktivita->id(),
            'obsazenost' => $aktivita->obsazenostObj($dataSourcesCollector),
        ];

        // organizatori se volají jen jednou, výsledek použijeme na oba datasety
        if ($verejneViditelna || $viditelnaProUzivatele) {
            $vypraveci = array_map(
                fn(Uzivatel $org) => $org->jmenoNick(),
                $aktivita->organizatori(dataSourcesCollector: $dataSourcesCollector),
            );
            $base = $this->sestavAktivituBase($aktivita, $vypraveci, $zacatek, $konec);

            if ($verejneViditelna) {
                $aktivityNeprihlasen[] = array_filter($base);
            } elseif ($viditelnaProUzivatele) {
                $aktivitySkryte[] = array_filter($base);
            }
        }

        // user-specific data
        if ($u && $viditelnaProUzivatele) {
            $aktivityUzivatel[] = array_filter(
                $this->sestavAktivituUzivatel($aktivita, $u, $dataSourcesCollector),
            );
        }
    }

    return compact('aktivityNeprihlasen', 'aktivitySkryte', 'aktivityUzivatel', 'aktivityObsazenost');
};
```

Výsledek se pak rozdělí do jednotlivých cache klíčů. Klíčový efekt: `organizatori()` se volá **jednou na aktivitu** místo dvakrát (za `neprihlasen` + za `skryte`).

---

### 2. Jeden kombinovaný cache klíč místo čtyř

**Problém:** 4 oddělené soubory = 4× file read/write, 4 nezávislé invalidace. Pokud se změní `akce_prihlaseni`, invaliduje se `aktivityUzivatel` i `obsazenosti` zvlášť, i když jejich data závisí na stejných tabulkách.

**Řešení:** Jeden cache záznam `aktivity_program_vsechna_rocnik_{rok}_{userId}` obsahující všechna data. DSC se nasbírá přes jeden průchod (viz bod 1), takže závislé tabulky jsou přesně ty, co se skutečně použijí.

```php
$cacheKey = 'aktivity_program_vsechna_rocnik_' . $rok . '_' . ($u?->id() ?? 'anonym');
$cachedItem = $tableDataDependentCache->getItem($cacheKey);

if (!$cachedItem) {
    $dsc  = $dataSourcesCollector->copy();
    $data = $dotahniVse($dsc);
    $tableDataDependentCache->setItem($cacheKey, $data, $dsc);
} else {
    $data = $cachedItem->data;
}
```

Výhody:
- Cache miss → 1 průchod, 1 zápis souboru (místo 4)
- Cache hit → 1 čtení souboru (místo 4)
- Invalidace je konzistentní – buď platí vše, nebo nic

Nevýhoda: User-specific data (`aktivityUzivatel`) jsou navázány na veřejná data v jednom souboru. Pokud je veřejný seznam invalidován, musí se přepočítat i user data.
→ Lze řešit splittem: jeden klíč pro veřejná data (`neprihlasen`, `skryte`, `obsazenosti`), druhý pro user data.

---

### 3. Předejít DB dotazu `preloadTableDataVersions()` na každý request

**Problém:** `preloadTableDataVersions()` (`TableDataDependentCache.php`, voláno v `aktivityProgram.php:65`) dělá SELECT z `_table_data_versions` při každém requestu, i když je cache validní.

**Řešení A:** Lazy loading – načítat verze tabulek až tehdy, když jsou skutečně potřeba (tj. při `getItem` a `setItem`), ne předem.

**Řešení B:** Short-lived in-process cache – verze tabulek jsou platné po dobu jednoho PHP requestu, takže `preload` stačí volat jednou a výsledek se sdílí přes statickou proměnnou, pokud je stejná instance cache použita vícekrát v rámci jednoho requestu. (Aktuálně je to tak, ale jen pokud `$tableDataDependentCache` je singleton – ověřit.)

---

### 4. Použít `Aktivita::zProgramu` místo `zFiltru` (pokud rok = aktuální ročník)

**Problém:** `zFiltru` nevkládá výsledky do `self::$objekty['ids']`, takže při opakovaném přístupu (v budoucnu, nebo při vícero requestech) nelze sdílet in-process cache.

**Řešení:** Pro dotaz na aktuální ročník použít `zProgramu(zCache: true)`:

```php
if ($rok === $systemoveNastaveni->rocnik()) {
    $aktivity = Aktivita::zProgramu(
        razeni: 'zacatek',
        zCache: true,
        prednacitat: true,
        systemoveNastaveni: $systemoveNastaveni,
    );
} else {
    $aktivity = Aktivita::zFiltru(
        systemoveNastaveni: $systemoveNastaveni,
        filtr: [FiltrAktivity::ROK => $rok],
        prednacitat: true,
        dataSourcesCollector: $dataSourcesCollector,
    );
}
```

`zProgramu` má navíc podmínku `a.stav != NOVA`, která odpovídá tomu, co API reálně potřebuje.

---

## Dopad úprav

| Úprava | Cache miss | Cache hit |
|---|---|---|
| Původní stav | 5× iterace, 4× file write, 1× DB (verze) | 4× file read, 1× DB (verze) |
| Po úpravě 1+2 | 1× iterace, 1× file write, 1× DB (verze) | 1× file read, 1× DB (verze) |
| Po úpravě 1+2+3 | 1× iterace, 1× file write | 1× file read |

Největší přínos na **cache miss** (první request po změně dat) – kde teď trvá API nejdéle.

---

## Soubory k úpravě

- `web/moduly/api/aktivityProgram.php` – hlavní logika průchodů a cachování
- `admin/scripts/api/aktivityProgram.php` – pravděpodobně analogická situace, stejné úpravy
- `model/Aktivita/Aktivita.php` – `zFiltru` (případně přidat `zCache` podporu)
- `model/Cache/TableDataDependentCache.php` – lazy loading verzí (úprava 3)

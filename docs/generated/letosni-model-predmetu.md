# Který předmět je „letošní model"

TL;DR: placka a kostka mají každý rok nový design, ale festival zároveň doprodává staré.
Report je musí rozlišit a dnes to dělá **heuristikou přes čtyři řadicí klíče**. Chceme to
nahradit příznakem na produktu. Tenhle dokument drží, proč a jak.

## Vstupní body

- `model/Shop/Predmet.php::letosniPredmet()` — heuristika sama (`letosniKostka()`, `letosniPlacka()`)
- `model/Report/BfsrReport.php` — dělí placky na `Ir-Placky-Letosni-*` a `Ir-Placky-Stare-*`
- `model/Uzivatel/Cenik.php:140,182` — kostka/placka zdarma se vztahuje jen na letošní model

## Co „letošní" znamená

**Právě jeden produkt na druh.** Ne „každý, co je letos v nabídce" — těch je víc, protože
se doprodávají starší designy pod letošním ročníkem.

Data pro placky v roce 2026:

| id | název | model_rok | je_letosni_hlavni | cena |
|---|---|---|---|---|
| **1846** | **Placka 2026** | 2026 | 1 | 40 |
| 1847 | Placka 2025 - Cesta časem | 2026 | 1 | 40 |
| 1903 | Placka 2021 - Cthulhu | 2026 | 1 | 40 |
| 1845 | Placka stará | 2026 | 1 | 20 |

Čtyři produkty, jeden z nich je letošní model. Rozdíl není kosmetický: záměna dělá
**12 nákupů ze 128** rozdíl v tom, co report hlásí jako letošní.

## Proč je dnešní řešení křehké

```sql
NOT EXISTS(… shop_nakupy … rok < :rocnik)   -- nikdo ho nekoupil dřív
ORDER BY model_rok DESC, je_letosni_hlavni DESC, cena_aktualni DESC, id_predmetu
```

Na datech výše **první tři klíče nerozhodnou nic** — všechny čtyři kandidáty mají shodný
`model_rok` i `je_letosni_hlavni` a cena odliší jen tu dvacetikorunovou. O vítězi mezi
`Placka 2026`, `Placka 2025 - Cesta časem` a `Placka 2021 - Cthulhu` tak rozhoduje
**`id_predmetu`, tedy pořadí nahrání**.

Navíc `je_letosni_hlavni` už není sloupec — kompatibilní pohled ho dopočítává jako
`archived_at IS NULL`, takže je `1` pro všech 103 letošních produktů. Jako řadicí klíč
nenese žádnou informaci.

Podmínka `NOT EXISTS` navíc znamená, že **produkt přestane být letošní v okamžiku, kdy si
ho někdo koupí v příštím ročníku** — tedy retroaktivně mění, co report o minulém roce řekne.

## Kam to chceme dotáhnout

Příznak na produktu místo hádání. Databáze umí vynutit „jen jeden na druh" i bez
filtrovaných indexů (MariaDB 10.11 je nemá):

```sql
ALTER TABLE shop_predmety
    ADD novy_model TINYINT(1) NULL,
    ADD UNIQUE KEY jeden_novy_model (druh_predmetu, novy_model);
```

**Funguje to proto, že NULL se v UNIQUE indexu neduplikuje**: starých designů může být
kolik chce (`NULL`), ale `1` smí být na druh jen jedna. Ověřeno na MariaDB 10.11:
pět řádků se dvěma druhy prošlo, druhý `('placka', 1)` skončil
`Duplicate entry 'placka-1' for key 'jeden_novy'`.

Co je potřeba rozmyslet, než se to udělá:

- **Podle čeho se druh pozná.** Dnes `kod_predmetu LIKE '%placka%'`; s příznakem je potřeba
  stabilní klíč — nabízí se tag (`placka`, `kostka`) místo podřetězce v kódu.
- **Kdo příznak nastavuje.** Admin při zakládání nového modelu; migrace ho musí doplnit
  zpětně podle dnešní heuristiky, ať se čísla v reportech nezmění.
- **Historie.** Report se ptá i na minulé ročníky, takže příznak musí být per ročník, ne
  jeden na produkt napříč lety — nebo se musí vázat na `archived_at`.

Do té doby heuristika zůstává; je ověřená testy (`BfsrReportPlackyTest`) a po převodu na
novou strukturu čte z pohledu `shop_predmety_s_typem`, protože `typ`, `model_rok` ani
`je_letosni_hlavni` už sloupce nejsou.

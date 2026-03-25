# Instance vs. Dítě aktivity (brát s rezervou)

Tabulka `akce_seznam` (alias `aktivita_seznam`) používá dva nezávislé hierarchické mechanismy — **instance** a **dítě** — které slouží různým účelům. Jsou to úplně odlišné koncepty, oba mohou existovat zároveň.

---

## 1. Instance (`patri_pod` + tabulka `akce_instance`)

### Co to je

Instance je **časová varianta** téže aktivity. Jedna "hlavní" aktivita existuje jako vzor (URL, popis, vybavení), a pak z ní vznikají instance na různé termíny.

### Databázová struktura

```
akce_seznam:
  patri_pod  BIGINT UNSIGNED NULL  → FK na akce_instance.id_instance

akce_instance:
  id_instance    BIGINT UNSIGNED PK
  id_hlavni_akce BIGINT UNSIGNED    → FK na akce_seznam.id_akce (hlavní aktivita)
```

- `patri_pod IS NULL` → aktivita je samostatná nebo **hlavní**
- `patri_pod = 5` → aktivita je instancí skupiny č. 5

Hlavní aktivita sama **nemá** `patri_pod` — je identifikována přes `akce_instance.id_hlavni_akce`.

### Příklad v DB

```
Judo (ID=100, patri_pod=NULL)  ← hlavní aktivita (vzor)
akce_instance(id=5, id_hlavni_akce=100)
  ├─ Instance červen   (ID=101, patri_pod=5, zacatek='2026-06-01')
  ├─ Instance červenec (ID=102, patri_pod=5, zacatek='2026-07-01')
  └─ Instance srpen    (ID=103, patri_pod=5, zacatek='2026-08-01')
```

### Klíčové metody (`model/Aktivita/Aktivita.php`)

| Metoda | Popis |
|--------|-------|
| `patriPod(): ?int` | ID instanční skupiny (nebo NULL) |
| `patriPodAktivitu(): ?Aktivita` | Hlavní/mateřská aktivita |
| `idHlavniAktivity(): int` | ID hlavní aktivity ve skupině |
| `jeHlavni(): bool` | `$this->idHlavniAktivity() === $this->id()` |
| `jeInstance(): bool` | `!$this->jeHlavni()` |
| `instancuj(): Aktivita` | Vytvoří novou instanci (kopii) |
| `pocetInstanci(): int` | Počet dalších instancí ve skupině |
| `instance(): Aktivita[]` | Všechny aktivity ve skupině (private) |

### Editace instancí

Při editaci instance se data rozdělují:
- `url_akce`, `popis`, `vybaveni` → mění se u **hlavní** aktivity (sdíleno všemi instancemi)
- `zacatek`, `konec` → mění se jen u **aktuální** instance

Viz `model/Aktivita/Aktivita.php` ~řádky 1211–1233.

### Kdy použít

Když stejná aktivita probíhá vícekrát (různé termíny), ale sdílí popis, URL a vybavení.

---

## 2. Dítě (`dite` — CSV sloupec)

### Co to je

Dítě je **logická navazující aktivita** — typicky "další kolo" nebo "skupina variant", ze které si účastník vybírá. Tvoří sekvenci nebo strom aktivit.

### Databázová struktura

```
akce_seznam:
  dite  VARCHAR(64) NULL   -- "potomci odděleni čárkou"
```

- `dite IS NULL` → aktivita nemá potomky
- `dite = '101,102,103'` → aktivita má 3 potomky
- Není cizí klíč — prostý text, parsovaný v PHP

### Příklad v DB

```
LKD (ID=50, dite='101,102,103')  ← rodič
  ├─ Kolej A (ID=101, dite=NULL)
  ├─ Kolej B (ID=102, dite=NULL)
  └─ Kolej C (ID=103, dite=NULL)

DrD kolo 1 (ID=200, dite='301,302')  ← kolo 1 má jako děti varianty kola 2
  ├─ DrD kolo 2 varianta X (ID=301, dite=NULL)
  └─ DrD kolo 2 varianta Y (ID=302, dite=NULL)
```

### Klíčové metody (`model/Aktivita/Aktivita.php`)

| Metoda | Popis |
|--------|-------|
| `deti(): Aktivita[]` | Vrátí Aktivita objekty všech dětí |
| `detiIds(): int[]` | Pole ID dětí |
| `detiDbString(): ?string` | Raw CSV string z DB |
| `maDite(int $id): bool` | Je dané ID mezi dětmi? |
| `rodice(): Aktivita[]` | Aktivity, které mají tuto jako dítě |
| `pridejDite(int $id)` | Přidá ID do CSV |
| `dalsiKola(): array` | Rekurzivní strom všech dalších kol |

### Kaskádové efekty

- **Přihlašování**: Kontroluje se, zda není přihlášen na sourozence (aktivity se stejným rodičem)
- **Odhlašování**: Odhlášení z rodiče automaticky odhlásí z dětí

Viz `Aktivita.php` ~řádky 1905–1906 a 2389–2393.

### Kdy použít

Když aktivita má navazující kola nebo varianty, ze kterých si účastník vybírá jednu.

---

## 3. Srovnání

| Vlastnost | Instance (`patri_pod`) | Dítě (`dite`) |
|-----------|------------------------|---------------|
| Uložení | FK na `akce_instance` | CSV string v `dite` |
| Účel | Časové varianty (termíny) | Sekvence / varianty výběru |
| Sdílené | URL, popis, vybavení | — (každé dítě je samostatné) |
| Rozdílné | Začátek, konec | — |
| Přihlašování | Každá instance zvlášť | Kaskáda přes rodiče/děti |
| Editace | Split mezi hlavní a instanci | Děti editovány samostatně |
| Příklady | "Judo v různých termínech" | "LKD koleje", "DrD kola" |
| DB integrita | Zahraniční klíč | Žádná |

---

## 4. Klíčové soubory

- `model/Aktivita/Aktivita.php` — veškerá business logika
- `model/Aktivita/SqlStruktura/AkceSeznamSqlStruktura.php` — konstanty `PATRI_POD`, `DITE`
- `migrace/000.php` ~řádky 1237–1287 — definice `akce_instance` a sloupců

# Instance aktivity (brát s rezervou)

Tabulka `akce_seznam` (alias `aktivita_seznam`) používá hierarchický mechanismus **instancí** pro časové varianty téže aktivity.

---

## Instance (`patri_pod` + tabulka `akce_instance`)

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

## Klíčové soubory

- `model/Aktivita/Aktivita.php` — veškerá business logika
- `model/Aktivita/SqlStruktura/AkceSeznamSqlStruktura.php` — konstanta `PATRI_POD`
- `migrace/000.php` ~řádky 1237–1287 — definice `akce_instance` a sloupců

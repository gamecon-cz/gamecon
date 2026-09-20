# Diferenční scénáře e-shopu — časová osa ročníku 2026

Praktické provedení plánu z [[eshop-diferencni-overeni]]. Každý scénář je vlastní soubor:
co nastavit, co udělat, a co se má na obou větvích shodovat.

## Sestava

| | worktree | větev | web | DB port |
|---|---|---|---|---|
| **A — legacy** | `gamecon-legacy-reference` | `origin/main` (detached) | <http://localhost:18040> | 15440 |
| **B — nový** | `gamecon-1274-ubytovani` | `1274-přepsat-e-shop` | <http://localhost:18020> | 15420 |

Přihlášení na obou: libovolný účet, heslo `admin` (`UNIVERZALNI_HESLO`).

Obě DB nasazené **ze stejného dumpu ostré**, migrace pak doběhly na každé větvi zvlášť.
Podrobnosti a past, do které se dá spadnout, jsou v poznámce `project_eshop_differential_setup`.

## Reset mezi scénáři

> **Pouštěj `reset.sh` před KAŽDÝM během, i před opakováním téhož scénáře.** Scénáře
> zapisují do e-shopu a bez resetu se zápisy nasčítají — pak to vypadá, že se větve liší
> v datech, přestože se liší jen historie klikání. Stálo to jednou hodinu. Reset je 9 s.

```bash
bin-diff/reset.sh            # obě DB zpět na výchozí stav (ze stejného dumpu)
bin-diff/cas.sh 2026-05-12   # posunout „teď" na obou větvích
bin-diff/porovnej.sh         # sémantický diff dat mezi A a B
bin-diff/porovnej-ceny.sh    # ceny: tentýž Cenik::cena() na obou větvích, 6 rolí × 40 předmětů
bin-diff/testy.sh            # phpunit s pojistkou: odmítne běžet s posunutým časem
```

> **⚠️ Posunutý čas rozbíjí testy — před `bin/phpunit.sh` ho vždycky vrať.**
> `GAMECON_DIFF_DATUM` se propisuje do PHP konstant přes `auto_prepend_file`, takže platí
> pro **každý** běh PHP v tom kontejneru, testy včetně. `tests/Model/Cas/DateTimeGameconTest.php`
> počítá právě s těmi konstantami a s posunutým časem dá 5 selhání; `SystemoveNastaveniTest`
> další. Vypadá to jako rozbitý `main`, a není.
>
> Pojistka je v `bin-diff/testy.sh` — pouštěj testy přes něj a s posunutým časem ti
> rovnou řekne, ať ho nejdřív vrátíš (exit 1), místo aby tě nechal číst falešnou červenou.
>
> ```bash
> bin-diff/testy.sh         # místo bin/phpunit.sh, dokud běží scénáře
> bin-diff/cas.sh --ukaz    # < skutečný čas > = můžeš testovat i napřímo
> bin-diff/cas.sh --vrat
> ```
>
> Naletěl jsem na to 2026-09-18: nahlásil jsem „date-drift na mainu" a chtěl to opravovat
> PR do `main`, přestože `main` byl celou dobu zelený. Ověřeno oběma směry — s posunem
> `Tests: 42, Failures: 5`, po `--vrat` `OK (42 tests)`.

> **Pořadí `reset.sh` a `cas.sh --vrat`:** `cas.sh` si původní `nabizet_do` schovává do
> tabulky `shop_predmety_nabizet_do_zaloha`, jenže `reset.sh` obnoví DB ze snapshotu, ve
> kterém ta tabulka ještě nebyla — a tím ji zahodí. Není to problém (`reset.sh` vrátí
> i `nabizet_do`), jen `--vrat` pak nemá z čeho obnovovat; skript to od 2026-09-18 přežije.
> **Čas ale vracej před resetem**, ať nezůstane viset `GAMECON_DIFF_DATUM` v `.env`.

`reset.sh` obnovuje ze **snapshotu pořízeného po migracích**, ne z původního dumpu — je to
řádově rychlejší (nenahrává 33MB dump a nepouští na něm 23 eshopových migrací) a vrací přesně
ten stav, ve kterém scénáře začínají. Snapshot se vytvoří sám při prvním běhu.

## Časová osa (skutečné hodnoty z `systemove_nastaveni`)

| datum | klíč | co se děje |
|---|---|---|
| 2026-05-01 23:59:59 | `HROMADNE_ODHLASOVANI_3` | třetí hromadné odhlašování |
| **2026-05-13 20:26** | `REG_GC_OD` | **otevření registrace na GC** |
| 2026-05-20 20:26 | `PRVNI_VLNA_KDY` | první vlna přihlašování na aktivity |
| 2026-06-10 20:26 | `DRUHA_VLNA_KDY` | druhá vlna |
| 2026-06-15 | `MIKINY_LZE_OBJEDNAT_A_MENIT_DO_DNE` | konec prodeje mikin |
| 2026-07-15 | `PREDMETY_BEZ_TRICEK_…_DO_DNE` | konec prodeje merche |
| **2026-07-19** | `UBYTOVANI_…` + `JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE` | **konec prodeje ubytování a jídla** |
| 2026-07-19 23:59:59 | `HROMADNE_ODHLASOVANI_2` | druhé hromadné odhlašování |
| **2026-07-23 12:00** | `GC_BEZI_OD` | **začátek GameConu** |
| 2026-07-26 22:00 | `REG_GC_DO` | konec registrace |
| **2026-07-26 23:59:59** | `GC_BEZI_DO` | **konec GameConu** |
| 2026-08-01 | `TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE` | konec prodeje triček |

**Nález rovnou při sestavování:** `TRETI_VLNA_KDY` je `2024-07-01`, tedy o dva ročníky pozadu.
Netýká se e-shopu, ale stojí za prověření — viz `nalezy.md`.

## Scénáře

| # | soubor | „teď" | těžiště |
|---|---|---|---|
| 1 | [01-pred-otevrenim.md](01-pred-otevrenim.md) | 2026-05-12 | zavřený e-shop, informace o termínu |
| 2 | [02-otevreni-registrace.md](02-otevreni-registrace.md) | 2026-05-13 20:30 | první nákup |
| 3 | [03-bezny-nakup.md](03-bezny-nakup.md) | 2026-06-01 | ubytování, jídlo, merch, tričko |
| 4 | [04-hranicni-ubytovani.md](04-hranicni-ubytovani.md) | 2026-06-01 | jedna noc, nenavazující noci, plno, spacák |
| 5 | [05-role-a-ceny.md](05-role-a-ceny.md) | 2026-06-01 | vypravěč, organizátor, partner, brigádník |
| 6 | [06-konec-prodeje.md](06-konec-prodeje.md) | 2026-07-20 | účastník nesmí, pult musí |
| 7 | [07-infopult-a-sef.md](07-infopult-a-sef.md) | 2026-07-24 | pult, šéf pultu, přeplnění |
| 8 | [08-po-gc.md](08-po-gc.md) | 2026-08-02 | zmrazená data |

## Jak zapisovat nálezy

Všechno do [nalezy.md](nalezy.md), jeden řádek na rozdíl, se zařazením:

- **záměrné** — víme o tom a je to tak chtěné (patří do popisu PR / na kartu)
- **chyba** — na opravu
- **nezjištěno** — zatím nevíme; nenechávat viset

Rozdíl **není automaticky chyba.** Ale nezařazený rozdíl je vždycky dluh.

## Ověřování: nejdřív kódem, nakonec prohlížečem

Programové ověření (volat `Cenik::cena()`, `MealWriter`, `AccommodationWriter` přímo a
diffnout obě větve) je rychlé a přesné na **výpočty** — ušetří spoustu klikání. Neověří ale,
že se komponenta vykreslí, že kliknutí něco udělá a že dvě rychlá kliknutí za sebou
nepřepíšou jedno druhé.

**Každý scénář má nakonec projít i Playwrightem**, aby bylo jisté, že to jde ovládat a je to
vidět v prohlížeči. Postup a pasti: `bin-diff/playwright/README.md`.

První takový průchod (2026-09-18) potvrdil, že na nové větvi se na `/prihlaska` vykreslí
**všech pět Preact sekcí** — merch, svršky, ubytování, jídlo, vstupné — s reálnými daty.


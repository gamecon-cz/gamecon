# Diferenční ověření e-shopu proti legacy

TL;DR: než e-shop uvidí první člověk, musí se **bok po boku** porovnat starý a nový stav —
dva worktree, dvě databáze, stejné scénáře, a porovnat **co se zapsalo do DB, jak se chová GUI
a co říkají adminní reporty**. Zelené testy to nenahrazují: ověřují, co jsme napsali, ne že se
to shoduje s tím, co dělal starý e-shop. *(záměr — zadáno uživatelem 2026-09-16)*

## Proč to nejde odbýt testy

Suite má 1398 zelených testů a přesto během převodu prošlo několik rozdílů, které odhalilo až
porovnání s legacy — namátkou: `letosniPredmet()` vybíral jiný produkt než `main` (12 nákupů
ze 128 jinak), přeplnění kapacity nikdo nehlídal, a `null` u spolubydlícího mazal data.
Všechny tři by „zelená" nezachytila, protože test ověřuje naši představu, ne shodu se starým
chováním.

## Sestava

Dva worktree se samostatnými DB a porty (viz `parallel-worktree` skill pro přidělení slotu):

| | větev | role |
|---|---|---|
| A | `main` | referenční chování, „jak to bylo" |
| B | `1274-přepsat-e-shop` | nový stav, „jak to je" |

**Obě naplnit ze stejného dumpu ostré databáze**, ne z migrací — teprve na reálných datech se
projeví věci jako collation nebo produkty z minulých ročníků. Na B pak doběhnou migrace
(23 jich mezi větvemi je), takže se schémata rozejdou.

## Past: schémata nejsou stejná

Mezi větvemi je **23 migrací**. Kompatibilní pohled `shop_predmety_s_typem` sice dopočítává
zrušené sloupce (`typ`, `model_rok`, `je_letosni_hlavni`), ale platí:

**Nelze porovnávat `SELECT *` po sloupcích.** Porovnávat se musí **sémanticky** — kdo má co
koupené, za kolik, v jakém ročníku a co z toho plyne pro finance:

- `shop_nakupy`: dvojice (uživatel, předmět, rok, cena) — nová vrstva navíc plní
  `variant_id`, `order_id` a snapshot sloupce, které na A neexistují
- `uzivatele_hodnoty`: `ubytovan_s`, `nechce_ubytovani` — sem obě větve zapisují stejně
- finanční souhrn účastníka (`Finance`) a adminní reporty — tam se rozdíl projeví nejdřív

## Scénáře, celý životní cyklus ročníku

Od „registrace ještě neotevřená" po „GameCon skončil a data se už nemění". U každého projít
přihlášku, admin Uživatele, Infopult a reporty:

1. **Před otevřením registrace** — přihláška se nesmí dát odeslat ani na A, ani na B
2. **Registrace otevřená, účastník nepřihlášen na GC** — co je vidět, co jde koupit
3. **Běžný účastník** — ubytování, jídlo, merch, tričko, vstupné; objednat, změnit, zrušit
4. **Hraniční ubytování** — jedna noc bez práva, nenavazující noci, plná noc, spacáky
5. **Role s výjimkami** — vypravěč, organizátor, partner, infopulťák (jiné ceny i práva)
6. **Po termínu prodeje** — účastník nesmí, obsluha v adminu musí
7. **Prodej na pultu (KFC)** a **ruční prodej v adminu**
8. **Storno a jeho zrušení**
9. **Po skončení GC** — data zmrazená, nic se nesmí dát změnit

## Co u každého scénáře porovnat

1. **Data v DB** — dotazem, ne okem; u obou větví stejný dotaz nad sémantickým průmětem
2. **GUI** — chová se stejně? Co je vidět, co je zamčené, co hlásí za chybu
3. **Reporty v adminu** — BFGR, přehled financí účastníka, ubytování, stravenky

Rozdíl **není automaticky chyba** — část jich je záměrná (hlídání kapacity na serveru, zrušený
převod bonusu). Každý nalezený rozdíl se musí zařadit: *záměrná změna*, *chyba*, nebo
*nezjištěno*. Záměrné patří do poznámek u karty, chyby na opravu.

## Než se to spustí

- Pořadí kroků a co ještě chybí v adminu drží [[admin-objednavky-za-ucastnika]].
- Samotné porovnání dává smysl až bude admin převedený — jinak se porovnává poloviční stav.
- Na A i B musí být **stejný „dnešek"** (`GC_BEZI_OD`/`GC_BEZI_DO`, termíny prodeje), jinak se
  scénáře rozjedou kvůli datu a ne kvůli kódu.

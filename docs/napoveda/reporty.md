# Reporty

Stránka **Reporty** je centrální místo, odkud si stáhneš data z GameConu — seznamy účastníků, přehledy financí, maily pro rozesílky, podklady pro program a podobně. Vidí ji organizátoři s právem na administraci reportů; některé jednotlivé reporty jsou navíc omezené na další právo (poznáš je podle ikony 🫥 — po najetí myší se dozvíš, jaké právo je potřeba).

Reporty **nejsou optimalizované na rychlost**. Počítej s tím, že větší report (zvlášť finanční přehledy přes všechny účastníky) se může generovat i desítky sekund. Neklikej opakovaně — jen bys server zatížil víc.

## Jak report stáhnout

U každého reportu v seznamu jsou odkazy na dostupné formáty:

| Formát | Co se stane | Na co se hodí |
|--------|-------------|---------------|
| **xlsx** | Stáhne se soubor pro Excel / LibreOffice / Google Sheets. Čísla jsou v něm rovnou jako čísla, takže můžeš hned filtrovat a sčítat. | Další zpracování dat, sdílení s ostatními. |
| **html** | Report se otevře jako tabulka v nové záložce prohlížeče. | Rychlé nakouknutí bez stahování souboru. |

Ne každý report nabízí oba formáty — čistě grafické reporty bývají jen v html, některé exporty naopak jen v xlsx.

U každého reportu je také ikona 📊 — po najetí myší ukáže, **kdo report naposledy použil, kdy a kolikrát byl celkem použit**. Hodí se, když si nejsi jistý, jestli report ještě někdo potřebuje, nebo chceš vědět, s kým řešit jeho obsah.

Nad seznamem je pole pro **omezení reportu na jediného uživatele** — začni psát přezdívku, jméno, příjmení, e-mail nebo ID a vyber člověka z našeptávače. Odkazy na reporty se pak upraví tak, aby report počítal jen s vybraným uživatelem. Využívají to hlavně souhrnné (finanční) reporty; reporty, které s filtrem nepočítají, ho ignorují.

## Přehled důležitých reportů

Přesný seznam se v čase mění (a liší se podle tvých práv), tohle jsou ty nejpoužívanější:

### Účastníci a aktivity

- **Historie přihlášení na aktivity** — kdo se kdy na jakou aktivitu přihlásil či odhlásil.
- **Účastníci a počty jejich aktivit** — kolik aktivit má každý účastník.
- **Graf rozložení rozmanitosti her** — grafický přehled, jak pestré programy si lidé skládají.
- **Duplicitní uživatelé** — kandidáti na sloučení účtů (stejná jména, e-maily…).
- **Počty rolí platných v ročnících** — kolik lidí mělo jakou roli v jednotlivých ročnících.

### Finance

- **Finance: Zůstatky všech účastníků** — kdo má kolik zaplaceno / kolik dluží.
- **Finance: Neplatiči k odhlášení** a **Finance: Odhlášené objednávky neplatičů** — podklady pro řešení neplatičů.
- **Finance: E-shop** — přehled objednávek z e-shopu (vyžaduje právo na administraci financí).
- **Finance: Rozpočtový report** a **Sirienův rozpočtový report** — souhrnné rozpočtové podklady.
- **BFGR report** — velký celkový finanční report; odkaz z této stránky vede na stránku Finance, kde se generuje.
- **Udělené slevy** a **Finance: Aktivity bez slev** — kontrola slev.
- **Finance: Příjmy a výdaje infopulťáka** — pokladní přehled pro infopult.

### Maily a rozesílky

- **Maily – přihlášení na letošní GC** — e-mailové adresy letos přihlášených.
- **Maily – letošní vypravěči** — adresy letošních vypravěčů.
- **Přihlášení k odběru newsletterů** — kdo si přeje dostávat novinky.
- **Zázemí & Program: Emaily na účastníky / vypravěče dle linií** — adresy rozdělené podle programových linií.

### Zázemí a program

- **Zázemí & Program: Časy a umístění aktivit**, **Přehled místností**, **Zařízení místností** — podklady pro plánování prostor.
- **Zázemí & Program: Seznam účastníků a triček** — velikosti triček pro objednávku.
- **Zázemí & Program: Potvrzení pro návštěvníky mladší patnácti let** — kdo potřebuje potvrzení od rodičů.
- **Nepřihlášení a neubytovaní vypravěči + další** — koho z vypravěčů je potřeba ještě „dohnat".
- **Stravenky uživatelů** a **Stravenky (bianco)** — tisk stravenek.

### Infopult

- **Infopult: Balíčky účastníků** — co má kdo připraveno k vyzvednutí.
- **Infopult: Nezkontrolované potvrzení rodičů** — u koho ještě chybí kontrola potvrzení.
- **Infopult: Účastníci aktivit bez průchodu infopultem** — kdo je na aktivitě, ale neprošel odbavením.

Názvy reportů někdy obsahují letošní rok — reporty pracují vždy s aktuálním ročníkem, pokud z názvu neplyne jinak (např. historie napříč ročníky).

## Quick reporty

Pod univerzálními reporty je sekce **Quick reporty** — jednorázové reporty, které si organizátoři vytvářejí sami. Je to **pokročilá funkce pro lidi, kteří umí SQL**: quick report je pojmenovaný vlastní dotaz do databáze, jehož výsledek se pak dá stahovat stejně jako u běžných reportů (html / xlsx).

- Nový quick report založíš přes stránku **Přidat quick report**; existující upravíš tlačítkem **upravit** u reportu v seznamu.
- Dotaz smí data **jen číst** — pokusy o zápis či mazání systém odmítne a dotaz se při ukládání rovnou zkontroluje, jestli funguje.
- V dotazu můžeš použít zástupky `{ROK}` nebo `{ROCNIK}` — nahradí se aktuálním ročníkem, takže report zůstane platný i příští rok.
- Po uložení se u reportu objeví odkaz **Vyzkoušet** pro rychlou kontrolu výsledku.

**Pozor:** jak varuje i samotná stránka, quick reporty „samy náhodně mizí a nelze tomu zabránit". Ber je jako pomůcku na jedno použití — pokud report potřebuješ dlouhodobě a spolehlivě, nech si ho převést mezi univerzální reporty (domluv se s vývojáři).

## Na co si dát pozor

- **Rychlost** — reporty nejsou stavěné na časově kritické použití. Velké reporty generuj s předstihem, ne pět minut před poradou.
- **Aktualita** — report je snímek dat v okamžiku stažení. Přihlášky i platby se mění průběžně, stažený soubor rychle zastarává.
- **Osobní údaje** — reporty obsahují jména, e-maily i finanční údaje účastníků. Stažené soubory nešiř mimo organizátory a po použití je smaž.
- **Quick reporty nejsou trvalé** — viz výše; na nic důležitého se na ně nespoléhej.

# Infopult

Infopult je hlavní obrazovka pro práci s účastníky přímo na festivalu: přihlášení na GameCon na místě, odbavení příjezdu a výdej materiálů, připsání platby v hotovosti, úprava ubytování a jídla, prodej předmětů a rychlé založení účtu novému příchozímu.

## Výběr účastníka (omnibox vlevo nahoře)

Skoro všechno na Infopultu se točí kolem **pracovního uživatele** — účastníka, kterého si vybereš v poli vlevo nahoře. Napiš začátek přezdívky, jména, příjmení, e-mailu nebo ID (zkratka **alt+U**) a vyber ho z našeptávače. Dokud nikoho nevybereš, Infopult ukazuje jen výzvu „Vyberte uživatele (pole vlevo nahoře)".

Po výběru vidíš vlevo nahoře jeho přezdívku, jméno, ID a stav. Práci s ním ukončíš tlačítkem **zrušit**; pod polem se pak nabízejí odkazy ↻ na naposledy otevřené účastníky. Odkaz s ikonou řetězu / kopírování ti dá URL, kterou můžeš poslat kolegovi — otevře mu stejného účastníka.

Pokud jsi infopulťák (a ne šéf Infa), při klepnutí na **zrušit** tě systém může zastavit potvrzovací hláškou „Účastník … Přesto ukončit práci s uživatelem?", když má účastník nedoplatek, chybí mu potvrzení od rodičů, formulář cizince nebo nemá kompletní či zkontrolované osobní údaje. Nejdřív to s ním dořeš.

## Horní tlačítka — stav účastníka na GameConu

| Tlačítko | Kdy je aktivní | Co udělá |
|----------|----------------|----------|
| **Přihlásit** | účastník není přihlášen na GC a registrace běží | přihlásí ho na letošní GameCon |
| **Přijel(a) a Dát materiály** | je přihlášen, ale ještě nedorazil | označí ho jako přítomného na GC |
| **Odjel(a) z GC** | je přítomen a ještě neodjel | označí ho jako odjetého (potvrzuje se dotazem „Opravdu odjel(a)?") |
| **Odhlásit z GC** | je přihlášen, ale nedorazil; **jen správce financí** | úplně ho odhlásí z GameConu |

U tlačítka **Přijel(a) a Dát materiály** je kontrolní seznam — před odkliknutím zkontroluj, že proběhlo: předání trička, placky, stravenek, číslo pokoje, srovnání nedoplatku; vysvětlení last moment přihlašování; doplnění chybějících údajů (adresa, telefon…); vyplnění čísla OP. Infopulťákovi navíc vyskočí potvrzení „… Přesto dát materiály?", pokud má účastník nedoplatek nebo nekompletní údaje/dokumenty.

U tlačítka **Odjel(a) z GC** zkontroluj: vyrovnaný nedoplatek a vrácený klíč od pokoje.

**Pozor — nevratná akce:** **Odhlásit z GC** se ptá „Trvale odhlásit uživatele z GameConu a smazat všechny jeho aktivity a nakoupené věci?" — přesně to se stane. Používej jen po rozmyslu; tlačítko funguje pouze správci financí.

Pokud účastník není přihlášen na GC, ukazuje se červená hláška „Uživatel(ka) není přihlášen(a) na GameCon." Před spuštěním registrací navíc „Registrace na GameCon není spuštěna." — pak nejde na místě přihlašovat nikoho.

## Přehled

- **Stav účtu** — zůstatek účastníka; záporný (dluh) svítí červeně. Tlačítko **🗘 Fio** znovu stáhne platby z banky — hodí se, když účastník tvrdí, že „právě zaplatil mobilem". Když se stažení nezdaří, objeví se ⚠️ s hláškou „Stahování plateb z Fio se nezdařilo. Zkus to prosím za chvíli znovu."
- **Poznámka** — interní poznámka k účastníkovi, ulož tlačítkem **uložit**.
- **Potvrzení** — jen u účastníků, kterým na začátku letošního GameConu ještě nebylo 15 let. Vidíš „má potvrzení od rodičů" nebo „chybí potvrzení od rodičů!"; zaškrtnutím políčka a uložením potvrdíš, že papírové potvrzení máte. Pokud rodiče potvrzení nahráli elektronicky, je tu „odkaz na potvrzení".
- **Cizinec** — u ubytovaných účastníků s jiným než českým občanstvím koleje vyžadují registrační formulář cizince. Opět „má formulář cizince" / „chybí formulář cizince!", zaškrtni a ulož, až ho vyplní.
- **Údaje** — souhrn stavu osobních údajů: „chybí údaje", „zkontrolovat údaje", „údaje v pořádku" nebo „údaje kompletní".
- **Kontakt** — telefon účastníka.
- **Balíček** — co má účastník předplacené k výdeji (tričko, kostky, placka…, případně „jen stravenky"); najetím myší se rozbalí přesný seznam. U brigádníka připomene „papír na bonus ✍️" — nech podepsat převzetí bonusu.
- Odkazy **Program** a **Program účastníka** vedou na jeho osobní program.

## Osobní údaje

Tabulka osobních údajů (jméno, adresa, datum narození, e-mail, telefon…). Platí „Pro úpravu klikni na údaj" — údaj se změní na políčko, přepiš a ulož. U ubytovaných je potřeba údaje zkontrolovat proti dokladu (viz stav „zkontrolovat údaje" v Přehledu).

## Připsat platbu

Formulář pro hotovost (nebo ruční opravu):

1. Vyplň **Částku** v Kč a případně **Poznámku**.
2. Klikni **Připsat**. Kdo platbu připsal, se ukládá automaticky (pole „Připsal(a)").

- **Záporná částka** (vrácení peněz) jde zadat, ale **poznámka je pak povinná** — jinak tě zastaví hláška „Pro zápornou platbu je poznámka povinná".
- Když připíšeš platbu účastníkovi nepřihlášenému na GC, projde to, ale dostaneš varování „Platba připsána uživateli, který není přihlášen na Gamecon".
- Tlačítko **QR platby** otevře okno s QR kódy pro platbu mobilem — přepínač **CZ** (domácí převod v CZK), **SK** (Pay by Square) a **SEPA** (EUR převod). Účastník si kód naskenuje a zaplatí ze svého bankovnictví; platba se pak spáruje automaticky, nic nepřipisuj ručně.
- Pole „ID Fio pohybu" a „Datum platby" se ukazují jen na testovacím prostředí, na ostré je neuvidíš.

## Ubytování a Nastavení pokojů

Sekce **Ubytování** ukazuje pokoj, spolubydlící (se jmény a telefony), objednané ubytování a případně „Nechce ubytování". V tabulce nocí můžeš ubytování upravit a uložit tlačítkem **Uložit**. Pozor: „Zrušit jiné ubytování než neděli může pouze šéf Infa."

Sekce **Nastavení pokojů**:

- **Vypsat** — zadej číslo pokoje a uvidíš, kdo v něm bydlí a jestli už dorazil (zeleně „dorazil", červeně „nedorazil").
- **Přidělit** — přidělí vybranému účastníkovi zadaný pokoj. Pozor, „přepíše stávající stav".

## Jídlo uživatele

Zaškrtávací přehled objednaných jídel po dnech, uloží se tlačítkem **Uložit**. Platí pravidla ze samotné obrazovky: „Zrušit jídlo je možné jenom oproti vrácené stravence. Při objednání jídla je naopak potřeba předat stravenku (a zkontrolovat stav financí). Snídaně nabízíme jen organizátorům a vypravěčům." Odkaz **Tisk stravenek** otevře stravenky účastníka k vytištění.

## Objednávky

Seznam nakoupených předmětů a jídel účastníka. Kdo má právo rušit nákupy, vidí u položek (včetně vstupného a dobrovolného vstupného) ikonu koše — **zrušení objednávky** se potvrzuje dotazem „Opravdu zrušit objednávku …". Rozmysli si to: zrušená položka zmizí z účtu účastníka a změní jeho zůstatek.

## Prodej

Velké tlačítko **Prodej** otevře obchod, kde vybranému účastníkovi prodáš předměty na místě (trička, kostky…). Prodané kusy se připíšou na jeho účet. Detaily k nákupům a financím účastníka najdeš v kapitole Karta uživatele.

## Čip

Podstránka **Čip** (odkaz „Čip 🏷️" v Přehledu u Balíčku) je rozpracovaná stránka pro párování NFC čipů s účastníkem. Zatím nabízí testovací čtení a zápis čipu („Test NFC Read" / „Test NFC Write") a ukazuje, jestli to tvé zařízení zvládne: čtení i zápis vyžadují zabezpečené (https) spojení a prohlížeč s podporou NFC — použij **Chrome na Android telefonu s NFC**. Jinak stránka napíše „Tvůj prohlížeč bohužel neumí číst z NFC čipu". Bez vybraného pracovního uživatele stránka jen vyzve „Vyber pracovního uživatele". Odkaz na Čip se zatím ukazuje jen na vývojovém prostředí, na ostrém webu ho neuvidíš.

## Rychloregistrace

Když **nemáš** vybraného žádného účastníka, dole na stránce je box **Rychloregistrace** s tlačítkem **Jen registrovat**. To okamžitě založí nový prázdný účet a ukáže hlášku „Vytořen uživatel s ID …" — ID si poznamenej, podle něj účet hned vybereš omniboxem a doplníš osobní údaje. Pokud běží registrace, nový účet se rovnou přihlásí na GameCon, a pokud už GameCon probíhá, označí se i jako přítomný. Vedle je i samostatný box **Vypsat pokoj** — funguje stejně jako v Nastavení pokojů.

## Typické postupy

**Účastník přišel zaplatit hotově:** vyber ho omniboxem → zkontroluj Stav účtu → v „Připsat platbu" zadej částku → **Připsat**. Platí mobilem? Ukaž mu **QR platby** a po zaplacení klikni **🗘 Fio**.

**Účastník se chce registrovat na místě:** nemá účet → **Jen registrovat**, vyber nový účet podle ohlášeného ID, doplň osobní údaje. Má účet → vyber ho a klikni **Přihlásit**. Pak s ním vyřiď platbu, ubytování a jídlo a nakonec **Přijel(a) a Dát materiály**.

**Účastník si jde pro tričko/materiály:** vyber ho → v Přehledu u **Balíčku** najedeš myší na seznam, co mu patří → předej věci, stravenky a číslo pokoje, srovnej nedoplatek → **Přijel(a) a Dát materiály**. Tričko koupené až na místě prodáš přes **Prodej**.

## Na co si dát pozor

- **Odhlásit z GC** trvale smaže všechny aktivity a nákupy účastníka — nevratné, jen pro správce financí.
- **Rušení objednávek** (koš v Objednávkách) měň zůstatek účastníka — potvrzuj s rozmyslem.
- **Přidělit pokoj** přepíše stávající přidělení bez ptaní.
- **Zrušit jídlo** jen proti vrácené stravence; nové jídlo = vydat stravenku.
- Před **Dát materiály** a před ukončením práce s účastníkem vyřeš nedoplatek, potvrzení od rodičů (mladší 15 let), formulář cizince a chybějící osobní údaje — systém tě na ně upozorní, ale nezastaví šéfa Infa.

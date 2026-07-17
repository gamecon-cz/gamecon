# Peníze

Kartička **Peníze** slouží k drobným finančním operacím nad účty účastníků:
vyplácení bonusů vypravěčům, ruční připsání slevy a stažení reportů ubytování.
Pod stejnou kartičkou najdeš i podstránky **Info věci před GC** (nástroje pro
infopult před začátkem festivalu) a **Rušení storna**.

## Převést bonus za vedení aktivity na peníze

Vypravěči za vedení aktivit vzniká bonus (v přehledu financí ho vidí jako
*Slevy za organizované aktivity*). Když si ho vypravěč nechce nechat na útratu,
tady mu ho převedeš na peníze:

1. V poli **Uživatel** vyber vypravěče. Nabídka se chvíli načítá a obsahuje jen
   **vypravěče s letošní účastí na GC**, kteří mají nějaký nevyužitý bonus —
   u každého rovnou vidíš částku, např. „Jan Novák - bonus k vyplacení 500 Kč".
2. **Poznámka** je předvyplněná textem „Převedení bonusu", můžeš ji upravit.
3. Pole **Převedl/a** je jen pro kontrolu — automaticky obsahuje tvoje jméno.
4. Klikni na **Převést**.

Po úspěchu se zobrazí hláška **„Bonus … Kč vyplacen uživateli …"**. Převádí se
vždy **celý** zbývající bonus najednou, částku nelze zvolit.

Pozor na nápovědu u tlačítka: *„Jde pouze o převod bonusu (Slevy za
organizované aktivity) na pohyb na účtu (připsání). Samotné fyzické vyplacení
je potřeba provést ručně."* — tzn. hotovost nebo převod na bankovní účet musíš
zařídit mimo systém.

Možné chybové hlášky: „Uživatel … není přihlášen na GameCon." a „Uživatel …
nemá žádný bonus k převodu."

## Připsat slevu

Ruční připsání slevy konkrétnímu účastníkovi (stejný formulář je i na kartičce
**Finance**):

1. Do pole **Výše slevy** napiš částku v Kč (jen číslice, případně desetinná
   čárka).
2. Do pole **Uživateli s ID** napiš ID účastníka. Pokud máš na kartičce
   **Uživatel** někoho načteného, jeho ID je tu předvyplněné — přesto ho
   zkontroluj.
3. **Poznámka** je povinná — napiš, proč slevu připisuješ (bude vidět
   v přehledu financí).
4. Pole **Připsal/a** obsahuje automaticky tvoje jméno.
5. Klikni na **Připsat**.

Po úspěchu se zobrazí **„Sleva … Kč připsána k uživateli …"**. Chybové hlášky:
„Zadej slevu.", „Uživatel … neexistuje.", „Uživatel … není přihlášen na
GameCon."

## Reporty

Dole na stránce je box **Reporty** se dvěma odkazy, které stáhnou soubor XLSX:

| Odkaz | Obsah |
|-------|-------|
| **Report ubytování** | přehled ubytování pro finance |
| **Report ubytovaných cizinců** | ubytovaní cizinci (např. pro hlášení ubytovatele) |

## Podstránka Info věci před GC

Nástroje pro infopult před začátkem GameConu:

- **Nastavení mřížkového prodeje** — interaktivní nastavení „mřížek" obchodu
  (prodeje na místě) pro letošní ročník. Po otevření se chvíli načítá
  („Nastavení mřížek obchodu se načítá ...").
- **Importér balíčků** — nahrání informace, kdo má dostat **velký balíček**.
  Jako zdroj slouží report *Infopult: Balíčky účastníků* ve formátu XLSX (odkaz
  je přímo na stránce). Do sloupce `balicek` napiš **velký balíček** u těch,
  kdo ho mají dostat; u ostatních nech cokoli jiného. Soubor vyber a klikni na
  **Nahrát**. Podle textu na stránce import nerozbije, co už systém ví o
  balíčcích a stravenkách.

## Podstránka Rušení storna

Když se účastník nedostavil na aktivitu (nebo se pozdě odhlásil), naskočí mu
storno poplatek. Stránka **Rušení storna za aktivity** ukazuje tabulku všech
letošních storen (Id, Uživatel, Aktivita, Začátek aktivity, Typ storna).
Tlačítkem **zrušit** u řádku storno odpustíš — potvrdí se hláškou „Zrušeno
storno pro … za … (…)". Pozor: *„Zrušení storna vymaže i záznam, že účastník
na aktivitu nedorazil."* Pokud žádná storna nejsou, uvidíš „Letos zatím žádná
storna."

## Kupóny „jedna aktivita zdarma"

Údržbové tlačítko **Přepočítat kupóny** najdeš na kartičce **Finance** (sekce
*Údržba kupónů „jedna aktivita zdarma"*). Přepočítá hodnotu kupónů všem
aktuálním držitelům práva podle nejdražší reálně placené aktivity. Běžně se
kupóny přepočítávají samy — tlačítko použij jen **při chybě nebo po změně cen
aktivit**. Před spuštěním se zobrazí potvrzení: *„Opravdu přepočítat všechny
kupóny „jedna aktivita zdarma"? Přepíše to jejich hodnotu u všech držitelů."*
Výsledek: „Přepočítáno kupónů „jedna aktivita zdarma": …".

## Typický postup: vypravěč si přišel pro bonus

1. Otevři kartičku **Peníze** a počkej, až se načte seznam v poli **Uživatel**.
2. Najdi vypravěče a zkontroluj s ním částku „bonus k vyplacení" u jeho jména.
3. Doplň případně poznámku a klikni na **Převést**.
4. Vyplať mu peníze **ručně** (hotovost / převod) — systém udělal jen zápis na
   jeho účet v GameConu.

## Na co si dát pozor

- **Převod bonusu je nevratný a bez potvrzovacího dotazu** — po kliknutí na
  **Převést** se okamžitě převede celý zbývající bonus. Částku i vypravěče si
  zkontroluj předem.
- **Připsání slevy** se také provede hned bez dalšího potvrzení; překlep v ID
  připíše slevu jinému účastníkovi. Sleva jde vidět (i s tvou poznámkou a
  jménem) v přehledu financí účastníka.
- **Zrušení storna** maže i záznam o tom, že účastník nedorazil — nedá se
  jednoduše vrátit.
- **Brigádnická odměna** (odměna za brigádnické aktivity) se účastníkovi
  zobrazuje automaticky jako řádek *Brigádnická odměna* v přehledu financí
  (v adminu i na webu) a započítává se do jeho zůstatku — nic pro ni na
  kartičce Peníze vyplácet nemusíš.

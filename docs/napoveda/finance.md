# Finance

Sekce **Finance** je zázemí pro správu peněz festivalu: přehled stavů účtů
účastníků, správa e-shopu (trička, jídlo, ubytování…), slevy a slevové
poukazy, párování plateb z banky a úklidové nástroje na konec ročníku
(hromadné odhlášení neplatičů, promlčení starých zůstatků, slučování
duplicitních účtů, anonymizace).

Sekci vidí jen organizátoři s právem na administraci financí. Ve spodní části
každé stránky sekce je patička **„Nastavený kurz € = … Kč"** s odkazem do
nastavení, kde jde kurz změnit.

Několik akcí v této sekci je **nevratných** — jsou níže výrazně označeny.

## Finance (hlavní přehled)

Do pole **„Vypsat uživatele, kteří mají stav účtu vyšší rovno jak"** zadej
částku a klikni na **Vypsat**. Zobrazí se tabulka uživatelů přihlášených na
letošní GameCon se sloupci **Login**, **Stav účtu**, **aktiv** (cena aktivit),
**ubyt** (ubytování) a **předm** (předměty a strava). Chceš-li dlužníky, zadej
záporné číslo. Pozn. z přehledu: *vypravěči mají právo „zaplatil včas" vždy*.
Když filtru nikdo nevyhovuje, uvidíš **(žádní uživatelé)**.

**Připsat slevu** — formulář s poli **Výše slevy** (Kč), **Uživateli s ID**
(předvyplní se právě vybraný pracovní uživatel), **Poznámka** (povinná)
a **Připsal/a** (tvoje jméno, needitovatelné). Sleva jde připsat jen uživateli
přihlášenému na letošní GameCon — jinak dostaneš chybu.

**Údržba kupónů „jedna aktivita zdarma"** — tlačítko **Přepočítat kupóny**
přepočítá hodnotu kupónů všem aktuálním držitelům práva (podle nejdražší
reálně placené aktivity). Běžně se kupóny přepočítávají samy při změně role
nebo přihlášky — tohle použij jen **při chybě nebo po změně cen aktivit**.
Akce se potvrzuje dialogem, protože přepíše hodnotu kupónu u všech držitelů.

Dále je tu tabulka **Report** s exportem **BFGR** (xlsx) a **Importér
ubytování** — nahráním XLSX souboru (vychází z *Reportu ubytování*)
**přepíše kompletně letošní údaje o ubytování**. Přepis či mazání osobních
údajů (OP a občanství) z importu projde jen se zaškrtnutým
**„Povolit přepis / mazání osobních údajů"**.

## Shop

Tabulka letošních položek e-shopu seskupená podle typu (trička, jídlo,
ubytování…). U každé položky vidíš **Název**, **Cenu za kus**, **Sumu**
(celkem utrženo), **Model rok**, **Naposledy koupeno**, **Letos prodáno
kusů** a **Zbývá kusů** (∞ = neomezeno). Hvězdička u názvu znamená
*„Vybráno pro slevu či zdarma organizátorům"*.

Upravit můžeš dva sloupce a pak klikni na **Uložit změny**:

| Sloupec | Význam |
|---------|--------|
| **Kusů celkem** | Kolik kusů je vyrobeno/k dispozici; prázdné = neomezeno. |
| **Stav** | **Veřejný** (běžně v prodeji), **Prodejný na místě**, **Orgové** (podpultový), **Vyřazený**. |

**Importér e-shopu** — nahráním XLSX (vychází z *Reportu e-shopu*)
**přepíše kompletně letošní nabízené předměty a ubytování**. Pozor na
varování přímo ve formuláři: *⚠️ Položky, které nebudou v importním souboru,
import skryje ⚠️*.

Pod importérem je **Nastavení mřížkového prodeje** (mřížky obchodu, např.
prodej jídla po dnech).

## Slevové poukazy

Přehled poukazů na jednu aktivitu zdarma s počty **Celkem / platných /
použitých**. Tlačítko **Vygenerovat nový poukaz** vytvoří nový kód; přes odkaz
**Zobrazit poukaz** ho zobrazíš a vytiskneš.

Každý poukaz má stav **Platný**, **Použitý** nebo **Zneplatněný**. U poukazu
můžeš:

- **Zneplatnit** — jen dosud nepoužitý poukaz; potvrzuje se dialogem
  *„Opravdu zneplatnit tento poukaz? Nepůjde pak uplatnit."* Použitý poukaz
  zneplatnit nejde (hláška *„Poukaz nešlo zneplatnit – už byl uplatněný."*).
- **Obnovit platnost** — vrátí omylem zneplatněný (a stále nepoužitý) poukaz
  zpět mezi platné.
- Uložit **Poznámku** (komu byl předán, k jaké akci patří apod.).

## Nespárované platby

Platby stažené z banky, které se nepodařilo automaticky přiřadit k uživateli
(typicky chybný nebo chybějící variabilní symbol). U každé vidíš **Částku**,
**Zprávu pro příjemce**, **Skrytou poznámku**, **Název protiúčtu** (po najetí
myší i číslo účtu a banku), **VS** a časy připsání.

Ve sloupci **Spárovat s uživatelem** začni psát jméno/ID — našeptávač uživatele
dohledá a po potvrzení dialogu **„Opravdu spárovat?"** se platba připíše na
jeho účastnický účet. Ověř si předem podle částky a jména na protiúčtu, že
páruješ správného člověka — přiřazenou platbu už tady zpátky „odpáruješ" jen
s pomocí IT.

## Rušení storna

Seznam letošních záznamů o stornu za aktivity (účastník se nedostavil apod.):
**Uživatel**, **Aktivita**, **Začátek aktivity**, **Typ storna**. Tlačítkem
**zrušit** storno smažeš — *zrušení storna vymaže i záznam, že účastník na
aktivitu nedorazil*. **Pozor: tlačítko se na nic neptá, ruší okamžitě.**
Když letos žádná storna nejsou, uvidíš *„Letos zatím žádná storna."*

## Hromadné rušení objednávek

Nástroj na zrušení objednávek (jídlo / ubytování / tričko) lidem se zůstatkem
nižším než zadaná hranice — typicky neplatičům před festivalem. Ve výpisu se
neobjeví lidé s právem „nerušit automaticky objednávky".

1. Zadej **Minimální zůstatek** (výchozí −20 Kč) a **Typ objednávek**.
2. Klikni na **Vypsat uživatele s nižším zůstatkem** a seznam zkontroluj.
3. Teprve pak klikni na **Zrušit vypsaným uživatelům vybrané objednávky**.
   Bez předchozího výpisu tě zastaví hláška *„Nejdříve si musíte uživatele
   vypsat."*

**⚠️ Nevratná akce bez dalšího potvrzení** — po kliknutí se objednávky
vypsaným lidem rovnou zruší. Před krokem 3 si výpis opravdu projdi.

## Promlčení zůstatků 🤫

Odepsání starých zůstatků lidí, kteří už na GameCon nejezdí.

1. Zadej rozmezí zůstatku (druhou hranici můžeš nechat prázdnou pro
   „nekonečno"; jde zadat i záporná čísla pro zjištění nedoplatků) a rok
   poslední účasti na GC.
2. **Zobrazit uživatele** vypíše kandidáty s ID, jménem, stavem účtu, ročníky
   přihlášení a poslední připsanou platbou. **Exportovat do XLSX** ti stejný
   seznam dá do tabulky. Hodí se i odkaz na report *Finance: Zůstatky všech
   účastníků*.
3. Zaškrtávátky vyber, komu promlčet, a klikni na **Promlčet X účtům**.
   Potvrzuje se dialogem *„Opravdu promlčet X účtů?"*

**⚠️ Nevratná akce** — zůstatek vybraných účtů se vynuluje. Akce se zapisuje
do interního logu (kdo, komu, kolik). Při velkém počtu účtů se může objevit
upozornění *„Kvůli technickým omezením PHP lze najednou promlčet pouze X ze
Y účtů"* — pak promlčení spusť vícekrát po dávkách.

## Hromadné odhlášení účastníků

Odhlášení neplatičů z GameConu naráz. **Nelze hromadně odhlásit účastníky,
kteří jsou již přítomni na Gameconu** (prošli infopultem) — takové ID musíš
ze seznamu vyřadit, jinak se neodhlásí nikdo.

1. Do pole **ID účastníků** vlož IDčka oddělená čárkou, mezerou nebo
   středníkem a klikni na **Připravit k hromadnému odhlášení**.
2. Zkontroluj vypsaný seznam (ID, jméno, zůstatek). Už odhlášené lidi systém
   sám vyřadí a oznámí to.
3. Klikni na **Hromadně odhlásit** a potvrď dialog *„Trvale odhlásit uživatele
   z GameConu a smazat všechny jejich aktivity a nakoupené věci?"*

**⚠️ Nevratná akce** — odhlášeným se zruší přihláška na GC, všechny aktivity
i nákupy; o odhlášení dostanou e-mail.

## Slučování uživatelů

Sloučení duplicitních účtů jednoho člověka. Vhodné vycházet z **reportu**
s potenciálně duplicitními uživateli (odkaz přímo na stránce).

1. Zadej obě ID a klikni na **Načíst údaje**.
2. Vedle sebe uvidíš historii přihlášení na ročníky a u každého údaje (login,
   heslo, mail, jméno a příjmení, adresa, telefon, poznámka, OP, pohlaví,
   datum narození) vybereš přepínačem, ze kterého účtu se převezme. Vidíš
   i zůstatky z předchozích ročníků a poslední platbu obou účtů.
3. Klikni na **Sloučit uživatele** a potvrď dialog *„Opravdu sloučit … s …?"*

**⚠️ Nevratná akce.** Výsledné ID by mělo být vždy to nižší (přihláška na GC
se zachová, i když ji měl účet s vyšším ID). Případné dluhy / zůstatky se
sečtou. Pokud je slučovaný uživatel vybrán pro práci, je potřeba ho zrušit
a vybrat znova. Pokud se vyskytne chyba, hlásit. Dole na stránce je
**Historie slučování uživatelů** — kdy, jaká ID, zůstatky a e-maily před
a po sloučení.

## Anonymizace 👻

Vyhovění žádosti o výmaz osobních údajů (GDPR). Zadej **ID uživatele**
a klikni na **Anonymizovat uživatele**.

**⚠️ Varování přímo ze stránky:** *Tato akce nenávratně anonymizuje všechna
osobní data uživatele (jméno, příjmení, adresa, telefon, email, atd.).*
Potvrzuje se dialogem *„Opravdu chcete anonymizovat tohoto uživatele? Tato
akce je nevratná!"* Před spuštěním si dvakrát ověř ID.

## Typické postupy

**Přišla platba bez VS (nebo s chybným VS).** Otevři **Nespárované platby**,
podle částky, zprávy pro příjemce a názvu protiúčtu dohledej, čí platba to je,
a ve sloupci **Spárovat s uživatelem** ji přiřaď. Když si nejsi jistý, radši
se člověka doptej — přiřazení se tady zpět nevrací.

**Účastník chce vrátit peníze / má přeplatek.** Zůstatek si ověř přes hlavní
přehled **Finance** (vypiš uživatele se stavem účtu ≥ 1 Kč) nebo na kartě
uživatele. Samotné vrácení peněz proběhne převodem z banky mimo tuto sekci.
Staré nevyzvednuté zůstatky lidí, kteří už nejezdí, řeš hromadně přes
**Promlčení zůstatků**.

**Před festivalem / konec ročníku.** Obvyklý úklid: (1) **Hromadné rušení
objednávek** uvolní jídlo a ubytování rezervované neplatiči; (2) **Hromadné
odhlášení účastníků** odhlásí ty, kdo nezaplatili vůbec; (3) po festivalu
**Rušení storna** pro odpuštění storn v odůvodněných případech; (4) čas od
času **Slučování uživatelů** podle reportu duplicit a **Promlčení zůstatků**
za staré ročníky.

## Na co si dát pozor

- **Nevratné akce:** hromadné odhlášení, hromadné rušení objednávek,
  promlčení zůstatků, sloučení účtů, anonymizace. Vždy si nejdřív seznam
  vypiš/zkontroluj a teprve potom klikej.
- **Rušení storna a hromadné rušení objednávek nemají potvrzovací dialog** —
  kliknutí rovnou provede akci.
- **Import e-shopu a import ubytování kompletně přepisují letošní data.**
  Položky, které v importním souboru chybí, import skryje.
- Sleva jde připsat jen uživateli **přihlášenému na letošní GameCon**.
- Použitý slevový poukaz už nejde zneplatnit; omylem zneplatněný jde
  **Obnovit platnost**.
- Hromadně nejde odhlásit nikdo, kdo už **prošel infopultem** — jediné takové
  ID v seznamu zablokuje celou dávku.

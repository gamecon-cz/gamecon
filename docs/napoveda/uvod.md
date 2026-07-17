# Úvod do administrace

Administrace (admin) je interní část webu GameConu pro organizátory, vypravěče
a další pomocníky. Spravuješ v ní účastníky, aktivity, platby, obsah webu
i nastavení ročníku. Veřejný web je výkladní skříň — admin je zázemí, kde se
festival doopravdy řídí. Platí tu proto dvojnásob citát z patičky:
*„Bacha, tady můžeš něco posrat, seš si jistej, že víš co děláš?"*

## Přihlášení

Admin najdeš na adrese `/admin`. Přihlašuješ se stejným účtem jako na veřejném
webu GameConu:

1. Do pole **Jméno** napiš svůj login (přezdívku).
2. Do pole **Heslo** napiš heslo.
3. Klikni na **Přihlásit**.

Když se přihlášení nepovede, uvidíš hlášku **„Chybné přihlašovací jméno nebo
heslo"** — zkontroluj překlepy a zkus to znovu.

Po přihlášení tě admin pošle rovnou na stránku, která pro tebe dává největší
smysl: organizátoři přistanou na kartě **Uživatel** (infopult), vypravěči bez
dalších práv na **Moje aktivity**. Kdo nemá do adminu žádné právo, je
přesměrován zpět na veřejný web.

Odhlásíš se tlačítkem **Odhlásit** v levém sloupci pod textem
**Přihlášen jako …**.

## Orientace: kartičky a podmenu

Vlevo je sloupec s **kartičkami** — hlavním menu adminu. Kartička = jedna
oblast: **Uživatel**, **Infopult**, **Aktivity**, **Moje aktivity**,
**Prezence**, **Finance**, **Peníze**, **Reporty**, **Statistiky**, **Web**,
**Nastavení**, **Práva**, **Nápověda**, případně **Dev**. Právě otevřená
kartička je zvýrazněná.

**Vidíš jen kartičky, na které máš právo.** Co v adminu vidíš a smíš dělat,
řídí práva tvých rolí (role ti přiděluje ten, kdo spravuje **Práva** — typicky
hlavní organizátoři). Dva lidé tak mohou mít admin úplně jinak zaplněný:
vypravěč uvidí třeba jen **Moje aktivity** a **Nápovědu**, organizátor
infopultu skoro všechno. Když otevřeš odkaz na stránku, na kterou právo nemáš,
admin ti to řekne: **„Pro přístup k této stránce nemáš oprávnění."**

Stejně funguje i **Nápověda**: ukazuje jen kapitoly k částem adminu, na které
máš právo. Nediv se proto, když kolega vidí v nápovědě víc kapitol než ty.

Větší kartičky (např. Aktivity, Finance, Web) mají nahoře na stránce ještě
**podmenu** s jednotlivými podstránkami — např. u Aktivit najdeš mimo jiné
**Nová aktivita**, **Týmy**, **Místnosti**, **Štítky** nebo **Export & Import
aktivit**. I položky podmenu se filtrují podle tvých práv.

V patičce každé stránky jsou odkazy **Program GameConu**, **Volná místa**
a **Program test** (otevírají se do nové záložky) a náhodný **Protip** —
krátká rada, jak si práci v adminu zrychlit. Vyplatí se je číst.

## Pracovní uživatel

Máš-li právo na infopult, je úplně nahoře v levém sloupci políčko pro výběr
**pracovního uživatele** — účastníka, kterému zrovna pomáháš (řešíš mu
přihlášky, platby, ubytování…):

- Napiš **začátek přezdívky, jména, příjmení, e-mailu nebo ID** a vyber
  z našeptávače. Do políčka skočíš i zkratkou **alt+U**.
- Vybraný uživatel se ukáže i s avatarem, jménem, ID a stavem přihlášení na GC.
  Zůstává vybraný, dokud ho nezrušíš — všechny infopultové akce se pak týkají jeho.
- Tlačítkem **zrušit** (zkratka **alt+Z**) práci s ním ukončíš.
- Ikonka odkazu / kopírování vedle ID zkopíruje adresu aktuální stránky
  s předvybraným uživatelem — hodí se pro poslání kolegovi, kterému se po
  otevření odkazu vybere tentýž uživatel.
- Pod políčkem se pamatuje **předchozí pracovní uživatel** — kliknutím na
  odkaz **↻ jméno** se k němu rychle vrátíš.

Důležité: pracovní uživatel je jen „koho obsluhuji". **Ty jsi pořád přihlášen
sám za sebe** a akce děláš svým jménem.

Pozor na nevratné akce s pracovním uživatelem — **odhlášením uživatele
z GameConu se nenávratně zruší všechny jeho aktivity a nákupy.**

## Přepnutí na uživatele

Něco jiného je **přepnout se na uživatele** — políčko v levém sloupci pod
textem **Přihlášen jako …**. Tím se opravdu přihlásíš jako někdo jiný a admin
i web vidíš přesně jeho očima (jeho práva, jeho kartičky). Hodí se, když
potřebuješ ověřit, co konkrétní člověk vidí, nebo něco vyřešit za něj.

- Přepnutí na **libovolného** uživatele má jen ten, komu pro letošní ročník
  přidělila zvláštní právo rada — právo se každý ročník uděluje znovu.
- Infopulťáci mají omezenou variantu: mohou se přepnout jen na **letošní
  vypravěče a partnery** (např. kvůli kontrole jejich aktivit). Po přepnutí
  na vypravěče skončíš rovnou na jeho **Moje aktivity**.
- Po přepnutí **jsi** ten uživatel — cokoli uděláš, děláš jeho jménem. Zpátky
  na svůj účet se dostaneš tlačítkem **Odhlásit** a novým přihlášením pod
  svým jménem.

## Jak poznám, že nejsem na ostré verzi

Ostrá (produkční) verze adminu nemá žádné zvláštní označení. Testovací
prostředí poznáš podle barevné **stužky v rohu obrazovky** — je vidět už na
přihlašovací stránce:

| Stužka | Kde jsi |
|--------|---------|
| *(nic)* | ostrá verze — tady se pracuje naostro |
| **β beta** | beta — testovací kopie |
| **🧐 preview** | preview — náhled rozpracované novinky |
| **άλφα local** | lokální vývojářská verze |

Než uděláš cokoli důležitého (platby, odhlašování, mazání), mrkni do rohu.
Bez stužky = ostrá data.

## Klávesové zkratky

- **Tlačítka s podtrženým písmenem** jdou zmáčknout zkratkou
  **alt+podtržené písmeno** (např. **z̲rušit** = alt+Z).
- **alt+U** — skočí do políčka výběru pracovního uživatele.
- **alt+Z** — zruší vybraného pracovního uživatele.

Používání klávesových zkratek práci na infopultu znatelně urychlí.

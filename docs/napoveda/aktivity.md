# Aktivity

Sekce **Aktivity** je srdce programu GameConu. Tady zakládáš a upravuješ aktivity (hry, turnaje, přednášky, brigády…), řídíš jejich zveřejnění a otevření přihlašování, hlídáš přihlášené účastníky a týmy, spravuješ štítky a místnosti a hromadně přenášíš aktivity mezi ročníky přes exporty a importy. Podle svých práv nemusíš vidět všechny podstránky.

## Přehled aktivit a životní cyklus

Podstránka **Přehled Aktivit** ukazuje tabulku aktivit s filtrem nahoře:

- **Programová linie** — zobrazí jen aktivity vybrané linie (u každé je v závorce počet aktivit); volba „(všechno)" zruší filtr.
- Sloupce **Název**, **Čas**, **Vypravěč** a **Typ** lze řadit trojúhelníčky ▲▼ v hlavičce. Najetím na název aktivity se ukážou její štítky.
- Sloupec **Ins**: ikona řetězu znamená, že aktivita je členem rodiny *instancí* — na webu se zobrazuje jen jednou, s více termíny k přihlášení.
- Sloupec **Kor**: zelená fajfka = u aktivity proběhla gramatická korekce a od té doby se text neměnil; červený křížek = korekce neproběhla. Kdo má právo provádět korekce, může stav kliknutím přepnout.

### Stavy aktivity

| Stav | Co znamená |
|------|------------|
| **nová** | Ve výrobě. Aktivita není vidět na webu. |
| **publikovaná** | Je vidět na webu, ale nedá se na ni přihlašovat. |
| **připravená** | Je vidět, nedá se přihlašovat, ale je nachystaná k (hromadné) aktivaci. |
| **aktivovaná** | Otevřená — účastníci se na ni mohou přihlašovat. |
| **zamčená** | Aktivita už začíná/proběhla a je zamčená pro změny (typicky kvůli prezenci). |
| **uzavřená** | Proběhlá aktivita s vyplněnou prezencí; definitivně uzavřená pro změny. |
| **systémová** | Interní technické položky systému; běžně je neřešíš. |

Obvyklá cesta: **nová → publikovaná → připravená → aktivovaná**, po proběhnutí aktivity **zamčená → uzavřená**. Posouváš ji tlačítky v posledních sloupcích tabulky:

- **pub** — publikovat (ukázat) aktivitu na webu; **odpu** — skrýt zpět před veřejností (s potvrzením).
- **přip** — připravit k hromadné aktivaci; **odpři** — připravení zrušit.
- **aktiv** — aktivovat, tj. otevřít k přihlašování (reálně se projeví, jen pokud běží registrace aktivit).
- **deak** — zavřít přihlašování (vrátit z aktivované zpět). Vidí ho jen šéf programu. Pokud už jsou na aktivitě přihlášení, musíš deaktivaci navíc potvrdit zaškrtnutím „Chci deaktivovat aktivitu s N přihlášenými účastníky".
- **inst** — vytvořit novou instanci aktivity (kopii na další termín; čas a místo pak doladíš v editaci).
- **tužka** — otevře podrobnou editaci aktivity.
- **koš** — **nevratné smazání** aktivity včetně odhlášení všech účastníků. Dobře si rozmysli, potvrzení je jen jedno.

### Hromadná aktivace

Tlačítko **aktivovat hromadně** nad tabulkou aktivuje **všechny „připravené" aktivity ve všech programových liniích najednou**. Je to ostrá akce vázaná na zvláštní právo (drží ho jen člen rady) a jde použít jen bez vybrané programové linie a jen pro letošní rok. Před spuštěním se zobrazí potvrzení — přečti si ho.

Kromě ručního tlačítka se připravené aktivity aktivují i **automaticky v čase vlny** přihlašování. Běžný postup tedy je: aktivity dopředu překlop do stavu „připravená" a v okamžiku vlny se otevřou samy.

## Založení a úprava aktivity

Novou aktivitu založíš na podstránce **Nová aktivita**, existující upravíš tužkou z přehledu. Formulář má tato pole (pole označená ¹ jsou *specifická pro instanci* — u rodiny instancí je má každý termín vlastní):

- **Název** (povinný) a **URL** — adresa na webu, ideálně malá písmena a pomlčky. URL musí být v ročníku jedinečná; stejnou URL smí sdílet jen instance téže aktivity (pro duplikaci použij „inst" v přehledu).
- **Místnosti¹** — aktivita může být ve více místnostech; volitelně jednu označ jako **hlavní místnost¹**.
- **Den¹** a **Čas¹** (od–do). Konec musí být po začátku.
- **Vypravěč¹** — jeden nebo více. Pokud má vypravěč v tu dobu jinou aktivitu, uložení skončí chybou s názvem kolizní aktivity.
- **Typ** (programová linie) a zaškrtávátko **teamová** — u týmové aktivity si první přihlášený sestavuje tým.
- **Kapacita** — tři čísla: unisex / muži / ženy (0/0/0 = bez omezení). U týmové aktivity místo toho zadáváš **min** a **max** velikost týmu, **týmů¹** (maximální počet týmů) a volbu **smazat tým po expiraci** (když kapitán tým včas nedokompletuje, tým se smaže; jinak se zveřejní).
- **Cena** a **bez slev** (cena je pevná, slevy se neaplikují). U brigádnické aktivity se místo ceny zadává **odměna za hodinu** — vyžaduje správně vyplněný čas od–do.
- **Tagy** — štítky pro vyhledávání na webu (správa viz níže).
- **Příprava místnosti** — co je potřeba nachystat (vybavení).
- **Turnaj¹** a **Kolo** — zařazení aktivity do vícekolového turnaje; můžeš vybrat existující turnaj nebo rovnou založit nový. U aktivity v turnaji se zobrazí přehled všech kol s časy a možností publikovat dosud nezveřejněná kola. Pozor: kolo s více aktivitami na výběr dává smysl jen u týmových aktivit — formulář tě na to upozorní.
- **Obrázek** — nahraješ soubor nebo zadáš URL; šířka minimálně 320 px, poměr 16:9.
- **Krátký popis** (s počítadlem znaků) a **Popis** — popis se píše ve zjednodušeném formátování (odkaz *help* u pole ukáže syntaxi: kurzíva, tučně, odkazy, seznamy, nadpisy) a vpravo vidíš živý náhled.
- **Proběhla korekce?** — vidí jen ten, kdo má právo provádět korekce; zaškrtne po jazykové korektuře textu.

Při ukládání změn **dne, času, ceny nebo kapacity** aktivity, na které už jsou přihlášení hráči, se zobrazí potvrzení s počtem dotčených hráčů. Cena a kapacita se u rodiny instancí propisují na všechny instance, takže varování počítá i hráče na sesterských termínech; den a čas se týkají jen upravovaného termínu.

## Seznam přihlášených

Podstránka **Seznam přihlášených** je přehled účastníků po programových liniích — nahoře klikneš na linii a pod sebou uvidíš všechny její letošní aktivity. U každé aktivity je:

- obsazenost (např. `12/20`) s rozpadem podle pohlaví „u + m + ž" a počtem **sledujících** — to jsou lidé, kteří na plné aktivitě čekají na uvolněné místo (systém jim pak pošle e-mail); v tabulce jsou šedě kurzívou s poznámkou „(sledující)",
- čas, vypravěči a místnost,
- odkazy **e-mail všem** (otevře e-mail se skrytými kopiemi všem přihlášeným) a **zobrazit maily**,
- tabulka přihlášených: login, jméno, e-mail, **věk** v den aktivity (u dospělých jen „18+"), telefon a datum přihlášení.

Samotné přihlašování a odhlašování účastníků z adminu děláš v programu na kartě uživatele (viz nápověda k uživatelům). Dvě zvláštnosti, které se hodí vědět:

- Na **skryté (nepublikované) technické a brigádnické aktivity** může účastníky posadit jen organizátor s právem prezenčního admina, a jen v době, kdy běží registrace aktivit. Na jiné typy skrytých aktivit to nejde záměrně.
- Účastníky brigádnických a technických aktivit jde měnit i hromadně importem (viz níže).

## Štítky, místnosti, týmy a vypravěčské skupiny

### Štítky

Podstránka **Štítky** vypisuje všechny štítky (tagy) seskupené po kategoriích, s poznámkou. Tlačítkem **Přidat tag** založíš nový, tlačítkem **uprav** u řádku upravíš název, kategorii a poznámku. Štítky pak přiřazuješ aktivitám v editaci a účastníci podle nich filtrují program na webu.

### Místnosti

Podstránka **Místnosti** spravuje seznam místností: **Pořadí** (šipkami ▲▼ — určuje řazení v programu), **Název**, **Dveře** (číslo dveří) a **Poznámka** (slouží i jako kategorie pro seskupení místností v editoru aktivity). Tlačítko **vytvořit další místnost** přidá novou, **uprav** → **ulož** změní existující. Aktivitám místnosti přiřazuješ v editaci aktivity.

### Týmy

Podstránka **Týmy** ukazuje všechny letošní **týmové aktivity** a jejich týmy: kapacitu týmů, celkový počet přihlášených, u každého týmu název, kapitána, obsazenost, veřejnost a čas založení, plus členy s e-maily a odkaz **E-mail všem členům**. K dispozici jsou akce:

- **Smazat tým** — rozpustí tým (s potvrzením),
- **Zamknout tým / Odemknout tým** — zamčený tým už členové nemohou měnit; zamykat a odemykat smí jen šéf infopultu.

Tlačítko **Kontrola stavu týmů** projde všechny týmy a vypíše problémy s nabídnutým řešením: týmy bez aktivity (smazat), připravené týmy bez kapitána (předat kapitána konkrétnímu členovi nebo automaticky), týmy s chybným počtem aktivit v kolech turnaje (přihlásit/odhlásit po kolech), hráče nepřihlášené na všechny aktivity turnaje a hráče na týmové aktivitě bez týmu či ve více týmech (odhlásit z aktivity). Když je vše v pořádku, vypíše „Vše v pořádku – žádné chyby nenalezeny."

### Nová vypravěčská skupina

Podstránka **Nová vypravěčská skupina** vytvoří „falešný" účet (např. Albi, Deskofobie…), který jde uvést jako vypravěče aktivity. **Pozor:** pokud jako vypravěče uvedeš jen tento účet místo konkrétních lidí, skutečným vypravěčům se nezapočtou slevy a nezablokuje se jim slot v programu.

## Exporty a importy

Tlačítka **Exportovat/Importovat aktivity** a **Exportovat/Importovat účastníky** najdeš nad přehledem aktivit.

### Export a import aktivit (Google Sheets)

Slouží hlavně k **hromadnému založení ročníku recyklací loňských aktivit** a k hromadným úpravám. Napoprvé musíš **povolit Gameconu přístup** ke svému Google Drive — Gamecon smí číst a zapisovat *pouze soubory, které sám vytvoří*.

Postup: vyber programovou linii (a případně rok) → **Exportovat aktivity** vytvoří tabulku na tvém Google Drive → tabulku upravíš → na stránce importu ji vybereš v seznamu a dáš **Importovat aktivity**. Pravidla úprav v tabulce:

- **Nová aktivita**: smaž ID (řádek bez ID se založí jako nová); jméno je povinné, pokud nejde o instanci.
- **Nová instance**: musí mít stejné URL jako mateřská aktivita.
- **Úprava stávající**: ponech původní ID — co na řádku přepíšeš, to se importem změní.
- Vypravěče zadávej jako ID, jméno, nick nebo e-mail — ale nové vypravěče naklikej do adminu **před** importem, jinak je import vynechá. Přehled vypravěčů, štítků a místností je v exportu na dalších kartách. Štítky jde psát bez ohledu na velikost písmen a diakritiku, stavy stačí zkrátit na první tři písmena (PUB…).
- Po importu čti barevný výsledek: **červená** = řádek se nenahrál, **oranžová** = varování, přečti si detail a rozhodni, jestli vadí.
- **Jednou použitý import nejde recyklovat** — pro další kolo úprav si udělej nový export.

### Export a import účastníků

- **Export účastníků** stáhne soubor XLSX s účastníky aktivit vybrané programové linie — ve stejném formátu, jaký očekává import, takže ho můžeš rovnou upravit a nahrát zpět.
- **Import účastníků** nahraje soubor XLSX se sloupci `id_aktivity` a `ucastnik` (účastník opět jako ID, jméno, nick nebo e-mail). Funguje **jen pro brigádnické a technické aktivity**, a jen dokud aktivita není v provozu (nová, publikovaná, připravená). **Pozor, import je stav, ne přírůstek:** účastníci uvedení v souboru se přihlásí a účastníci dané aktivity, kteří v souboru chybí, se **odhlásí**. Po dokončení se vypíše, kolik lidí bylo přihlášeno a odhlášeno, plus případná varování.

## Ostatní podstránky

- **Program po místnostech** — tisková sestava programu členěná po místnostech (otevírá se v novém okně); hodí se na vylepení na dveře a pro orgy na místě. Aktivita ve více místnostech se ukáže v každé z nich.
- **Neubytovaní vypravěči** — přesměruje na report nepřihlášených a neubytovaných vypravěčů; rychlá kontrola, kdo z vypravěčů si ještě nevyřešil přihlášku nebo ubytování.
- **Medailonky** — správa medailonků vypravěčů zobrazovaných na webu. Horní část medailonku je obecná („o sobě"), dolní je pro DrD. V přehledu vidíš fajfkou/křížkem, kdo má kterou část vyplněnou; nový medailonek založíš zadáním ID uživatele.

## Typické postupy

1. **Nový ročník z loňska:** exportuj loňské aktivity linie do Google Sheets → smaž ID u řádků, které chceš založit znovu → uprav termíny a vypravěče → importuj. Aktivity vzniknou jako „nové" (skryté).
2. **Zveřejnění programu:** u hotových aktivit klikej **pub** — účastníci je vidí, ale nemohou se hlásit.
3. **Příprava vlny:** aktivity, které se mají otevřít, překlop přes **přip** do „připravená". V čase vlny se aktivují samy, případně je člen rady spustí tlačítkem **aktivovat hromadně**.
4. **Přidání dalšího termínu:** u aktivity klikni **inst** a v editaci nové instance nastav den, čas a místnost. Na webu se rodina ukáže jako jedna aktivita s výběrem termínu.

## Na co si dát pozor

- **Smazání aktivity (koš) je nevratné** a odhlásí všechny přihlášené účastníky.
- **Hromadná aktivace** otevře najednou všechny připravené aktivity ve všech liniích — spouštěj ji, jen když je celý balík opravdu nachystaný.
- **Deaktivace aktivity s přihlášenými** je zásah do už provedených přihlášek; proto ji smí jen šéf programu a vyžaduje zvláštní potvrzení.
- **Import účastníků odhlašuje** každého, kdo v nahraném souboru chybí — nikdy nenahrávej neúplný seznam „jen s novými lidmi".
- Změna **dne, času, ceny nebo kapacity** aktivity s přihlášenými se dotkne hráčů — potvrzující dialog ber vážně; cena a kapacita se navíc propíšou na všechny instance.
- Tlačítko **aktiv** má viditelný efekt jen v době, kdy běží registrace aktivit.
- **Vypravěčská skupina** (fake účet) nenahrazuje skutečné vypravěče — bez nich se nezapočtou slevy ani neblokují sloty.

<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Brožura „Hráčův průvodce GameConem“ jako stránka webu (/brozura), aby se nemusela stahovat jako PDF.
// Text je v Markdownu a upravuje se v adminu jako každá jiná stránka; ilustrace a mapy leží v gitu
// ve web/soubory/blackarrow/brozura/, vzhled dává brozura.less. Stránku, kterou už někdo založil, nepřepíše.
// U dalekohledu je `src` před `class` schválně: Stranka::obrazek() bere za obrázek stránky (og:image) první
// `<img src=`, u ostatních ilustrací by jím jinak byla mapa areálu.

$existuje = $this->q(<<<'SQL'
SELECT 1 FROM stranky WHERE url_stranky = 'brozura'
SQL,
)->fetch_row();
if ($existuje) {
    return;
}

$obsah = <<<'MD'
<div class="brozura" markdown="1">

# Hráčův průvodce GameConem

<img src="soubory/blackarrow/brozura/dalekohled.avif" class="brozura_obrazek brozura_obrazek-uvod" alt="" width="355" height="226">

Všechno důležité na jednom místě: kde co najdeš, kdy se jí, na koho se obrátit a jak to na GameConu chodí. Stránku si ulož do telefonu, ať ji máš po ruce. Pro tisk je tu i [brožura v PDF](soubory/obsah/download/brozura_2026.pdf).

<nav class="brozura_obsah" id="obsah" markdown="1">

## Obsah

1. [Vítej na GameConu!](brozura#vitej)
2. [Ubytování](brozura#ubytovani)
3. [Jídlo a občerstvení](brozura#jidlo)
4. [Účast na aktivitě](brozura#ucast)
5. [Bezplatný program](brozura#bezplatny-program)
6. [Zapoj se do Celohry!](brozura#celohra)
7. [Hlasuj pro figurku!](brozura#figurky)
8. [Před odjezdem](brozura#pred-odjezdem)
9. [Jak se ti na GameConu líbilo?](brozura#zpetna-vazba)
10. [Pravidla účasti](brozura#pravidla)
11. [Mapa areálu](brozura#mapa-arealu)
12. [Mapa vnitřních prostor](brozura#mapa-vnitrnich-prostor)
13. [Sponzoři](brozura#sponzori)

</nav>

<section class="brozura_sekce" markdown="1">

## Vítej na GameConu! {#vitej}

Po příjezdu se prosím **ohlas na Infopultu** (viz [mapka](brozura#mapa-arealu)) – vyřídíš tam registraci, ubytko, materiály, peníze atd. Na Infopult se neváhej stavit ani později, pokud budeš v průběhu GameConu potřebovat něco vyřídit nebo zařídit.

### Organizační barvy

<img class="brozura_obrazek" src="soubory/blackarrow/brozura/paska.avif" alt="" width="360" height="357">

<span class="brozura_paska brozura_paska-cervena">Červená páska</span> označuje organizátory. Klidně se na nás s čímkoliv obrať, buď pomůžeme, nebo tě nasměrujeme.

<span class="brozura_paska brozura_paska-oranzova">Oranžová páska</span> označuje ostatní pracovníky na GameConu. Rádi ti pomůžou.

<span class="brozura_paska brozura_paska-modra">Modrá páska</span> označuje vypravěče aktivit (vede nějakou z aktivit) a hermany v deskoherně (rádi ti vysvětlí pravidla kterékoliv deskovky).

**Ostatní barvy** mají účastníci (liší se podle typu ubytování).

### Pomoc a nouzové kontakty

<img class="brozura_obrazek" src="soubory/blackarrow/brozura/lektvar.avif" alt="" width="301" height="352">

**Všechny organizační záležitosti a problémy** řeší **Infopult**. V krizi a po zavíračce volej [735 513 899](tel:+420735513899). Infopult je otevřený:

<div class="brozura_tabulka" markdown="1">

| den     | otevřeno        |
|---------|-----------------|
| středa  | cca od 16:00    |
| čtvrtek | 9:00–21:00      |
| pátek   | 8:30–21:00      |
| sobota  | 8:30–20:00      |
| neděle  | 8:30–16:00      |

</div>

**Při úrazu nebo nemoci** vyhledej **zdravotníka** – přes Infopult nebo na [735 540 479](tel:+420735540479).

**Nepříjemné situace** řeší **Spřízněná duše**, což je skutečná psycholožka Míša. Seženeš ji přes Infopult, na [705 943 002](tel:+420705943002) nebo na [spriznenaduseGC@gmail.com](mailto:spriznenaduseGC@gmail.com).

[zpět na obsah](brozura#obsah){.brozura_zpet}

</section>

<section class="brozura_sekce" markdown="1">

## Ubytování {#ubytovani}

<img class="brozura_obrazek" src="soubory/blackarrow/brozura/postel.avif" alt="" width="360" height="304">

Ubytování je možné od **středy** přibližně po **16:00**. Přikoupit si ho můžeš i na místě na Infopultu.

Od pokoje má každý svůj klíč. **Ztráta klíče** je penalizovaná částkou **2 500 Kč**.

**Při odjezdu v pátek, sobotu a neděli** je nutné opustit pokoj **do 15:00**, **v pondělí** prosíme o odevzdání **klíče do 9:00**.

Pokud máš **ubytování na hotelu**, je potřeba opustit pokoj **do 10:00** – platí pro všechny dny bez výjimky.

### Klasické pokoje

Pokoje jsou buňkového typu, takže na dva pokoje budete mít společnou koupelnu, toaletu a chodbičku s lednicí. Sprchu zkus potkat aspoň jednou denně, ale klidně i víckrát! Kuchyňky najdeš vždycky na patře.

### Hotelové pokoje

Na pokoji najdeš ručník, fén, lednici a rychlovarnou konvici. K pokoji je na buňku/samostatně (podle zvoleného typu pokoje) koupelna, toaleta a malá kuchyňka. Sprchu zkus potkat aspoň jednou denně, ale klidně i víckrát!

Snídaně se podávají v přízemí budovy B (viz [mapka](brozura#mapa-arealu)) každý den v čase 6:30 až 9:30.

[zpět na obsah](brozura#obsah){.brozura_zpet}

</section>

<section class="brozura_sekce" markdown="1">

## Jídlo a občerstvení {#jidlo}

<img class="brozura_obrazek" src="soubory/blackarrow/brozura/pribory.avif" alt="" width="360" height="337">

### Objednaná jídla

<div class="brozura_tabulka" markdown="1">

| jídlo   | kdy                                 |
|---------|-------------------------------------|
| Snídaně | 8:00–10:00                          |
| Obědy   | 12:00–14:00, v neděli 13:00–15:00   |
| Večeře  | 17:30–19:30                         |

</div>

Všechna jídla se vydávají **v menze** (viz [mapka](brozura#mapa-arealu)). Co se kdy vaří, najdeš v [jídelníčku](jidelnicek).

**Vypravěči a orgové můžou v časové nouzi předbíhat!** Snažíme se to nedělat, ale v natěsnaném rozvrhu je to bohužel občas nutnost.

**Snídaně pro ubytované na hotelu** jsou **v přízemí budovy B** (viz [mapka](brozura#mapa-arealu)) mezi **6:30 a 9:30**.

### Občerstvení a pití

<img class="brozura_obrazek" src="soubory/blackarrow/brozura/lahve.avif" alt="" width="360" height="333">

**Zásobníky s vodou** jsou různě po GameConu. Kdo je nepoužívá, papá *vlastní* ibalgin.

Pokud máš doma vlastní **nicknack**, rozhodně si ho přibal. A pokud ne, můžeš navštívit **Infopult** a pořídit si jeden přímo od GameConu!

**Vedle Sraziště** (viz [mapka](brozura#mapa-arealu)) najdeš **foodtruck a kafestánek**. Foodtruck je otevřený mezi 10:00 a 20:00, kávu si můžeš dát od 9:00 do 19:15 (s výjimkou čtvrtku, kdy otevírá ve 14:00, a neděle, kdy zavírá ve 13:00).

**V klubu Vrtule** (viz [mapka](brozura#mapa-arealu)) si můžeš zase mezi 14:00 a 22:00 objednat klasické restaurační jídlo. Mezi 14:00 a 1:00 si zde koupíš i alko a nealko nápoje.

**Čaje a vodní dýmky** si můžeš dát zase **za deskohernou** (viz [mapka](brozura#mapa-arealu)). Otevřeno je mezi 8:30 a 2:00.

[zpět na obsah](brozura#obsah){.brozura_zpet}

</section>

<section class="brozura_sekce" markdown="1">

## Účast na aktivitě {#ucast}

### Kdy a kde mám být?

<img class="brozura_obrazek" src="soubory/blackarrow/brozura/vlajka.avif" alt="" width="336" height="352">

**15 minut předem na Srazišti u vlajky sekce tvé aktivity.** Najdeš ho před hlavním vchodem do kolejí. Pokud nebudeš na Srazišti včas, tvé místo propadne náhradníkovi!

**Na přednášku jdi rovnou do přednáškové místnosti.** Nachází se v budově B (projdi lobby do budovy B, zahni doprava a na konci chodby jsou napravo dveře označené „Přednášky“).

**Do Deskoherny můžeš volně přijít** každý den od 8:00 do 3:00 (ve čtvrtek od 14:00 a v neděli do 14:00). Najdeš ji v chodbě a tělocvičně nalevo vedle lobby.

### Nemůžu přijít, protože &lt;generická výmluva&gt;

<img class="brozura_obrazek" src="soubory/blackarrow/brozura/presypaci-hodiny.avif" alt="" width="360" height="341">

Může se stát. Prosíme, **co nejdříve se odhlas** (přes [program](program), nebo na Infopultu). Usnadníš hledání náhradníka a taky se tím vyhneš placení plného storna.

### Chci hrát, ale při přihlašování to nevyšlo

Sleduj [program](program) – na mnoha hrách odpadnou hráči doslova na poslední chvíli. Neboj se toho využít.

Ochomítej se kolem **vlajky Náhradníků**, možná budeš mít štěstí…

**Vůbec se neboj zkusit něco nového**, i v sekci, kterou neznáš! Všechny hry, u kterých není řečeno jinak, jsou uzpůsobené nováčkům.

[zpět na obsah](brozura#obsah){.brozura_zpet}

</section>

<section class="brozura_sekce" markdown="1">

## Bezplatný program {#bezplatny-program}

### Deskoherny

<img class="brozura_obrazek" src="soubory/blackarrow/brozura/kostky.avif" alt="" width="354" height="272">

Deskoherny jsou v chodbě a tělocvičně nalevo od lobby a jsou otevřeny **od 8 ráno do 3 v noci**.

Můžeš tam zajít s kamarády, nebo se můžeš přímo v Deskoherně domluvit na hře s někým dalším, kdo tam hledá spoluhráče.

**Pokud si chceš vyzkoušet hru, kterou neznáš**, naši hermani, stejně jako prodejci u stánků, tě ji rádi naučí – popřípadě pokud nevíš, co si zahrát, rádi ti něco na vyzkoušení doporučí.

### Přednášky

Přednáškovou místnost najdeš **v budově B** (viz [mapka](brozura#mapa-arealu)).

**Kapacita není nijak omezená**, stačí se na ně přihlásit buďto přes [program](program), nebo přes [programovou linii](prednasky).

### Komunitní srazy

V sobotu se v průběhu dne konají různé komunitní srazy, na kterých se můžeš potkat s dalšími členy určitých komunit. Kapacita je neomezená, stačí se na ně přihlásit buďto přes [program](program), nebo přes [programovou linii](doprovodny-program).

### Koncert

<img class="brozura_obrazek" src="soubory/blackarrow/brozura/housle.avif" alt="" width="235" height="354">

**Od 20:00 je v sobotu v klubu Vrtule koncert a párty.** Koncert trvá do půlnoci, ve Vrtuli můžeš sedět až do 3 v noci. Jen pozor na noční klid.

[zpět na obsah](brozura#obsah){.brozura_zpet}

</section>

<section class="brozura_sekce" markdown="1">

## Zapoj se do Celohry! {#celohra}

<img class="brozura_obrazek" src="soubory/blackarrow/brozura/lupa.avif" alt="" width="279" height="350">

Celohra je jediná hra, která se ti neokouká. Každý rok je psaná na aktuální téma GameConu, má nový příběh i herní mechaniky.

Je to ideální volnočasová aktivita pro chvíle, kdy zrovna nejsi na programu žádné z hlavních linií. Lze ji začít hrát kdykoliv. Hraj, dokud je čas, příští rok už na tebe zase bude čekat něco zbrusu nového.

**Jak se letos zapojit?** Pro další info navštiv [stránku Celohry](celohra).

[zpět na obsah](brozura#obsah){.brozura_zpet}

</section>

<section class="brozura_sekce" markdown="1">

## Hlasuj pro figurku! {#figurky}

<img class="brozura_obrazek" src="soubory/blackarrow/brozura/stetce.avif" alt="" width="197" height="352">

Chceš předvést své precizní dovednosti v malování figurek, anebo jen ukázat pokroky z předchozích ročníků malování na GameConu? Tak se chop štětce a maluj!

Nezáleží na tom, jestli to bude sci-fi robot, elegantní steampunkový gentleman nebo barbarský válečník, kreativitě se meze nekladou, jediná podmínka je, že přihlásit můžeš celkem **maximálně tři modely na celkovou plochu 15 × 15 cm**.

Veškeré informace a potřebné materiály k účasti v soutěži a k hlasování najdeš na [stránce malovací výzvy](wargaming/malovaci-vyzva).

### Vyfoť si figurku!

Máš cool figurku, o kterou se chceš podělit se světem? Není problém! Ve wargamingové části deskoherny najdeš fotokoutek navržený tak, aby tvoje miniatura vynikla se všemi svými detaily. Stačí vložit do připraveného stojanu mobil, položit figurku před pozadí a pak jen sdílet se světem! A nezapomeň nás označit.

[zpět na obsah](brozura#obsah){.brozura_zpet}

</section>

<section class="brozura_sekce" markdown="1">

## Před odjezdem {#pred-odjezdem}

<img class="brozura_obrazek" src="soubory/blackarrow/brozura/pytel.avif" alt="" width="354" height="342">

**Ukliď pokoj, pokud se nevlezou odpadky do koše, vynes je a sundej povlečení** – to nech složené na pokoji, díky. **Klíč vrať na vrátnici.**

**Nedoplatek** za dodatečně zapsané aktivity **srovnej na Infopultu, nebo [online](finance)**. Přeplatek si můžeš nechat na další rok, nebo ti ho můžeme vrátit – pokud ale nemáš nouzi, raději vracíme převodem.

[zpět na obsah](brozura#obsah){.brozura_zpet}

</section>

<section class="brozura_sekce" markdown="1">

## Jak se ti na GameConu líbilo? {#zpetna-vazba}

<img class="brozura_obrazek" src="soubory/blackarrow/brozura/srdce.avif" alt="" width="360" height="286">

Prosíme, **dej vědět nám i svému vypravěči, co se ti líbilo, nebo co by šlo případně do příště vychytat**, ať se můžeme všichni dál zlepšovat! Je to pro nás moc důležité. Zpětnou vazbu ke hrám můžeš předat osobně vypravěčům či orgům již na GameConu, nebo vyplnit online formulář (buď hned na místě, nebo kdykoliv později) – [dotazníky najdeš v praktických informacích](prakticke-informace#dotazniky).

[zpět na obsah](brozura#obsah){.brozura_zpet}

</section>

<section class="brozura_sekce" markdown="1">

## Pravidla účasti {#pravidla}

<img class="brozura_obrazek" src="soubory/blackarrow/brozura/pravidla.avif" alt="" width="360" height="307">

### 1: Nebuď debil

Pokud tě napadne něco zajímavého, **nejdřív se nad tím zamysli**, zejména večer… **Chovej se bezpečně a vstřícně** jak k sobě, tak hlavně k ostatním.

<img class="brozura_obrazek brozura_obrazek-vlevo" src="soubory/blackarrow/brozura/lebka.avif" alt="" width="352" height="274">

**Pokud se budeš chovat nevhodně nebo nebezpečně**, může tě vypravěč vyloučit z aktivity a/nebo org z GameConu, a to i bez udání důvodu či náhrady. A natrvalo. Zatím se to nestalo a byli bychom rádi, kdyby to tak i zůstalo.

### 2: Pij jen tolik, kolik zvládneš

<img class="brozura_obrazek" src="soubory/blackarrow/brozura/rum.avif" alt="" width="219" height="352">

Když se někde vyvrátíš, musíme to řešit (a uklízet) my, což nám na zábavě ani náladě rozhodně nepřidá (a ostatním okolo taky ne).

### 3: Zkus nám pomáhat

Nezvládáme být všude. Pokud potkáš nějaký problém nebo nedostatek, zkus to vyřešit sám, nebo nám dej vědět přes Infopult či organizátora.

### 4: Noční klid

<img class="brozura_obrazek" src="soubory/blackarrow/brozura/figurka.avif" alt="" width="313" height="360">

Existuje.

Někteří účastníci chtějí spát. Mnozí orgové a vypravěči potřebují spát, aby GameCon i pro tebe fungoval. A s univerzitou a policií chceme být zadobře.

**Minimalizuj proto bordel venku na ulici i na chodbách** – bordel ve smyslu hluku i odpadků. Pozdní útočiště ti poskytne **Noční kecárna**, která je v klubu Vrtule i s možností koupit si alko i nealko. Poslední drink ve 3:00. Popřípadě můžeš využít i Deskohernu, která zavírá také ve 3:00.

### 5: Vyjdi nám vstříc

<img class="brozura_obrazek" src="soubory/blackarrow/brozura/masky.avif" alt="" width="352" height="242">

Pokud o něco žádáme nebo něco říkáme, máme pro to důvod, ale ne vždycky čas (nebo manu) ho vysvětlovat – prosíme udělej to, i když ti to třeba přijde divný, nebo zbytečný.

[zpět na obsah](brozura#obsah){.brozura_zpet}

</section>

<section class="brozura_sekce" markdown="1">

## Mapa areálu {#mapa-arealu}

<a class="brozura_mapa" href="soubory/blackarrow/brozura/mapa-arealu-velka.avif" target="_blank"><img src="soubory/blackarrow/brozura/mapa-arealu.avif" alt="Mapa areálu kolejí v Ostravě-Porubě: budovy kolejí A až E, Infopult, Sraziště, deskoherny, wargaming, menza, foodtruck, čajovna, klub Vrtule a zastávky MHD" width="1200" height="1692" loading="lazy"></a>

Klepnutím mapu zvětšíš. Můžeš si ji i [stáhnout jako PDF](soubory/obsah/materialy/obecne/ostrava_mapa_arealu.pdf).

[zpět na obsah](brozura#obsah){.brozura_zpet}

</section>

<section class="brozura_sekce" markdown="1">

## Mapa vnitřních prostor {#mapa-vnitrnich-prostor}

<a class="brozura_mapa" href="soubory/blackarrow/brozura/mapa-vnitrnich-prostor-velka.avif" target="_blank"><img src="soubory/blackarrow/brozura/mapa-vnitrnich-prostor.avif" alt="Mapa vnitřních prostor kolejí s vyznačenými místy GameConu, popsanými v legendě pod mapou" width="1600" height="568" loading="lazy"></a>

Klepnutím mapu zvětšíš. Můžeš si ji i [stáhnout jako PDF](soubory/obsah/materialy/obecne/ostrava_mapa_vnitrnich_prostor.pdf).

<ul class="brozura_legenda">
<li><img src="soubory/blackarrow/brozura/ikony/infopult.avif" alt="" width="96" height="96">Infopult</li>
<li><img src="soubory/blackarrow/brozura/ikony/sraziste.avif" alt="" width="128" height="128">Sraziště</li>
<li><img src="soubory/blackarrow/brozura/ikony/recepce.avif" alt="" width="128" height="128">Recepce</li>
<li><img src="soubory/blackarrow/brozura/ikony/wargaming.avif" alt="" width="128" height="128">Wargaming</li>
<li><img src="soubory/blackarrow/brozura/ikony/prednasky.avif" alt="" width="128" height="128">Přednášky</li>
<li><img src="soubory/blackarrow/brozura/ikony/komunitni-srazy.avif" alt="" width="128" height="128">Komunitní srazy</li>
<li class="brozura_legenda-deskoherny"><img src="soubory/blackarrow/brozura/ikony/deskoherny.avif" alt="" width="424" height="96">Deskoherny č. 1 až 3</li>
<li><img src="soubory/blackarrow/brozura/ikony/foodtruck.avif" alt="" width="128" height="128">Foodtruck a kafekára</li>
<li><img src="soubory/blackarrow/brozura/ikony/cajovna.avif" alt="" width="128" height="128">Čajovna</li>
<li><img src="soubory/blackarrow/brozura/ikony/bar.avif" alt="" width="128" height="128">Klub a bistro Vrtule a noční kecárna</li>
<li><img src="soubory/blackarrow/brozura/ikony/snidarna.avif" alt="" width="128" height="128">Snídárna pro hotelové hosty</li>
<li><img src="soubory/blackarrow/brozura/ikony/zachod.avif" alt="" width="128" height="128">Záchod</li>
<li><img src="soubory/blackarrow/brozura/ikony/parkoviste.avif" alt="" width="128" height="128">Parkoviště pro partnery a hotelové hosty</li>
</ul>

### Důležitá telefonní čísla

<div class="brozura_tabulka" markdown="1">

| kontakt                  | telefon                                  |
|--------------------------|------------------------------------------|
| Infopult                 | [735 513 899](tel:+420735513899)         |
| Zdravotník               | [735 540 479](tel:+420735540479)         |
| Spřízněná duše           | [705 943 002](tel:+420705943002)         |

</div>

[zpět na obsah](brozura#obsah){.brozura_zpet}

</section>

<section class="brozura_sekce" markdown="1">

## Sponzoři {#sponzori}

Děkujeme našim sponzorům a partnerům!

(widget:sponzori)

[zpět na obsah](brozura#obsah){.brozura_zpet}

</section>

</div>
MD;

$obsahSql = "'" . $this->connection->real_escape_string($obsah) . "'";
$this->q(<<<SQL
INSERT INTO stranky (url_stranky, obsah, poradi)
VALUES ('brozura', {$obsahSql}, 0)
SQL,
);

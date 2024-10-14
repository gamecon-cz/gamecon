
# Architektura

Aplikace je postavená na obvyklém vzoru Model-view-controller (__MVC__). To znamená, že kód (třídy, skripty) je rozčleněn do skupin: Modely, views (pohledy), controllery (řadiče). Každá skupina dělá něco trochu jiného.

__Modely__ jsou třídy reprezentující základní pojmy na GameConu (aktivita, uživatel, …) a obsahují metody pro základní operace s nimi (přihlásit uživatele na aktivitu). Slovo _model_ se používá proto, že tyto třídy pro programátora představují model (reprezentaci) reálného světa, s kterou pracuje. Nachází se v složce `model` a jsou sdílené pro admin i veřejný web.

__Controllery__ jsou skripty, které pracují s modelem, zpracovávají požadavek od uživatele a rozhodují co s ním provést. Případně i provádějí na modelu změny (=volají metody), pokud uživatel nějaké požaduje. Typický controller pracuje následovně:

- Uživatel zadá url, která se díky `.htaccess` předá jako parametr do `index.php`.
- Uvnitř `index.php` se rozhodne, který controller má požadavek zpracovat (např. url `/blog/{něco}` zpracovává `blog.php`) a vybraný controller se includne.
- Controlleru se jako proměnné předají parametry, controller si z parametrů vyčte např. url, konkrétně část {něco}, a pokusí se načíst objekt Blog s id {něco}.
- Pokud se mu to podaří, zobrazí daný blogový příspěvek (zavolá si na to view), pokud ne, zobrazí chybu (zavolá si na to jiné view).

Controllery jsou zvlášť pro admin a web, na webu se nachází v složce `web/moduly`.

__Views__ jsou skripty, které obsahují html kód k zobrazení a pracují s daty (proměnnými), které jim controller načetl. Jejich smyslem je přetransformovat data na nějaký zobrazitelný výsledek. Obecně už by se v nich nemělo nic složitějšího počítat. Specificky v GC webu jsou views jen velmi tenké šablony a část kódu přechází na controller (více dál). V GC webu typicky patří k jednomu controlleru jeden soubor s view, na webu se nachází v `web/sablony`, ale daný controller taky view nemusí využívat vůbec.

> Je vhodné doplnit, že používání názvů view a controller je dost svévolné a různé frameworky k němu přistupují různě. Například [Nette](https://nette.org) říká svým šablonám View a třídám zpracovávajícím požadavek Controller, zatímco [Django](https://djangoproject.com) říká třídám zpracovávajícím požadavek View a šablonám prostě „šablony“.

## Model

Model je postaven nad relační databází a částečně využívá vzoru __ActiveRecord__. To znamená, že třída obvykle odpovídá určité tabulce a objekt odpovídá jednomu řádku z ní. Nejlepší bude začít jednoduchým příkladem, na kterém se dají popsat základní principy:

```php
$u = Uzivatel::zEmailu('shako@gamecon.cz');
$a = Aktivita::zId(123);
$a->prihlas($u);
echo 'Uživatel '.$u->jmeno().' se přihlásil na aktivitu '.$a->nazev();
```

### Načtení objektu (zNěčeho)

```php
$u = Uzivatel::zEmailu('shako@gamecon.cz'); // načte uživatele s daným mailem z databáze
```

Objekty se běžně nenačítají způsobem `new Trida(argumenty)`, jak je zvykem, ale voláním statické metody, například `Uzivatel::zMailu('nejaky@mail.cz')`, pokud chceme načíst uživatele na základě mailu. Těmto metodám se říká tovární metody (__Factory Method__) a třída jich může mít hodně. Tovární metody nemusí odpovídat jen sloupcům, mohou brát různé parametry a libovolně z nich kombinovat výsledný dotaz do databáze (viz třeba `Aktivita::zFiltru()`).

Metody z příkladu vrací vždy jeden objekt, jiné ale můžou vracet i kolekci, např. `Aktivita::zIds([123, 456])`. Všechny tovární metody v modelu můžou vrátit i `null` respektive prázdnou kolekci (místo toho, aby například skončily výjimkou). Chovají se tak stejně jako databáze (nad kterou jsou postaveny), která taky při nenalezení řádku vrátí prázdný výsledek (a nehlásí to jako chybu).

Konstruktor u těchto tříd je běžně chráněný (protected) a objekty se z vnějšku získávají právě jen továrními metodami.

> Styl názvů _zNěčeho_ odpovídá anglickému _bySomething_ často používanému v této situaci (např. fillBySomething nebo loadBySomething). Tovární metody záměrně vytváří programátor vždy ručně, aby se snadněji zachovávala zpětná kompatibilita (tedy nechceme je např. automaticky generovat pro každý sloupec v tabulce).

### Get/set metody

```php
echo $u->jmeno(); // vypíše 'Jan Novák'
$u->jmeno('Karel Nejedlík'); // změní jméno uživatele a zapíše do databáze
```

Pro přístup k atributu (sloupci) se používá jedna metoda pro čtení i zápis. Bez parametru vrátí hodnotu atributu, s zadaným parametrem hodnotu nastaví (podobně jako například v [jQuery](https://jquery.com)). Obvykle má třída jeden vnitřní atribut `$r` obsahující celý databázový řádek a v metodě pro čtení stačí vrátit hodnotu z něj, např. `$this->r['jmeno']`. Většina metod záměrně podporuje jen čtení, a ty které podporují zápis ihned zapisují do databáze (tzn. příkaz `$u->mail('shako@senam.cz')` ihned vyvolá update příkaz jedné hodnoty do databáze). Okamžitý zápis ostatně platí pro všechny metody, které nějak mění stav databáze (tj. i metoda `prihlas()` z prvního příkladu).

> Smyslem okamžitého zápisu je vyhnout se nutnosti volat explicitně metodu typu `uloz()` a předejít nejasnostem v odpovědnostech. Příkladem je i příkaz `$a->prihlas($u)` z vzorového kódu. Pokud by k zápisu přihlášení do db došlo až voláním `uloz()`, mělo by se volat na aktivitě nebo uživateli? A pokud např. na aktivitě, uložilo by i případné změny v uživateli? Co by se stalo, kdybych po přihlášení ještě změnil aktivitě další atributy, ale při uložení by zápis selhal např. protože uživatel má přihlášenou jinou aktivitu v stejný čas? Měl bych případné chyby ošetřovat až na místě, kde volám `uloz()`? Co když ho volám až úplně v jiném souboru?
>
> Lze správně namítnout, že okamžitý zápis vytváří zase jiné problémy, pokud chceme editovat objekt jako celek nebo vytvořit úplně nový. Proto se takové situace řeší jinak, a to konkrétně pomocí formulářů, o kterých bude ještě řeč.

### Formuláře

```php
if(Aktivita::editorZpracuj())     // pokud přišla nějaká POST data a byla zpracována
  back();                         // přesměrujeme na původní url a ukončíme skript
$a = Aktivita::zId($_GET['id']);  // jinak načteme aktivitu s požadovaným id
echo Aktivita::editor($a);        // a zobrazíme formulář pro její editaci
```

Pokud je potřeba editovat nebo vytvářet nový objekt v modelu, mnoho tříd nabízí metody pro zobrazení a zpracování editačního html formuláře. Princip jejich fungování je vždy podobný: zpracují se případná POST data, následně pokud POST data byla přítomna, provede se přesměrování v rámci principu [redirect after post](https://en.wikipedia.org/wiki/Post/Redirect/Get), a jinak se načte a zobrazí formulář.

Formulářem je možné objekty jak editovat, tak vytvářet nové. Aby to šlo, jsou metody pro práci s formulářem statické. Je tedy možné zavolat jak `Aktivita::editor($aktivita)`, pokud chci formulář na editování konkrétní aktivity, tak `Aktivita::editor(null)`, pokud chci prázdný formulář na vytvoření aktivity nové.

Metody pro práci s formuláři se v jednotlivých třídách můžou mírně lišit, ale vždycky odpovídají principu popsanému výš (pokud se budou používat formuláře tohoto typu i dál, chceme rozhraní sjednotit). Takto vypadá použití formuláře pro editaci novinek:

```php
$form = Novinka::form(get('id')); // načte formulář pro editaci nebo tvorbu novinky
$form->processPost();             // zpracuje POST data pokud jsou, případně přesměruje
echo $form->full();               // vypíše html kód formuláře
```

Tady je formulář jako samostatný objekt, metoda `processPost()` může rovnou interně ukončit skript a vyvolat přesměrování, pokud je potřeba. Funkce `get('id')` je jenom zkrácený zápis pro vrácení zadaného GET parametru nebo null, pokud parametr není zadán.

Formulář ve třídě `Novinka` je mimochodem automaticky generovaný, o čemž bude ještě řeč v souvislosti s abstraktní třídou `DbObject`. Pokud se ale formulář implementuje ručně, platí, že maximum kódu (ideálně všechen) by měl být sdílený pro editaci i vkládání (a případné rozdíly ošetřeny jen pomocí podmínek).

> Formuláře vlastně částečně porušují princip MVC, protože obsahují i zobrazení a zpracování dat. Tvoří v podstatě samostatnou gui komponentu, která ale MVC realizuje vnitřně. Modelem jsou interní data třídy, která se edituje, controllerem jsou metody transformující tato data na obsah formuláře a zpět (z POST hodnot) a view tvoří šablona pro formulář. Tento styl vznikl jako způsob, jak jednoduše sdílet gui komponenty mezi adminem a veřejným webem.

### DbObject

```php
class Novinka extends DbObject {

  protected static $tabulka = 'novinky';

  function nazev() {
    return $this->r['nazev'];
  }

  static function zTypu($typ) {
    return self::zWhere('vydat <= NOW() AND typ = $1 ORDER BY vydat DESC', [$typ]);
  }

}
```

Nejjednodušší třídy v modelu jsou ty, které dědí od třídy `DbObject`. Jsou to nejčistší použití vzoru ActiveRecord, kde třída 1 ku 1 odpovídá nějaké tabulce v databázi. Na vytvoření takové třídy stačí oddědit `DbObject` a nastavit statickou proměnnou `$tabulka` na název tabulky.

Z DbObjectu získáme děděním „zadarmo“ metody pro načítání z id, řeší za nás práci s databází, a stačí nám doimplementovat metody pro přístup k parametrům (viz. get/set metody), případně vlastní metody na načítání objektu zNěčeho (viz. načtení objektu). Pokud chceme, máme rovnou k dispozici i automaticky generovaný formulář (který je v mnoha případech plně použitelný). Příkladem takové třídy je `Novinka`.

### Složitější modely

Ne každá třída v modelu ale musí dědit `DbObject` a odpovídat právě jedné tabulce. Některé třídy jako zdroj dat používají složitější dotaz (příkladem je třída `Aktivita`, dotaz je vidět v metodě `zWhere`). Jiné třídy nemusí mít obraz v databázi vůbec (příkladem je `Cenik`) nebo mohou uložení dat do databáze řešit vlastním způsobem (tj. že jeden objekt neodpovídá jednomu řádku nějaké tabulky či dotazu a třída dělá při uložení v databázi víc různých úprav – funguje tak např. `ShopUbytovani`).

> Objektový návrh a relační databáze [jsou odlišné světy](https://en.wikipedia.org/wiki/Object-relational_impedance_mismatch) – databáze obsahuje tabulková data, zatímco objekty tvoří propojenou síť. Mapováním databáze na objekty 1 ku 1 bychom se připravili o mnoho výhod objektového programování, které v databázi neexistují. Z toho důvodu je preferovaný způsob navrhnout nejdřív objektový model a až následně způsob jeho uložení v databázi. Pokud to jde snadno, může být uložení 1 ku 1, pokud ne, přidají se pro čtení a zápis nějaké doplňující výpočty, které transformují data z/do databázového formátu.

V každém případě ale pokud třída už z databáze nějaká data čte nebo ukládá, ukládání se musí odehrát ihned při každé změně a práce s databází se musí odehrávat jenom v modelu. Jinými slovy nesmí se objevit SQL mimo model a třída nesmí předat svoje zdrojová data (atribut `$r`) ven.

> Důvodem zákazu SQL kódu a předávání `$r` je zapouzdření. Pokud by třída předala `$r`, zpřístupní tak všechny své načtené sloupce z databáze ostatním. Pokud by se objevilo mimo model SQL, bude ostatní kód spoléhat na existenci konkrétních tabulek a sloupců. V obou případech pak není možné tabulky nebo sloupce změnit, protože kód, který na ně spoléhá, by se musel všude vyhledat a ručně upravit. Pokud se ale struktura sloupců a tabulek používá jenom uvnitř třídy, je možné libovolně tabulky měnit, rozdělovat a upravovat, a stačí upravit vnitřní kód třídy. Vše ostatní zůstane funkční.

> Jedním z problémů mapování tříd na tabulky 1 ku 1 je navigovatelnost, tzn. schopnost dostat se z jednoho objektu (aktivita) k druhému (např. organizátor). Pokud mám 10 aktivit, procházím je v cyklu a zobrazuji organizátory, nechci posílat 10 dotazů do databáze kvůli načtení každého jednoho organizátora. Možných řešení je hodně (načíst předem všechno, říct předem, co načítat a co ne, načíst něco teprve až je potřeba, …). Zkoušíme různé varianty, a o tom, která je nejlepší a proč, si rádi popovídáme nad pivem.

<!-- TODO udělat z "popovídáme nad pivem" link na nějakou stránku pro dobrovolníky. Tu aktuální jsme právě přestali používat. -->

## Controller

Controllery jsou čisté skripty (neobsahující třídy ani funkce), které na základě parametrů zobrazí požadovaný výstup. Parametry jsou jim předány jako proměnné (např. `$u` je aktuálně přihlášený uživatel nebo null, `$url` je url adresa stránky, `$t` je automaticky načtená šablona, …). Výstup vypíše controller přímo na výstup (pomocí `echo` nebo za použití šablony).

Controller taky typicky zpracovává POST data, pokud chce uživatel provést nějakou akci (např. přihlásit se, smazat něco, …). V takovém případě nic nevykresluje, ale jen provede požadované změny a přesměruje uživatele zpět. Složitější příklad celého controlleru:

```php
<?php

// otestujeme, jestli je uživatel přihlášen (pokud není, ukončíme skript)
// proměnnou $u dostáváme přednastavenou zvenčí
if(!$u) {
  echo 'Tuto stránku můžou vidět jen přihlášení uživatelé.';
  return;
}

// zpracujeme případná POST data (tady např. smazání aktivity)
if(post('smazatId')) {
  $a = Aktivita::zId(post('smazatId'));
  $a->smaz();
  back('aktivita smazána');           // funkce, která přesměruje uživatele zpět a zobrazí hlášku
}

// zobrazíme aktivity pomocí šablony (více o šablonách níž)
// proměnné $url a $t dostáváme přednastavené zvenčí
$typ = $url->cast(1);                 // vezmeme 1. část url a předpokládáme, že je to typ aktivity
$aktivity = Aktivita::zTypu($typ);    // načteme aktivity daného typu
foreach($aktivity as $a) {            // projdeme všechny načtené aktivity
  $t->assign('aktivita', $a);         // nastavíme proměnnou šablony
  $t->parse('aktivity.aktivita');     // vytiskneme pomocí šablony jednu aktivitu
}
```

Proměnné, které dostává controller přednastavené, se dají vyčíst vždy v `index.php` na místě, kde se controller vkládá. Mezi adminem a webem je drobný rozdíl – v adminu se controller vkládá přímo pomocí `include`, na webu je k tomu spec. třída `Modul`, kde se samotný skript dynamicky načte jako metoda. Proto je na webu v controllerech občas k vidění `$this` a parametry se v indexu nastavují pomocí `$m->param('nazev', 'hodnota')`. Princip controllerů je ale na obou místech stejný.

Lze si všimnout, že i úplně prázdný controller něco zobrazí (je vidět menu, hlavička stránky, …). Je to proto, že výstup controlleru se ve výchozím stavu vloží do části webu pro obsah, a okolní menu atd… přidá `index.php` automaticky. Pokud se chceme okolního html kódu zbavit (např. protože chceme vrátit csv dokument místo html stránky) nebo ho nějak upravit (např. změnit atribut `<title>` v hlavičce), musíme to indexu sdělit. K tomu jsou určené zase určité speciální proměnné. V controlleru je nastavíme, index si je přečte, a podle toho se zachová. Příklad z webu, jak vypsat bílou stránku s JSONem:

```php
$this->bezStranky(true); // nastavíme, že výstup má být úplně čistý, bez okolní html stránky
echo json_encode(['id' => 123, 'name' => 'Něco']); // pomocí echo vypíšeme přímo nějaký json
```

## View

Jako view se používají šablony vycházející ze staršího projektu XTemplate. Základní myšlenka projektu je, že šablony jsou čistě deklarativní (neobsahují _žádný_ kód). Šablona vypadá jako obyčejné html, krom něj obsahuje navíc jenom komentáře `<!-- begin: blok -->` a `<!-- end: blok -->` rozdělující stránku na bloky (bloky mohou být vzájemně vnořené) a místa pro doplnění proměnných, např. `{promenna}` nebo `{promenna.getMetoda}`. Nejjednodušší šablona může vypadat takto:

```html
<!-- begin: tabulka -->
<table>
    <!-- begin: radek --><tr><td>{jmeno}</td><td>{prijmeni}</td></tr><!-- end: radek -->
</table>
<!-- end: tabulka -->
```

Při zpracování šablony se pak vždy nastaví proměnné a následně „vyparsuje“ určitý blok. Tím se za proměnné dosadí konkrétní hodnoty a html kód bloku s doplněnými proměnnými se uloží do bufferu. Následně se nastaví proměnné na jiné hodnoty a stejný blok se může vyparsovat znova. Na konci se všechen vyparsovaný html kód pošle na výstup.

Pokud například chceme vytisknout tabulku s jmény a příjmeními, nastavíme proměnné `jmeno` a `prijmeni` a následně vyparsujeme řádek, a to pro každého uživatele:

```php
$t = new XTemplate('vypis-uzivatelu.xtpl'); // načteme šablonu
foreach($uzivatele as $u) {
    $t->assign(['jmeno' => $u->jmeno(), 'prijmeni' => $u->prijmeni()]); // nastavíme proměnné
    $t->parse('tabulka.radek'); // vyparsujeme řádek tabulky
}
```

Poslední nevysvětlenou věcí z příkladu je ještě zápis `'tabulka.radek'`, který říká, že se má vyparsovat blok `radek` vnořený v bloku `tabulka`. Řádky po provedení kódu z příkladu visí v bufferu. Abychom dostali na výstup celou tabulku (nejen zatím vyparsovaný vnitřek), vyparsujeme ještě nadřízený blok `tabulka` (ten už nyní bude obsahovat všechny řádky z bufferu) a jeho obsah pak pošleme na výstup:

```php
$t->parse('tabulka'); // vyparsujeme nadřízený blok
$t->out('tabulka'); // pošleme blok na výstup
```

Abychom nastavování proměnných a načítání šablon nemuseli psát pořád dokola, máme k dispozici ještě pár zjednodušení:

- Místo nastavování jedné proměnné po druhé můžeme do proměnné nastavit objekt `$t->assgin(['u' => $uzivatel])` a v šabloně pak použít tečku k zavolání metody bez parametrů, tj. `{u.jmeno}` které odpovídá `$u->jmeno()`.
- Pokud v složce `web/sablony` máme šablonu se stejným názvem jako controller, potom ji dostaneme automaticky načtenou v proměnné `$t`. Kořenový blok šablony se pak musí taky jmenovat stejně jako controller a na konci se vyrenderuje automaticky, stačí tedy zavolat `parse()` na vnořené bloky a `out()` nemusíme volat vůbec.

> Jak je z příkladu vidět, k zpracování šablony je potřeba i nějaký php kód, který v tomto případě je v controlleru – dá se tedy říct, že část logiky view přechází do controlleru. Původní motivací takového návrhu bylo odstínit html kodéry od všech speciálních jazykových konstrukcí, kterými se to v běžných šablonovacích jazycích jen hemží. Ačkoli funkční, v dnešní době už je to přecejen poněkud fousatý přístup.

> Původní implementace [XTemplate](https://sourceforge.net/projects/xtpl/) je dnes už mrtvá. My používáme vlastní [zjednodušenou implementaci](./../model/XTemplate/XTemplate.php) vycházející z https://github.com/godric-cz/xtemplate, která využívá kompilaci šablon do pomocných php tříd a je výrazně rychlejší.

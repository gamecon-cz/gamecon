<?php
/** @var \Godric\DbMigrations\Migration $this */

// Spustit jen na ostré
if (!jsmeNaOstre()) {
    return;
}

$typ   = 2;
$vydat = '2025-11-08 09:00:00';
$url   = 'vyjadreni-konec-mdrd';
$nazev = 'Vyjádření ke konci mDrD';
$autor = 'GameCon';

$text = <<<'MD'
#Vyjádření ke konci Mistrovství v Dračím doupěti

Mistrovstvím v Dračím doupěti GameCon kdysi začal a postupně se z něj rozrostl v současný festival. Za třicet let jeho existence odehrály Mistrovství stovky družin, které si odnesly nespočet nezapomenutelných herních zážitků a staly se základem komunity a organizátorů, kteří GameCon tvoří dodnes.

Rádi bychom touto cestou poděkovali všem, kdo se na Mistrovství během let podíleli – hráčům a zejména PJům a organizátorům. Bez jejich nadšení a energie by GameCon nevypadal tak, jak ho známe dnes.

##Proč MDrD skončilo?

Mistrovství mělo svůj první „plánovaný“ konec už v roce 2013, kdy se jeho tehdejší vedení shodlo, že s pomalu upadající účastí je soutěžní formát dlouhodobě neudržitelný a bude důstojné ukončit ho v roce 2015 k jeho dvacátému výročí. Tehdy ale nastal obrat, v roce 2014 a následně i v roce 2015 účast nečekaně vzrostla, a tak se konec zrušil a výsledkem byl pouze vznik Legend Klubu dobrodruhů, které měly být původně jeho turnajovým nástupcem. Sekce tedy žila o celých deset let déle, než se původně doufalo. Účast i zájem v posledních letech ale znovu začal klesat a spolu s tím i možnost udržet Mistrovství v jeho původní podobě.

Vedoucí sekce (Guff) přišel s tím, že v současném formátu už nelze pokračovat a že by bylo vhodné zkusit formát změnit nebo rozšířit. A jelikož by změnou systému Mistrovství v Dračím doupěti přestalo být Mistrovstvím v Dračím doupěti, shodli jsme se v týmu na zrušení sekce a vytvoření nové. To vedlo k domluvě o letošním konci MDrD na jeho krásné 30. výročí, v době, kdy stále mělo solidní účast a dalo se mu dopřát důstojné rozloučení.

##Proč se teda nepokračovalo ve změněné podobě?

Guffův návrh na podobu možného nástupce Mistrovství v DrD spočíval v kombinaci DrD a Dračí Hlídky a vedl v týmu k živelné diskusi, která vyústila ve vznik druhého návrhu na vytvoření turnaje v DnD (resp. DnD a JaD). Tyto dva návrhy byly chápané jako nezávislé a na stole byla nejen možnost neschválit žádný nebo jen jeden z nich, ale klidně oba. Po dlouhé a náročné diskusi nakonec důvěru a podporu týmu získal jen návrh na nový turnaj v DnD.

Z takřka padesáti organizátorů GameConu měl každý své důvody hlasovat tak, jak hlasoval. Pokud bychom ale měli shrnout nejčastější argumenty, které zaznívaly, šlo především o tyto:

-   Kombinace dvou herních systémů (DrD a DH): Ačkoli si jsou systémy relativně podobné, mají mezi sebou rozdíly, které by mohly při hře ovlivnit rovné podmínky. Část organizátorů měla obavy, že by srovnání výsledků mezi jednotlivými družinami nebylo férové.

-   Dlouhodobě nižší důraz na zpětnou vazbu: MDrD mělo dlouhodobě oproti ostatním Programovým sekcím nižší nároky na reportovanou kvalitu. Někteří členové týmu se proto obávali, že by případný nový turnaj mohl být v porovnání se zbytkem festivalu méně kvalitní.

##Proč letos nebyla samostatná zpětná vazba?

Zpětná vazba k MDrD letos nevznikla, protože její tvorbu měl na starosti vedoucí sekce, který už po rozhodnutí o konci Mistrovství nepovažoval samostatný dotazník za potřebný. Své podněty a komentáře ale účastníci mohli psát do celogameconového dotazníku, kde byly také zaznamenány a ze kterých jsme při psaní tohoto vyjádření čerpali.

##Co bude dál?

Na GameConu bude možnost zúčastnit se nového turnaje v DnD/JaD, vedle toho samozřejmě zůstanou také Legendy Klubu dobrodruhů, které měly MDrD nahradit původně, a kromě zapojení do těchto soutěží budou PJové z Mistrovství samozřejmě moct nabídnout hry i v RPG (nebo jiných sekcích). Naším cílem je udržet na GameConu místo pro různé styly hraní a všechny komunity RPG hráčů.

Mrzí nás, že závěr Mistrovství provázely negativní emoce a nejasnosti. Věříme, že třicet let MDrD zůstane v paměti jako kus krásné historie GameConu a české RPG scény. Jeho odkaz a duch společného hraní zůstává součástí festivalu i dál – jen v nové podobě.

Zároveň víme o zájmu o novinky kolem chystaného turnaje v DnD/JaD. Vytvořit novou sekci od základů je ale časově náročné a spousta věcí se ještě ladí. Nechceme sdílet neúplné informace a hotovou koncepci chceme nejprve prezentovat zbytku týmu, proto prosíme o trpělivost – na turnaji se aktivně pracuje a všechno otevřeně představíme po podzimním organizačním sraze, který proběhne koncem listopadu. GameCon přece děláme pro účastníky, a čím víc jich o turnaji ví, tím lépe!
MD;

// Stabilní 32bit signed ID z URL (kompatibilní se starými zápisy)
$id32   = hexdec(substr(md5($url), 0, 8));
$textId = ($id32 > 0x7FFFFFFF) ? $id32 - 0x100000000 : $id32;

// Do SQL dáme text i další stringy přes UNHEX(...) -> odpadá escapování
$hexText  = bin2hex($text);
$hexUrl   = bin2hex($url);
$hexNazev = bin2hex($nazev);
$hexAutor = bin2hex($autor);

$this->q('START TRANSACTION');

$this->q("
    INSERT INTO `texty` (`id`, `text`)
    VALUES ($textId, UNHEX('$hexText'))
    ON DUPLICATE KEY UPDATE `text` = UNHEX('$hexText')
");

$this->q("
    INSERT INTO `novinky` (`typ`,`vydat`,`url`,`nazev`,`autor`,`text`)
    VALUES ($typ, '$vydat', UNHEX('$hexUrl'), UNHEX('$hexNazev'), UNHEX('$hexAutor'), $textId)
    ON DUPLICATE KEY UPDATE
        `typ`   = VALUES(`typ`),
        `vydat` = VALUES(`vydat`),
        `nazev` = VALUES(`nazev`),
        `autor` = VALUES(`autor`),
        `text`  = VALUES(`text`)
");

$this->q('COMMIT');

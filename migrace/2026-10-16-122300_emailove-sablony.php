<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Subject and text of the e-mails that used to be written directly in code, so they can be changed from the admin.
// Blank is not allowed even by hand in the database, hence the CHECK (the application guards it too).
$this->q(<<<SQL
CREATE TABLE IF NOT EXISTS emailova_sablona
(
    kod         VARCHAR(100)        NOT NULL COLLATE utf8mb4_czech_ci,
    predmet     VARCHAR(255)        NOT NULL COLLATE utf8mb4_czech_ci,
    text        TEXT                NOT NULL COLLATE utf8mb4_czech_ci,
    zmenil      BIGINT(20) UNSIGNED          DEFAULT NULL,
    zmeneno_kdy TIMESTAMP           NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    CONSTRAINT emailova_sablona_predmet_neprazdny CHECK (TRIM(predmet) <> ''),
    CONSTRAINT emailova_sablona_text_neprazdny CHECK (TRIM(text) <> ''),
    FOREIGN KEY (zmenil) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL ON UPDATE CASCADE,
    PRIMARY KEY (kod)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

// The default wording is the one that was in code. INSERT IGNORE so that a repeated run
// does not overwrite text the admin already edited. Symbols are %lowercase% variables
// and %UPPERCASE% system values, a literal percent sign is %%.
$sablony = [];

$oddelovac = str_repeat('═', 50);

// --- Account and registration

$sablony['obnova_hesla'] = [
    'predmet' => 'Obnova hesla na GameConu',
    'text'    => <<<'TEXT'
Ahoj,

dostali jsme žádost o nastavení nového hesla na Gamecon.cz. Klikni na odkaz níže a nastav si nové heslo:

<a href="%odkaz%">%odkaz%</a>

Odkaz platí 1 hodinu. Pokud jsi o změnu hesla nežádal/a, tento e-mail ignoruj — heslo zůstane beze změny.

Tvůj
organizační tým GameConu
TEXT,
];

$sablony['rychla_registrace'] = [
    'predmet' => 'Registrace na GameCon.cz',
    'text'    => <<<'TEXT'
Ahoj,

děkujeme, že se letos účastníš GameConu! Kliknutím na odkaz níže potvrdíš registraci na web, kde si nastavíš přezdívku a heslo tak, ať můžeš používat web a přijet třeba i příští rok. V případě pozdější registrace na web by bylo nutné nechat si vygenerovat heslo znovu.

<a href="%odkaz_k_potvrzeni%">%odkaz_k_potvrzeni%</a>
TEXT,
];

// --- Aktivity

$sablony['uvolnene_misto'] = [
    'predmet' => 'Gamecon: Volné místo na aktivitě %aktivita%',
    'text'    => <<<'TEXT'
Na aktivitě %aktivita%, která se koná v %cas%, se uvolnilo místo. Tento e-mail dostáváš, protože máš nastavené sledování uvedené aktivity. Přihlaš se na aktivitu přes <a href="https://gamecon.cz/program">program</a> (pospěš si, ať ti místo nezabere někdo jiný).
TEXT,
];

$sablony['nedostaveni_se_na_aktivitu'] = [
    'predmet' => 'Nedostavení se na aktivitu',
    'text'    => <<<'TEXT'
Ahoj,

vypadá to, že aktivita %aktivita% proběhla bez tebe!

Chápeme, že může nastat situace, kvůli které se nemůžeš, nebo nechceš aktivity zúčastnit, pro příště tě ale moc prosíme

VŽDY SE Z AKTIVITY ODHLAS.

Žádáme tě o to z toho důvodu, aby na tebe vypravěč a ostatní účastníci nemuseli čekat a zjišťovat, jestli dorazíš. A taky to značně urychlí hledání náhradníka. Pokud se odhlásíš dříve než %lhuta_bez_pokuty% před začátkem aktivity, vrátíme ti za ni všechny peníze. Pokud později, tak %pokuta_procenta% %%.

Jedná se o omyl? Někde v systému pravděpodobně nastala chyba, za kterou se moc omlouváme. Dej nám o ní prosím vědět, abychom to opravili a znovu tě zbytečně neděsili.

Děkujeme za spolupráci!

Tvůj,
Organizační tým GC
TEXT,
];

$sablony['uzavreni_prezence'] = [
    'predmet' => 'Gamecon: Uzavři prosím prezenci na %na_aktivitach%',
    'text'    => <<<'TEXT'
Zkontroluj prezenci a zavři
%aktivity%
TEXT,
];

// --- Unregistering from GameCon

$uvodOdhlaseni      = 'Právě jsme tě odhlásili z letošního Gameconu.';
$oddelovacOdhlaseni = str_repeat('═', mb_strlen($uvodOdhlaseni));
$sablony['odhlaseni_z_gameconu'] = [
    'predmet' => 'Byl%a% jsi odhlášen%a% z Gameconu %ROCNIK%',
    'text'    => <<<TEXT
{$uvodOdhlaseni}

{$oddelovacOdhlaseni}

Pokud jsi platbu zapomněl%a% poslat, přihlaš se zpět v další vlně aktivit, která bude %dalsi_vlna% a platbu ohlídej.
TEXT,
];

$sablony['hromadne_odhlaseni_neplatic_zruseno'] = [
    'predmet' => 'Odhlášené %co%',
    'text'    => <<<'TEXT'
Jelikož tvé finance nedorazili na účet Gameconu včas, museli jsme %zruseno%

Aktivity si můžeš znovu přihlásit v další vlně, předměty si můžeš znovu objednat kdykoliv. Jen prosíme ohlídej své platby.

Tvůj
organizační tým GameConu
TEXT,
];

$uvodVarovaniNeplatice = 'Prosíme zaplať své objednávky na Gamecon %ROCNIK%';
$oddelovacVarovaniNeplatice = str_repeat('═', mb_strlen('Prosíme zaplať své objednávky na Gamecon 2026'));
$sablony['varovani_brzke_odhlaseni_neplatic'] = [
    'predmet' => 'Nezaplacené objednávky Gamecon %ROCNIK%',
    'text'    => <<<TEXT
{$uvodVarovaniNeplatice}

{$oddelovacVarovaniNeplatice}

Ahoj %jmeno%, zaplať prosím všechny své objednávky, jinak Tě budeme muset za tři dny odhlásit z Gameconu %ROCNIK%
TEXT,
];

// --- Debtor reminders. Generated from the original code UpominaniDluzniku::dejEmailZpravu().

$sablony['upominka_tyden_pritomen'] = [
    'predmet' => 'GameCon %ROCNIK% - nedoplatky',
    'text'    => <<<'TEXT'
Ahoj!

Doufáme, že tě letošní GameCon bavil!

V systému ti nicméně zbyly nějaké nedoplatky, pravděpodobně za last moment aktivity nebo jiné objednávky během GC. Konkrétně se jedná o %dluh% korun. Můžeš nám prosím nedoplatek co nejdřív srovnat?

Stačí poslat danou částku na GC účet %UCET_CZ% (%IBAN%) s variabilním symbolem %vs%, popř. využít platební QR kód přiložený níže.

Jakékoliv dotazy, nejasnosti nebo reklamace směřuj prosím v odpovědi na tento e-mail, nebo na finance@gamecon.cz.

Moc děkujeme a těšíme se zase za rok!

PS: rovnou si dovolíme i připomenout možnost vyplnění zpětných vazeb na GC i na jednotlivé aktivity, které jsou pro nás velmi důležité pro další zlepšování. Najdeš je tady: https://gamecon.cz/prakticke-informace#dotazniky
TEXT,
];

$sablony['upominka_tyden_jen_prihlasen'] = [
    'predmet' => 'GameCon %ROCNIK% - nedoplatky',
    'text'    => <<<'TEXT'
Ahoj!

Mrzí nás, že ses na letošní GameCon nakonec nedostal%a%.

I tak ti ale na GC účtu zůstal nedoplatek za objednávky z letošní přihlášky. Konkrétně se jedná o %dluh% korun. Můžeš nám prosím nedoplatek co nejdřív srovnat?

Stačí poslat danou částku na GC účet %UCET_CZ% (%IBAN%) s variabilním symbolem %vs%, popř. využít platební QR kód přiložený níže.

Jakékoliv dotazy, nejasnosti nebo reklamace směřuj prosím v odpovědi na tento e-mail, nebo na finance@gamecon.cz.

Děkujeme a snad se uvidíme na některém z dalších ročníků!
TEXT,
];

$sablony['upominka_tyden_nedorazil_drive'] = [
    'predmet' => 'GameCon %ROCNIK% - nedoplatky',
    'text'    => <<<'TEXT'
Ahoj!

Ozýváme se ohledně tvého účtu na GameConu.

Z GameConu %rok_posledni_ucasti% ti na GC účtu zůstal nedoplatek. Konkrétně se jedná o %dluh% korun. Můžeš nám prosím nedoplatek co nejdřív srovnat?

Stačí poslat danou částku na GC účet %UCET_CZ% (%IBAN%) s variabilním symbolem %vs%, popř. využít platební QR kód přiložený níže.

Jakékoliv dotazy, nejasnosti nebo reklamace směřuj prosím v odpovědi na tento e-mail, nebo na finance@gamecon.cz.

Děkujeme a snad se uvidíme na některém z dalších ročníků!
TEXT,
];

$sablony['upominka_tyden_nedorazil_nikdy'] = [
    'predmet' => 'GameCon %ROCNIK% - nedoplatky',
    'text'    => <<<'TEXT'
Ahoj!

Ozýváme se ohledně tvého účtu na GameConu.

Na tvém GC účtu evidujeme nedoplatek. Konkrétně se jedná o %dluh% korun. Můžeš nám prosím nedoplatek co nejdřív srovnat?

Stačí poslat danou částku na GC účet %UCET_CZ% (%IBAN%) s variabilním symbolem %vs%, popř. využít platební QR kód přiložený níže.

Jakékoliv dotazy, nejasnosti nebo reklamace směřuj prosím v odpovědi na tento e-mail, nebo na finance@gamecon.cz.

Děkujeme a snad se uvidíme na některém z dalších ročníků!
TEXT,
];

$sablony['upominka_mesic_pritomen'] = [
    'predmet' => 'GameCon %ROCNIK% - PŘIPOMÍNKA nedoplatků',
    'text'    => <<<'TEXT'
Ahoj!

Doufáme, že ti na GameCon zůstaly krásné vzpomínky!

V systému ti nicméně zbyly nějaké nedoplatky, pravděpodobně za last moment aktivity nebo jiné objednávky během GC. Konkrétně se jedná o %dluh% korun a nám už se velmi blíží účetní uzávěrka. Můžeš nám prosím nedoplatek co nejdřív srovnat?

Stačí poslat danou částku na GC účet %UCET_CZ% (%IBAN%) s variabilním symbolem %vs%, popř. využít platební QR kód přiložený níže.

Jakékoliv dotazy, nejasnosti nebo reklamace směřuj prosím v odpovědi na tento e-mail, nebo na finance@gamecon.cz.

Moc děkujeme a těšíme se zase za rok!

PS: stále ještě případně zbývá trochu času na vyplnění zpětné vazby na GC i jednotlivé aktivity. Dotazníky najdeš tady: https://gamecon.cz/prakticke-informace#dotazniky. Jejich vyplnění je pro další zlepšování akce velmi důležité.
TEXT,
];

$sablony['upominka_mesic_jen_prihlasen'] = [
    'predmet' => 'GameCon %ROCNIK% - PŘIPOMÍNKA nedoplatků',
    'text'    => <<<'TEXT'
Ahoj!

Mrzí nás, že ses na letošní GameCon nakonec nedostal%a%.

I tak ti ale na GC účtu zůstal nedoplatek za objednávky z letošní přihlášky. Konkrétně se jedná o %dluh% korun a nám už se velmi blíží účetní uzávěrka. Můžeš nám prosím nedoplatek co nejdřív srovnat?

Stačí poslat danou částku na GC účet %UCET_CZ% (%IBAN%) s variabilním symbolem %vs%, popř. využít platební QR kód přiložený níže.

Jakékoliv dotazy, nejasnosti nebo reklamace směřuj prosím v odpovědi na tento e-mail, nebo na finance@gamecon.cz.

Děkujeme a snad se uvidíme na některém z dalších ročníků!
TEXT,
];

$sablony['upominka_mesic_nedorazil_drive'] = [
    'predmet' => 'GameCon %ROCNIK% - PŘIPOMÍNKA nedoplatků',
    'text'    => <<<'TEXT'
Ahoj!

Ozýváme se ohledně tvého účtu na GameConu.

Z GameConu %rok_posledni_ucasti% ti na GC účtu zůstal nedoplatek. Konkrétně se jedná o %dluh% korun a nám už se velmi blíží účetní uzávěrka. Můžeš nám prosím nedoplatek co nejdřív srovnat?

Stačí poslat danou částku na GC účet %UCET_CZ% (%IBAN%) s variabilním symbolem %vs%, popř. využít platební QR kód přiložený níže.

Jakékoliv dotazy, nejasnosti nebo reklamace směřuj prosím v odpovědi na tento e-mail, nebo na finance@gamecon.cz.

Děkujeme a snad se uvidíme na některém z dalších ročníků!
TEXT,
];

$sablony['upominka_mesic_nedorazil_nikdy'] = [
    'predmet' => 'GameCon %ROCNIK% - PŘIPOMÍNKA nedoplatků',
    'text'    => <<<'TEXT'
Ahoj!

Ozýváme se ohledně tvého účtu na GameConu.

Na tvém GC účtu evidujeme nedoplatek. Konkrétně se jedná o %dluh% korun a nám už se velmi blíží účetní uzávěrka. Můžeš nám prosím nedoplatek co nejdřív srovnat?

Stačí poslat danou částku na GC účet %UCET_CZ% (%IBAN%) s variabilním symbolem %vs%, popř. využít platební QR kód přiložený níže.

Jakékoliv dotazy, nejasnosti nebo reklamace směřuj prosím v odpovědi na tento e-mail, nebo na finance@gamecon.cz.

Děkujeme a snad se uvidíme na některém z dalších ročníků!
TEXT,
];

// --- Balance expiry

$sablony['promlceni_varovani_mesic'] = [
    'predmet' => 'GameCon %ROCNIK% - Tvůj zůstatek %zustatek% Kč bude promlčen',
    'text'    => <<<'TEXT'
Ahoj %jmeno%,

máš na GameCon účtu zůstatek %zustatek% Kč, ale %posledni_roky% jsi se GameConu nezúčastnil%a%.

Hrozí promlčení zůstatku na Tvém GameCon účtu kvůli tvé neaktivitě.

Zůstatek bude promlčen krátce po skončení letošního GameConu %ROCNIK%.

Co můžeš proti tomu udělat:
- Registrovat se na letošní GameCon %ROCNIK% (registrace začnou %registrace_od%)
- Kontaktovat nás na info@gamecon.cz a domluvit se, kam chceš zůstatek vrátit

Tvůj zůstatek: %zustatek% Kč

Děkujeme za pochopení!
Tvůj
organizační tým GameConu
TEXT,
];

$sablony['promlceni_varovani_tyden'] = [
    'predmet' => 'PŘIPOMÍNKA: GameCon %ROCNIK% - Tvůj zůstatek %zustatek% Kč bude promlčen',
    'text'    => <<<'TEXT'
Ahoj %jmeno%,

toto je připomínka našeho předchozího e-mailu.

Máš na GameCon účtu zůstatek %zustatek% Kč, ale %posledni_roky% jsi se GameConu nezúčastnil%a%.

REGISTRACE UŽ ZA TÝDEN!
Registrace na GameCon %ROCNIK% začínají %registrace_od%.

Pokud se nezaregistruješ a nezúčastníš se letošního GameConu, tvůj zůstatek bude promlčen krátce po skončení akce.

Co můžeš udělat:
- Registrovat se na letošní GameCon %ROCNIK%
- Kontaktovat nás na info@gamecon.cz, pokud máš dotazy

Tvůj zůstatek: %zustatek% Kč

Děkujeme!
Tvůj
organizační tým GameConu
TEXT,
];

// --- Messages for the CFO

$sablony['cfo_brzke_odhlaseni_neplaticu'] = [
    'predmet' => 'Odhlášení neplatičů: %kdy% bude hromadně odhlášeno %pocet% neplatičů z GC',
    'text'    => <<<TEXT
%kdy% Gamecon systém odhlásí %pocet% účastníků z letošního Gameconu, protože jsou neplatiči.
{$oddelovac}

Sirien: zkontroluj partnery ty kokos ať se hromadně nezjebou i se svym ubytkem

{$oddelovac}

%seznam%
TEXT,
];

$sablony['cfo_nesparovane_platby'] = [
    'predmet' => '%kdy% bude hromadné odhlášení a stále máme %pocet% nespárovaných plateb',
    'text'    => <<<TEXT
%kdy% Gamecon systém hromadně odhlásí neplatiče. Přitom ale máme %pocet% nespárovaných plateb a hrozí komplikace.

{$oddelovac}

%seznam%
TEXT,
];

$sablony['cfo_neplatici_varovani_odeslano'] = [
    'predmet' => '%pocet% neplatičům jsme právě odeslali bububu email',
    'text'    => <<<TEXT
%pocet% potenciálním neplatičům jsme právě odeslali varovný email, že pokud nezaplatí včas, budeme je muset odhlásit z Gameconu.

{$oddelovac}

Příklad odeslaného emailu:

%priklad_predmet%

{$oddelovac}

%priklad_text%
TEXT,
];

$sablony['cfo_odhlaseni_neplaticu_provedeno'] = [
    'predmet' => 'Hromadně odhlášeno %pocet% účastníků z GC',
    'text'    => <<<TEXT
Právě jsme odhlásili %pocet% účastníků z letošního Gameconu.

{$oddelovac}

%zaznamy%
TEXT,
];

$sablony['cfo_podezrele_vysoka_platba'] = [
    'predmet' => 'Podezřele vysoká platba automaticky spárována s účastníkem',
    'text'    => <<<'TEXT'
Byla automaticky spárována podezřele vysoká platba s účastníkem.

Detaily platby:
- Částka: %castka% Kč
- Variabilní symbol: %vs%
- Datum: %datum%
- Název protiúčtu: %protiucet%

Spárováno s účastníkem:
- ID: %id_uzivatele%
- Jméno: %jmeno_uzivatele%
- Email: %email_uzivatele%

Zkontrolujte prosím správnost spárování v administraci:
%odkaz_uzivatel%

Pokud se jedná o chybné spárování (např. institucionální platbu), odpojte platbu od účastníka na stránce nesparovaných plateb:
%odkaz_nesparovane%
TEXT,
];

$sablony['cfo_promlceni_nikdo'] = [
    'predmet' => 'Automatické promlčení zůstatků GC %ROCNIK%: 0 promlčených',
    'text'    => <<<'TEXT'
Automatické promlčení zůstatků po skončení GameConu %ROCNIK% bylo provedeno.

Výsledek: Žádní uživatelé nesplňovali kritéria pro promlčení zůstatků.

GameCon skončil: %gc_skoncil%
TEXT,
];

$sablony['cfo_promlceni_pred'] = [
    'predmet' => 'Automatické promlčení zůstatků GC %ROCNIK%: Report před promlčením',
    'text'    => <<<'TEXT'
Automatické promlčení zůstatků po skončení GameConu %ROCNIK% bude nyní provedeno.

Přehled před promlčením:
- Počet uživatelů: %pocet_uzivatelu%
- Celková suma k promlčení: %suma% Kč

V příloze najdete detailní report se všemi uživateli, jejich účastí na GC a částkami k promlčení.

GameCon skončil: %gc_skoncil%
TEXT,
];

$sablony['cfo_promlceni_dokonceno'] = [
    'predmet' => 'Automatické promlčení zůstatků GC %ROCNIK%: DOKONČENO',
    'text'    => <<<'TEXT'
Automatické promlčení zůstatků po skončení GameConu %ROCNIK% bylo úspěšně dokončeno.

Výsledek promlčení:
- Promlčeno uživatelů: %pocet%
- Celková promlčená suma: %suma% Kč

V příloze najdete aktuální report zůstatků všech uživatelů v databázi po promlčení.

GameCon skončil: %gc_skoncil%
TEXT,
];

$sablony['cfo_promlceni_varovani_mesic_odeslano'] = [
    'predmet' => 'Varovné e-maily o promlčení zůstatků: odesláno %pocet% e-mailů',
    'text'    => <<<'TEXT'
Varovné e-maily o promlčení zůstatků (1 měsíc před otevřením registrací) byly odeslány.

Počet uživatelů: %pocet%
Registrace začínají: %registrace_od%
Počet let neúčasti: %pocet_let%

Uživatelé byli varováni, že jejich zůstatky budou promlčeny po skončení GameConu %ROCNIK%, pokud se nezúčastní.
TEXT,
];

$sablony['cfo_promlceni_varovani_tyden_odeslano'] = [
    'predmet' => 'Připomínka promlčení zůstatků: odesláno %pocet% e-mailů',
    'text'    => <<<'TEXT'
Připomínkové e-maily o promlčení zůstatků (1 týden před otevřením registrací) byly odeslány.

Počet uživatelů: %pocet%
Registrace začínají: %registrace_od%
Počet let neúčasti: %pocet_let%

Uživatelé byli znovu upozorněni, že jejich zůstatky budou promlčeny po skončení GameConu %ROCNIK%, pokud se nezúčastní.
TEXT,
];

foreach ([
    'tyden'   => ['Upomínky dlužníkům', 'Upomínkové', '1 týden'],
    'mesic'   => ['PŘIPOMÍNKA upomínek dlužníkům', 'Připomínkové', '1 měsíc'],
    'rucni'   => ['Ručně rozeslané upomínky dlužníkům', 'Ručně rozeslané upomínkové', 'ruční rozeslání'],
    'vlastni' => ['Upomínky vlastním zněním', 'Vlastním zněním rozeslané upomínkové', 'vlastní znění'],
] as $typ => [$nadpis, $typTextu, $nazevTypu]) {
    $sablony["cfo_upominky_{$typ}_odeslano"] = [
        'predmet' => "{$nadpis}: odesláno %pocet% e-mailů",
        'text'    => <<<TEXT
{$typTextu} e-maily dlužníkům ({$nazevTypu} po skončení GameConu %ROCNIK%) byly odeslány.

Počet dlužníků: %pocet%
Přeskočeno (upomínku už dostali dřív): %pocet_preskocenych%
Přeskočeno (dluh pod prahem %prah% Kč): %pocet_pod_prahem%
Konec GameConu: %konec_gc%

{$oddelovac}

Příklad odeslaného emailu:

%priklad_predmet%

{$oddelovac}

%priklad_text%
TEXT,
    ];
}

// --- Messages for the team

$sablony['info_odhlasil_ale_platil'] = [
    'predmet' => "Uživatel '%jmeno_nick%' (%id_uzivatele%) %odhlaseni% ale platil",
    'text'    => <<<'TEXT'
Uživatel %jmeno_nick% (ID %id_uzivatele%) %odhlaseni% z GameConu, ale v aktuálním roce (%ROCNIK%) si poslal %castka% Kč. Bude vhodné to prověřit popř. smazat platby z připsaných a dát do zůstatku v seznamu uživatelů, aby mu peníze nepropadly
TEXT,
];

$sablony['info_odhlasil_mel_objednavky'] = [
    'predmet' => 'Uživatel %odhlaseni% a měl objednávky',
    'text'    => <<<'TEXT'
Uživatel %jmeno_nick% (ID %id_uzivatele%) %odhlaseni% z GameConu a v aktuálním roce (%ROCNIK%) měl tyto objednávky:

%objednavky%

Uvolnila se tak místa / položky.
TEXT,
];

$sablony['info_hromadna_aktivace_aktivit'] = [
    'predmet' => 'Hromadně aktivováno %pocet% aktivit',
    'text'    => <<<'TEXT'
Právě jsme aktivovali %pocet% aktivit.

%komplikace%
TEXT,
];

// --- Reports. HTML without newlines, because newlines in an HTML e-mail become line breaks.

$sablony['bfgr_report'] = [
    'predmet' => 'BFGR Report - ročník %rocnik_reportu%',
    'text'    => '<p>Ahoj %jmeno%,</p>'
        . '<p>BFGR report najdeš v příloze.</p>'
        . '<p><strong>Ročník:</strong> %rocnik_reportu%<br><strong>Vygenerováno:</strong> %vygenerovano%</p>'
        . '<p>S pozdravem,<br>GameCon systém</p>',
];

$sablony['bfgr_report_chyba'] = [
    'predmet' => 'Chyba při generování BFGR reportu',
    'text'    => '<p>Ahoj %jmeno%,</p>'
        . '<p>Bohužel došlo k chybě při generování BFGR reportu.</p>'
        . '<p><strong>Chyba:</strong> %chyba%</p>'
        . '<p>Kontaktuj prosím administrátora systému.</p>'
        . '<p>S pozdravem,<br>GameCon systém</p>',
];

$uvozovkyKRetezci = fn(string $retezec): string => "'" . $this->connection->real_escape_string($retezec) . "'";

foreach ($sablony as $kod => $sablona) {
    $kodSql     = $uvozovkyKRetezci($kod);
    $predmetSql = $uvozovkyKRetezci($sablona['predmet']);
    $textSql    = $uvozovkyKRetezci($sablona['text']);
    $this->q(<<<SQL
INSERT IGNORE INTO emailova_sablona(kod, predmet, text)
VALUES ({$kodSql}, {$predmetSql}, {$textSql})
SQL,
    );
}

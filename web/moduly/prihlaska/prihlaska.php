<?php

use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;
use Gamecon\Shop\Shop;
use Gamecon\Pravo;
use Gamecon\Web\ZpracovaneObrazky;

/**
 * @see web/sablony/blackarrow/prihlaska.xtpl
 * @var \Gamecon\XTemplate\XTemplate $t
 * @var Uzivatel $u
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

$this->blackarrowStyl(true);
$this->bezPaticky(true);
$this->info()->nazev(t('prihlaska.nazev'));
// Rámec stránky je přeložený ve všech stavech. E-shop (předměty, ubytování, jídlo, vstupné) je zatím česky a má
// v šabloně lang="cs" (jazykEshopu); vidí ho jen přihlášený, vyhledávač dostane jen stavy bez formuláře.
$this->setObsahPrelozeny(true);

function cestaKObrazkuEshopPredmetu(string $soubor): string
{
    return adresarKObrazkuEshopPredmetu() . '/' . $soubor;
}

function adresarKObrazkuEshopPredmetu(): string
{
    return ZpracovaneObrazky::adresarObrazkuEshopu();
}

/**
 * @throws \RuntimeException
 * Pomocná funkce pro náhled předmětu pro aktuální ročník
 */
function miniauturaNahleduPredmetu(string $cestaKObrazku): string
{
    return Nahled::zeSouboru($cestaKObrazku)
        ->kvalita(98)
        ->pasuj(268)
        ->url();
}

/**
 * @throws \RuntimeException
 * Pomocná funkce pro náhled předmětu pro aktuální ročník
 */
function nahledPredmetu(string $cestaKObrazku): string
{
    return Nahled::zeSouboru($cestaKObrazku)
        ->kvalita(98)
        ->url();
}

/** Odkaz do věty z katalogu (htmlSVlozkami()), text i titulek jsou prostý text z t(). */
$odkaz = static fn (string $href, string $text, ?string $titulek = null): string => '<a href="' . htmlspecialchars($href) . '"'
    . ($titulek !== null
        ? ' title="' . htmlspecialchars($titulek) . '"'
        : '')
    . '>' . htmlspecialchars($text) . '</a>';

// e-shop a údaje z něj (názvy rolí) jsou česky i na stránce v jiném jazyce, čtečka je tak přečte česky
$jazykEshopu = AktualniJazyk::jazyk()->jeVychozi()
    ? ''
    : ' lang="' . Jazyk::vychozi()->value . '"';

if (post('pridatPotvrzeniRodicu')) {
    if (!$u->zpracujPotvrzeniRodicu()) {
        chyba(htmlspecialchars(t('prihlaska.souhlas_rodicu.chybi')));
    } else {
        oznameni(htmlspecialchars(t('prihlaska.souhlas_rodicu.ulozeno')));
    }
    back();
}

if (po(GC_BEZI_DO)) {
    if ($u && $u->gcPritomen()) {
        $t->assign([
            'dekujeme'    => htmlSVlozkami(
                t('prihlaska.po_gc.dekujeme', ['zpetna_vazba' => znackaVlozky('zpetna_vazba')]),
                // relativně, aby zpětná vazba zůstala v jazyce stránky
                ['zpetna_vazba' => $odkaz('info-po-gc', t('prihlaska.po_gc.zpetna_vazba'), t('prihlaska.po_gc.zpetna_vazba_titulek'))],
            ),
            'sledujteNas' => htmlSVlozkami(
                t('prihlaska.po_gc.sledujte_nas', [
                    'facebook'  => znackaVlozky('facebook'),
                    'instagram' => znackaVlozky('instagram'),
                    'discord'   => znackaVlozky('discord'),
                ]),
                [
                    'facebook'  => $odkaz('facebook', t('prihlaska.po_gc.facebook'), t('prihlaska.po_gc.facebook_titulek')),
                    'instagram' => $odkaz('instagram', t('prihlaska.po_gc.instagram'), t('prihlaska.po_gc.instagram_titulek')),
                    'discord'   => $odkaz('discord', t('prihlaska.po_gc.discord_kde'), t('prihlaska.po_gc.discord_titulek')),
                ],
            ),
        ]);
        $t->parse('prihlaskaPoGc.ucastnilSe');
    } else {
        $t->assign([
            // rok jako řetězec, ICU by ho česky zapsalo s mezerou mezi tisíci
            'neucastnilSe' => htmlspecialchars(t('prihlaska.po_gc.neucastnil_se', ['rok' => (string) ($systemoveNastaveni->rocnik() + 1)])),
            'fotky'        => htmlSVlozkami(
                t('prihlaska.po_gc.fotky', [
                    'facebook'  => znackaVlozky('facebook'),
                    'instagram' => znackaVlozky('instagram'),
                    'discord'   => znackaVlozky('discord'),
                ]),
                [
                    'facebook'  => $odkaz('facebook', t('prihlaska.po_gc.facebook'), t('prihlaska.po_gc.facebook_titulek')),
                    'instagram' => $odkaz('instagram', t('prihlaska.po_gc.instagram'), t('prihlaska.po_gc.instagram_titulek')),
                    'discord'   => $odkaz('discord', t('prihlaska.po_gc.discord_kam'), t('prihlaska.po_gc.discord_titulek')),
                ],
            ),
        ]);
        $t->parse('prihlaskaPoGc.neucastnilSe');
    }
    $t->parse('prihlaskaPoGc');

    return;
}

if (!$u?->gcPrihlasen() && po($systemoveNastaveni->prihlasovaniUcastnikuDo())) {
    $t->assign('uzavreno', htmlspecialchars(t('prihlaska.uzavreno', ['konec' => $systemoveNastaveni->prihlasovaniUcastnikuDo()])));
    $t->parse('prihlaskaPo');

    return;
}

if ($systemoveNastaveni->gcBezi()) {
    $finance = $odkaz('finance', t('prihlaska.behem_gc.finance'));
    if ($u?->gcPritomen()) {
        $t->assign([
            'naInfopultu'       => htmlSVlozkami(
                t('prihlaska.behem_gc.na_infopultu', ['jen_na_miste' => znackaVlozky('jen_na_miste')]),
                ['jen_na_miste' => '<strong>' . htmlspecialchars(t('prihlaska.behem_gc.jen_na_miste')) . '</strong>'],
            ),
            'prehledObjednavek' => htmlSVlozkami(
                t('prihlaska.behem_gc.prehled_objednavek', ['finance' => znackaVlozky('finance')]),
                ['finance' => $finance],
            ),
        ]);
        $t->parse('prihlaskaUzavrena.proselInfopultem');
        $t->parse('prihlaskaUzavrena');

        return;
    }
    if ($u?->gcPrihlasen()) {
        $t->assign('vitame', htmlSVlozkami(
            t('prihlaska.behem_gc.vitame', ['finance' => znackaVlozky('finance')]),
            ['finance' => $finance],
        ));
        $t->parse('prihlaskaUzavrena.neproselInfopultem');
        $t->parse('prihlaskaUzavrena');

        return;
    }
}

if (!$u) {
    // Když přijde POST (účastník odeslal formulář) a přitom není přihlášen, jeho
    // session mezitím vypršela – data se neuložila. Bez téhle hlášky odejde s pocitem,
    // že je přihlášený na GameCon, ač ho odeslání jen odbylo na homepage/přihlášení.
    $sessionVyprsela = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    oznameniPresmeruj(
        htmlspecialchars($sessionVyprsela
            ? t('prihlaska.relace_vyprsela')
            : t('prihlaska.vyzaduje_prihlaseni')),
        urlWebu('prihlaseni'),
        Chyba::VAROVANI
    );
}

if (pred($systemoveNastaveni->prihlasovaniUcastnikuOd())) {
    $t->assign('spusteni', htmlspecialchars($systemoveNastaveni->rocnik() < date('Y')
        // ještě jsme nepřeklopili ročník
        ? t('prihlaska.pred.upresnime')
        : t('prihlaska.pred.zacatek', ['zacatek' => $systemoveNastaveni->prihlasovaniUcastnikuOd()])));
    $t->parse('prihlaskaPred');

    return;
}

$shop  = new Shop($u, $u, $systemoveNastaveni);
$pomoc = new Pomoc($u);

if (post('odhlasit')) {
    if (po($systemoveNastaveni->gcBeziOd())) {
        chyba(htmlspecialchars(t('prihlaska.odhlaseni_behem_gc', ['pohlavi' => $u->pohlavi()])));
    } else {
        $odhlaseno = $u->odhlasZGc('rucne-sam-sebe', $u);
        if ($odhlaseno) {
            $u->finance()->obnovUdaje();
        }
        oznameni(htmlspecialchars(t('prihlaska.odhlaseno', ['rok' => (string) ROCNIK])));
    }
}

if (post('prihlasitNeboUpravit')) {
    $prihlasovani = !$u->gcPrihlasen();
    dbBegin();
    try {
        if ($prihlasovani) {
            $u->gcPrihlas($u);
        }
        $shop->zpracujPredmety();
        $shop->zpracujUbytovani(ulozitNechceUbytovani: true);
        $shop->zpracujJidlo();
        $shop->zpracujVstupne();
        $pomoc->zpracuj();
        $u->finance()->obnovUdaje();
        dbCommit();
    } catch (Throwable $throwable) {
        dbRollback();
        throw $throwable;
    }
    if ($prihlasovani) {
        oznameni(htmlspecialchars(t('prihlaska.prihlaseno', ['rok' => (string) ROCNIK])));
    } else {
        oznameni(htmlspecialchars(t('prihlaska.aktualizovano')));
    }
}

// Informace o slevách. Jídlo nevypisovat, tabulka správně vypisuje cenu po slevě. Slevy na jídlo se poznají podle
// českého textu, slevy v jazyce stránky mají stejné pořadí (a klíče) jako české.
$slevyNaJidlo = array_intersect($u->finance()->slevyVse(), ['jídlo zdarma', 'jídlo se slevou']);
$slevy = array_diff_key($u->finance()->slevyVse(AktualniJazyk::jazyk()), $slevyNaJidlo);
if ($slevy) {
    $t->assign('slevy', htmlSVlozkami(
        t('prihlaska.predmety.slevy', ['titul' => znackaVlozky('titul'), 'slevy' => implode(', ', $slevy)]),
        // název role je česky
        ['titul' => '<span' . $jazykEshopu . '>' . htmlspecialchars(mb_strtolower($u->status())) . '</span>'],
    ));
    $t->parse('prihlaska.slevy');
}

if (!$u->maPravo(Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC)) {
    $t->parse('prihlaska.ubytovaniMinDveNoci');
}

if ($u->jeOrganizator()) {
    $t->parse('prihlaska.poznamkaKUbytovaniVNedeli');
}

if ($u->maPravoNaUbytovaniZdarma()) {
    $t->assign('ubytovaniZdarma', htmlspecialchars(t('prihlaska.ubytovani.zdarma_organizatorovi', ['pohlavi' => $u->pohlavi()])));
    $t->parse('prihlaska.ubytovaniInfoOrg');
} elseif ($u->maPravoNaPoradaniAktivit() && !$u->nemaPravoNaBonusZaVedeniAktivitAniUcastNaTechnicke()) {
    $t->assign('bonusVypravece', htmlSVlozkami(
        t('prihlaska.ubytovani.bonus_vypravece', ['odkaz' => znackaVlozky('odkaz')]),
        ['odkaz' => $odkaz('finance', t('prihlaska.ubytovani.bonus_vypravece_odkaz'))],
    ));
    $t->parse('prihlaska.ubytovaniInfoVypravec');
}

$adresarKObrazkuPredmetu = adresarKObrazkuEshopPredmetu();
if (is_dir($adresarKObrazkuPredmetu)) {
    foreach (scandir(adresarKObrazkuEshopPredmetu()) as $soubor) {
        // včetně `.` a `..` a dočasných souborů z nahrávání v administraci
        if (str_starts_with($soubor, '.')) {
            continue;
        }
        $cestaKObrazku = cestaKObrazkuEshopPredmetu($soubor);
        if (!is_file($cestaKObrazku)) {
            continue;
        }
        if (!Obrazek::jeToPodporovanyObrazek($cestaKObrazku)) {
            continue;
        }
        $chybiObrazek  = false;
        try {
            $obrazek = nahledPredmetu($cestaKObrazku);
        } catch (\RuntimeException $runtimeException) {
            $obrazek      = $cestaKObrazku;
            $chybiObrazek = true;
        }

        $chybiMiniatura = false;
        try {
            $miniatura = miniauturaNahleduPredmetu($cestaKObrazku);
        } catch (\RuntimeException $runtimeException) {
            $miniatura      = $cestaKObrazku;
            $chybiMiniatura = true;
        }
        $nazev = ZpracovaneObrazky::popisekObrazkuEshopu($soubor);

        $t->assign([
            'obrazek'   => $obrazek,
            'miniatura' => $miniatura,
            'nazev'     => $nazev,
            'display'   => ($chybiObrazek || $chybiMiniatura) && (!$u || !$u->maPravo(Pravo::ADMINISTRACE_INFOPULT))
                ? 'none'
                : 'flex',
        ]);
        $t->parse('prihlaska.nahled');
    }
}

$t->assign([
    'jazykEshopu'          => $jazykEshopu,
    'jidlo'                => $shop->jidloHtml(),
    'predmety'             => $shop->predmetyHtml(),
    'ubytovani'            => $shop->ubytovaniHtml(),
    'vstupne'              => $shop->vstupneHtml(),
    'pomoc'                => $pomoc->html(),
    'velikostiMikin'       => htmlSVlozkami(
        t('prihlaska.predmety.velikosti_mikin', ['odkaz' => znackaVlozky('odkaz')]),
        ['odkaz' => $odkaz('https://gamecon.cz/soubory/obsah/materialy/2026/velikosti_mikin.pdf', t('prihlaska.predmety.velikosti_mikin_odkaz'))],
    ),
    'zmenyPredmetuDo'      => htmlspecialchars(t('prihlaska.predmety.zmeny_do', [
        'mikiny'  => $systemoveNastaveni->prodejMikinDo(),
        'ostatni' => $systemoveNastaveni->prodejPredmetuBezTricekDo(),
    ])),
    'zmenyUbytovaniDo'     => htmlspecialchars(t('prihlaska.ubytovani.zmeny_do', ['do' => $systemoveNastaveni->prodejUbytovaniDo()])),
    'ubytovaniOdStredy'    => htmlSVlozkami(
        t('prihlaska.ubytovani.od_stredy', ['ctvrtek' => znackaVlozky('ctvrtek')]),
        ['ctvrtek' => '<strong>' . htmlspecialchars(t('prihlaska.ubytovani.ctvrtek')) . '</strong>'],
    ),
    'podrobnostiUbytovani' => htmlSVlozkami(
        t('prihlaska.ubytovani.podrobnosti', ['odkaz' => znackaVlozky('odkaz')]),
        ['odkaz' => $odkaz('prakticke-informace#ubytovani', t('prihlaska.ubytovani.prakticke_informace'))],
    ),
    'zmenyJidlaDo'         => htmlspecialchars(t('prihlaska.jidlo.zmeny_do', ['do' => $systemoveNastaveni->prodejJidlaDo()])),
    'jidelnicek'           => htmlSVlozkami(
        t('prihlaska.jidlo.jidelnicek', ['odkaz' => znackaVlozky('odkaz')]),
        ['odkaz' => $odkaz('jidelnicek', t('prihlaska.jidlo.jidelnicek_odkaz'))],
    ),
    'ulozitNeboPrihlasit'  => htmlspecialchars($u->gcPrihlasen()
        ? t('prihlaska.ulozit_zmeny')
        : t('prihlaska.prihlasit')),
]);

if ($u->gcPrihlasen()) {
    $t->assign([
        'aktivni'        => htmlspecialchars(t('prihlaska.prihlasen.aktivni', ['rok' => (string) ROCNIK])),
        'uhraditDo'      => htmlspecialchars(t('prihlaska.prihlasen.uhradit_do', ['do' => $systemoveNastaveni->nejpozdejiZaplatitDo()])),
        'prehledFinanci' => htmlSVlozkami(
            t('prihlaska.prihlasen.prehled_financi', ['odkaz' => znackaVlozky('odkaz')]),
            ['odkaz' => $odkaz('finance', t('prihlaska.prihlasen.prehled_financi_odkaz'))],
        ),
    ]);
    if ($u->vekKDatu($systemoveNastaveni->gcBeziOd()) < 15 &&
        ((!$u->potvrzeniZakonnehoZastupceOd()) ||
            $u->potvrzeniZakonnehoZastupceOd()->format('Y') != $systemoveNastaveni->rocnik())) {
        if ($u->potvrzeniZakonnehoZastupceSouborOd() && ((!$u->potvrzeniZakonnehoZastupceOd()) || $u->potvrzeniZakonnehoZastupceSouborOd() > ($u->potvrzeniZakonnehoZastupceOd()))) {
            $t->assign('uspesneNahrano', 'prihlaska_uspesneNahrano');
            $t->parse('prihlaska.prihlasen.potvrzeniZakonnyZastupce.nahrano');
        }
        // formulář je jen česky, soubor se neliší podle jazyka stránky; cestu s {rocnik} zná PouzitiObrazku::ODKAZY_Z_KODU
        $souhlasRodicu = str_replace('{rocnik}', (string) $systemoveNastaveni->rocnik(), URL_WEBU . '/soubory/obsah/materialy/{rocnik}/souhlas_rodicu_{rocnik}.pdf');
        $t->assign('souhlasRodicu', htmlSVlozkami(
            t('prihlaska.souhlas_rodicu.text', ['odkaz' => znackaVlozky('odkaz')]),
            ['odkaz' => $odkaz($souhlasRodicu, t('prihlaska.souhlas_rodicu.odkaz'))],
        ));
        $t->parse('prihlaska.prihlasen.potvrzeniZakonnyZastupce');
    }
    $t->parse('prihlaska.prihlasen');
    if (pred($systemoveNastaveni->gcBeziOd())) {
        $t->assign('potvrzeniOdhlaseni', htmlspecialchars(json_encode(t('prihlaska.odhlasit_potvrzeni'), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)));
        $t->parse('prihlaska.odhlasit');
    }
} else {
    $t->assign('neprihlasen', htmlspecialchars(t('prihlaska.neprihlasen', ['rok' => (string) ROCNIK])));
    $t->parse('prihlaska.neprihlasen');
}

<?php declare(strict_types=1);

namespace Gamecon\Aktivita\OnlinePrezence;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\AktivitaPrezence;
use Gamecon\Aktivita\RazitkoPosledniZmenyPrihlaseni;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\ZmenaPrihlaseni;
use Gamecon\Aktivita\ZmenaStavuAktivity;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Symfony\Component\Filesystem\Filesystem;

class OnlinePrezenceAjax
{
    public const AJAX = 'ajax';
    public const POSLEDNI_ZMENY = 'posledni-zmeny';

    public const POSLEDNI_LOGY_AKTIVIT_AJAX_KLIC = 'posledni_logy_aktivit_ajax_klic';
    public const POSLEDNI_LOGY_UCASTNIKU_AJAX_KLIC = 'posledni_logy_ucastniku_ajax_klic';
    public const ID_AKTIVITY = 'id_aktivity';
    public const ID_UZIVATELE = 'id_uzivatele';
    public const ID_LOGU = 'id_logu';
    public const CAS_ZMENY = 'cas_zmeny';
    public const STAV_AKTIVITY = 'stav_aktivity';
    public const STAV_PRIHLASENI = 'stav_prihlaseni';
    public const HTML_UCASTNIKA = 'html_ucastnika';
    public const ZMENY_STAVU_AKTIVIT = 'zmeny_stavu_aktivit';
    public const ZMENY_PRIHLASENI = 'zmeny_prihlaseni';
    public const RAZITKO_POSLEDNI_ZMENY = 'razitko_posledni_zmeny';
    public const UCASTNICI_PRIDATELNI_DO_TIMESTAMP = 'ucastnici_pridatelni_do_timestamp';
    public const UCASTNICI_ODEBRATELNI_DO_TIMESTAMP = 'ucastnici_odebratelni_do_timestamp';
    public const ERRORS = 'errors';
    public const PRIHLASEN = 'prihlasen';
    public const CAS_POSLEDNI_ZMENY_PRIHLASENI = 'cas_posledni_zmeny_prihlaseni';
    public const AKTIVITA = 'aktivita';
    public const DORAZILI = 'dorazili';

    public static function dejUrlAkcePosledniZmeny(): string {
        return getCurrentUrlWithQuery([self::AJAX => 1, 'akce' => self::POSLEDNI_ZMENY]);
    }

    /**
     * @var OnlinePrezenceHtml
     */
    private $onlinePrezenceHtml;
    /**
     * @var Filesystem
     */
    private $filesystem;
    /**
     * @var SystemoveNastaveni
     */
    private $systemoveNastaveni;
    /**
     * @var bool
     */
    private $testujeme;

    public function __construct(
        OnlinePrezenceHtml $onlinePrezenceHtml,
        Filesystem         $filesystem,
        SystemoveNastaveni $systemoveNastaveni,
        bool               $testujeme
    ) {
        $this->onlinePrezenceHtml = $onlinePrezenceHtml;
        $this->filesystem = $filesystem;
        $this->systemoveNastaveni = $systemoveNastaveni;
        $this->testujeme = $testujeme;
    }

    public function odbavAjax(\Uzivatel $vypravec) {
        if (!post(self::AJAX) && !get(self::AJAX)) {
            return false;
        }

        if (get('akce') === self::POSLEDNI_ZMENY) {
            $this->ajaxDejPosledniZmeny(
                (array)post(self::POSLEDNI_LOGY_AKTIVIT_AJAX_KLIC),
                (array)post(self::POSLEDNI_LOGY_UCASTNIKU_AJAX_KLIC),
                $vypravec
            );
            return true;
        }

        if (post('akce') === 'uzavrit') {
            $this->ajaxUzavritAktivitu((int)post('id'), $vypravec);
            return true;
        }

        if (post('akce') === 'zmenitPritomnostUcastnika') {
            $zdaDorazil = post('dorazil');
            if ($zdaDorazil !== null) {
                $zdaDorazil = (bool)$zdaDorazil;
            }
            $this->ajaxZmenitPritomnostUcastnika(
                $vypravec,
                (int)post('idUcastnika'),
                (int)post('idAktivity'),
                $zdaDorazil,
            );
            return true;
        }

        if (post('prezenceAktivity')) {
            $this->ajaxUlozPrezenci((int)post('prezenceAktivity'), array_keys(post('zdaDorazil') ?: []));
            return true;
        }

        if (get('omnibox')) {
            $this->ajaxOmnibox(
                $vypravec,
                (int)get('idAktivity'),
                (string)get('term') ?: '',
                (array)get('dataVOdpovedi') ?: [],
                get('labelSlozenZ')
            );
            return true;
        }

        $this->echoErrorJson('Neznámý AJAX požadavek');
        return true;
    }

    /**
     * @param string[] $posledniZnameStavyAktivit
     * @param string[][][] $idsPoslednichZnamychLoguUcastniku
     * @param \Uzivatel $vypravec
     * @return void
     */
    private function ajaxDejPosledniZmeny(
        array     $posledniZnameStavyAktivit,
        array     $idsPoslednichZnamychLoguUcastniku,
        \Uzivatel $vypravec
    ) {
        $zmenyStavuAktivitProJson = [];
        $nejnovejsiZmenyStavuAktivit = Aktivita::dejPosledniZmenyStavuAktivit($posledniZnameStavyAktivit);
        foreach ($nejnovejsiZmenyStavuAktivit->zmenyStavuAktivit() as $zmenaStavuAktivity) {
            $aktivita = Aktivita::zId($zmenaStavuAktivity->idAktivity(), true);
            $zmenyStavuAktivitProJson[] = [
                self::ID_AKTIVITY => $zmenaStavuAktivity->idAktivity(),
                self::ID_LOGU => $zmenaStavuAktivity->idLogu(),
                self::CAS_ZMENY => $zmenaStavuAktivity->casZmenyProJs(),
                self::STAV_AKTIVITY => $zmenaStavuAktivity->stavAktivityProJs(),
                self::UCASTNICI_PRIDATELNI_DO_TIMESTAMP => $this->ucastniciPridatelniDoTimestamp($vypravec, $aktivita),
                self::UCASTNICI_ODEBRATELNI_DO_TIMESTAMP => $this->ucastniciOdebratelniDoTimestamp($vypravec, $aktivita),
            ];
        }

        $zmenyPrihlaseniProJson = [];
        $nejnovejsiZmenyPrihlaseni = AktivitaPrezence::dejPosledniZmenyPrezence($idsPoslednichZnamychLoguUcastniku);
        foreach ($nejnovejsiZmenyPrihlaseni->zmenyPrihlaseni() as $zmenaPrihlaseni) {
            $aktivita = Aktivita::zId($zmenaPrihlaseni->idAktivity(), true);
            if (!$aktivita) { // Stává se na betě, když se natvrdo odebírají aktivity
                if (!defined('TESTING') || !TESTING) {
                    trigger_error(
                        "Nelze načíst aktivitu s ID {$zmenaPrihlaseni->idAktivity()}: " . var_export($zmenaPrihlaseni, true),
                        E_USER_WARNING
                    );
                }
                continue;
            }
            $zmenyPrihlaseniProJson[] = [
                self::ID_AKTIVITY => $zmenaPrihlaseni->idAktivity(),
                self::ID_UZIVATELE => $zmenaPrihlaseni->idUzivatele(),
                self::ID_LOGU => $zmenaPrihlaseni->idLogu(),
                self::CAS_ZMENY => $zmenaPrihlaseni->casZmenyProJs(),
                self::STAV_PRIHLASENI => $zmenaPrihlaseni->typPrezenceProJs(),
                self::HTML_UCASTNIKA => $this->onlinePrezenceHtml->sestavHmlUcastnikaAktivity(
                    \Uzivatel::zId($zmenaPrihlaseni->idUzivatele()),
                    $aktivita,
                    $vypravec,
                    $zmenaPrihlaseni->stavPrihlaseni()
                ),
            ];
        }

        $this->echoJson([
            self::ZMENY_STAVU_AKTIVIT => $zmenyStavuAktivitProJson,
            self::ZMENY_PRIHLASENI => $zmenyPrihlaseniProJson,
            self::RAZITKO_POSLEDNI_ZMENY => $this->dejPotvrzeneRazitkoPosledniZmeny(
                $vypravec,
                $nejnovejsiZmenyStavuAktivit->posledniZmenaStavuAktivity(),
                $nejnovejsiZmenyPrihlaseni->posledniZmenaPrihlaseni(),
                true // tyhle změny jsou opravdu ty poslední, takže jestli esxistuje nějaké staré, jiné razítko, tak ho chceme přepsat
            ),
        ]);
    }

    private function ajaxUzavritAktivitu(int $idAktivity, \Uzivatel $vypravec) {
        $aktivita = Aktivita::zId($idAktivity, true);
        if (!$aktivita) {
            $this->echoErrorJson('Chybné ID aktivity ' . $idAktivity);
            return;
        }
        $aktivita->zamkni();
        $aktivita->dejPrezenci()->uloz($aktivita->dorazili());
        $aktivita->uzavri();
        $aktivita->refresh();

        $this->echoJson(
            [
                self::UCASTNICI_PRIDATELNI_DO_TIMESTAMP => $this->ucastniciPridatelniDoTimestamp($vypravec, $aktivita),
                self::UCASTNICI_ODEBRATELNI_DO_TIMESTAMP => $this->ucastniciOdebratelniDoTimestamp($vypravec, $aktivita),
            ]
        );
    }

    private function ucastniciPridatelniDoTimestamp(\Uzivatel $prihlasujici, Aktivita $aktivita): int {
        return $aktivita->ucastniciPridatelniDo($prihlasujici, $this->systemoveNastaveni)->getTimestamp();
    }

    private function ucastniciOdebratelniDoTimestamp(\Uzivatel $odhlasujici, Aktivita $aktivita): int {
        return $aktivita->ucastniciOdebratelniDo($odhlasujici, $this->systemoveNastaveni)->getTimestamp();
    }

    private function echoErrorJson(string $error): void {
        header("HTTP/1.1 400 Bad Request");
        $this->echoJson([self::ERRORS => [$error]]);
    }

    private function echoJson(array $data): void {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_THROW_ON_ERROR);
    }

    /**
     * @param \Uzivatel $vypravec
     * @param int $idUcastnika
     * @param int $idAktivity
     * @param bool|null $dorazil
     * @return void
     * @throws \JsonException
     */
    private function ajaxZmenitPritomnostUcastnika(
        \Uzivatel $vypravec,
        int       $idUcastnika,
        int       $idAktivity,
        ?bool     $dorazil
    ) {
        $ucastnik = \Uzivatel::zId($idUcastnika);
        if (!$ucastnik) {
            $this->echoErrorJson('Chybné ID účastníka');
            return;
        }
        $aktivita = Aktivita::zId($idAktivity, true);
        if (!$aktivita) {
            $this->echoErrorJson('Chybné ID aktivity');
            return;
        }

        if ($dorazil === null) {
            $this->echoErrorJson('Chybějící příznak zda dorazil');
            return;
        }
        $vypravec = $this->dejVypravecePodleTestu($aktivita, $vypravec);

        if ($dorazil) {
            try {
                $ignorovat = Aktivita::IGNOROVAT_LIMIT | Aktivita::IGNOROVAT_PRIHLASENI_NA_SOUROZENCE;
                $aktivita->zkontrolujZdaSeMuzePrihlasit(
                    $ucastnik,
                    $vypravec,
                    $this->testujeme
                        ? $ignorovat | Aktivita::DOPREDNE | Aktivita::ZPETNE | Aktivita::STAV
                        : $ignorovat,
                    true,
                    true,
                );
            } catch (\Chyba $chyba) {
                $this->echoErrorJson($chyba->getMessage());
                return;
            }
            $aktivita->dejPrezenci()->ulozZeDorazil($ucastnik);
        } else {
            try {
                $aktivita->zkontrolujZdaSeMuzeOdhlasit($ucastnik, $vypravec);
                $aktivita->dejPrezenci()->zrusZeDorazil($ucastnik);
            } catch (\Chyba $chyba) {
                $this->echoErrorJson($chyba->getMessage());
                return;
            }
        }

        /** Abychom mměli nová data pro @see Aktivita::dorazilJakoCokoliv */
        $aktivita->refresh();

        $posledniZmenaPrihlaseni = $aktivita->dejPrezenci()->posledniZmenaPrihlaseni($ucastnik);

        $this->echoJson([
            self::PRIHLASEN => $aktivita->dorazilJakoCokoliv($ucastnik),
            self::CAS_POSLEDNI_ZMENY_PRIHLASENI => $posledniZmenaPrihlaseni->casZmenyProJs(),
            self::STAV_PRIHLASENI => $posledniZmenaPrihlaseni->typPrezenceProJs(),
            self::ID_LOGU => $posledniZmenaPrihlaseni->idLogu(),
            self::RAZITKO_POSLEDNI_ZMENY => $this->dejPotvrzeneRazitkoPosledniZmeny(
                $vypravec,
                Aktivita::posledniZmenaStavuAktivit([$aktivita]), // jenom jedna aktivita v online prezenci asi nebude, ale pro razítko to stačí (než se někdo přihlásí o změny a pak se přepočte se všemi aktivitami)
                $posledniZmenaPrihlaseni,
                false // kdyby se mezi uložením změn a zjišťováním razítka objevila jiná změna, tak nechceme razítko přepisovat
            ),
        ]);
    }

    private function dejPotvrzeneRazitkoPosledniZmeny(
        \Uzivatel           $vypravec,
        ?ZmenaStavuAktivity $posledniZmenaStavuAktivity,
        ?ZmenaPrihlaseni    $posledniZmenaPrihlaseni,
        bool                $prepsatStare
    ): string {
        return (new RazitkoPosledniZmenyPrihlaseni(
            $vypravec,
            $posledniZmenaStavuAktivity,
            $posledniZmenaPrihlaseni,
            $this->filesystem,
            self::RAZITKO_POSLEDNI_ZMENY
        ))->dejPotvrzeneRazitkoPosledniZmeny($prepsatStare);
    }

    public function dejVypravecePodleTestu(Aktivita $aktivita, \Uzivatel $vypravec): \Uzivatel {
        if (!$this->testujeme) {
            return $vypravec;
        }
        $organizatori = $aktivita->organizatori();
        return count($organizatori) > 0
            ? reset($organizatori) // první organizátor co padne pod ruku
            : $vypravec;
    }

    private function ajaxUlozPrezenci(int $idAktivity, array $idDorazivsich) {
        $aktivita = Aktivita::zId($idAktivity, true);
        if (!$aktivita) {
            $this->echoErrorJson('Chybné ID aktivity' . $idAktivity);
            return;
        }
        $dorazili = \Uzivatel::zIds($idDorazivsich);
        $aktivita->dejPrezenci()->uloz($dorazili);

        $this->echoJson([self::AKTIVITA => $aktivita->rawDb(), self::DORAZILI => $dorazili]);
    }

    private function ajaxOmnibox(
        \Uzivatel $vypravec,
        int       $idAktivity,
        string    $term,
        array     $dataVOdpovedi,
        ?array    $labelSlozenZ
    ) {
        $aktivita = Aktivita::zId($idAktivity, true);
        if (!$aktivita) {
            $this->echoErrorJson('Chybné ID aktivity ' . $idAktivity);
            return;
        }
        $omniboxData = omnibox(
            $term,
            true,
            $dataVOdpovedi,
            $labelSlozenZ,
            array_map(
                static function (\Uzivatel $prihlaseny) {
                    return (int)$prihlaseny->id();
                }, $aktivita->prihlaseni()
            ),
            true,
            1 // znaky ovladame v JS pres minLength, v PHP uz to omezovat nechceme
        );
        foreach ($omniboxData as &$prihlasenyUzivatelOmnibox) {
            $prihlasenyUzivatel = \Uzivatel::zId($prihlasenyUzivatelOmnibox['value']);
            if (!$prihlasenyUzivatel) {
                continue;
            }
            $ucastnikHtml = $this->onlinePrezenceHtml->sestavHmlUcastnikaAktivity(
                $prihlasenyUzivatel,
                $aktivita,
                $vypravec,
                /* jenom zobrazeni - skutečné uložení, že dorazil, řešíme už po vybrání uživatele z omniboxu,
                   což je ještě před vykreslením účastníka */
                StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK
            );
            $prihlasenyUzivatelOmnibox['html'] = $ucastnikHtml;
        }
        unset($prihlasenyUzivatelOmnibox);

        $this->echoJson($omniboxData);
    }
}

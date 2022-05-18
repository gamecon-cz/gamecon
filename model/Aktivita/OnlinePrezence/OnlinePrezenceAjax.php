<?php declare(strict_types=1);

namespace Gamecon\Aktivita\OnlinePrezence;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\AktivitaPrezence;
use Gamecon\Aktivita\PosledniZmenyStavuPrihlaseni;
use Gamecon\Aktivita\RazitkoPosledniZmenyPrihlaseni;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\ZmenaStavuPrihlaseni;
use Symfony\Component\Filesystem\Filesystem;
use function PHPUnit\Framework\returnArgument;

class OnlinePrezenceAjax
{
    public const AJAX = 'ajax';
    public const POSLEDNI_ZMENY = 'posledni-zmeny';

    public const IDS_POSLEDNICH_LOGU_UCASTNIKU_AJAX_KLIC = 'ids_poslednich_logu_ucastniku_ajax_klic';
    public const IDS_AKTIVIT = 'ids_aktivit';
    public const ID_AKTIVITY = 'id_aktivity';
    public const ID_UZIVATELE = 'id_uzivatele';
    public const ID_LOGU = 'id_logu';
    public const CAS_ZMENY = 'cas_zmeny';
    public const STAV_PRIHLASENI = 'stav_prihlaseni';
    public const HTML_UCASTNIKA = 'html_ucastnika';
    public const ZMENY = 'zmeny';
    public const RAZITKO_POSLEDNI_ZMENY = 'razitko_posledni_zmeny';
    public const ZAMCENA = 'zamcena';
    public const UZAVRENA = 'uzavrena';
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
     * @var bool
     */
    private $testujeme;

    public function __construct(
        OnlinePrezenceHtml $onlinePrezenceHtml,
        Filesystem         $filesystem,
        bool               $testujeme
    ) {
        $this->onlinePrezenceHtml = $onlinePrezenceHtml;
        $this->filesystem = $filesystem;
        $this->testujeme = $testujeme;
    }

    public function odbavAjax(\Uzivatel $vypravec) {
        if (!post(self::AJAX) && !get(self::AJAX)) {
            return false;
        }

        if (get('akce') === self::POSLEDNI_ZMENY) {
            $this->ajaxDejPosledniZmeny(
                (array)post(self::IDS_POSLEDNICH_LOGU_UCASTNIKU_AJAX_KLIC),
                array_map('intval', (array)post(self::IDS_AKTIVIT)),
                $vypravec
            );
            return true;
        }

        if (post('akce') === 'uzavrit') {
            $this->ajaxUzavritAktivitu(
                (int)post('id'),
                ['maPravoNaZmenuHistorieAktivit' => $vypravec->maPravoNaZmenuHistorieAktivit()]
            );
            return true;
        }

        if (post('akce') === 'zmenitPritomnostUcastnika') {
            $zdaDorazil = post('dorazil');
            if ($zdaDorazil !== null) {
                $zdaDorazil = (bool)$zdaDorazil;
            }
            $this->ajaxZmenitPritomnostUcastnika(
                (int)post('idUzivatele'),
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
     * @param int[] $idsPoslednichLoguUcastniku
     * @param int[] $idsAktivit
     * @param \Uzivatel $vypravec
     * @return void
     */
    private function ajaxDejPosledniZmeny(array $idsPoslednichLoguUcastniku, array $idsAktivit, \Uzivatel $vypravec) {
        $zmenyProJson = [];
        $nejnovejsiZmenyStavuPrihlaseni = AktivitaPrezence::dejPosledniZmeny(
            array_map('intval', $idsPoslednichLoguUcastniku),
            $idsAktivit
        );
        foreach ($nejnovejsiZmenyStavuPrihlaseni->zmenyStavuPrihlaseni() as $zmenaStavuPrihlaseni) {
            $aktivita = Aktivita::zId($zmenaStavuPrihlaseni->idAktivity());
            $zmenyProJson[] = [
                self::ID_AKTIVITY => $zmenaStavuPrihlaseni->idAktivity(),
                self::ID_UZIVATELE => $zmenaStavuPrihlaseni->idUzivatele(),
                self::ID_LOGU => $zmenaStavuPrihlaseni->idLogu(),
                self::CAS_ZMENY => $zmenaStavuPrihlaseni->casZmenyProJs(),
                self::STAV_PRIHLASENI => $zmenaStavuPrihlaseni->typPrezenceProJs(),
                self::HTML_UCASTNIKA => $this->onlinePrezenceHtml->sestavHmlUcastnikaAktivity(
                    \Uzivatel::zId($zmenaStavuPrihlaseni->idUzivatele()),
                    $aktivita,
                    $zmenaStavuPrihlaseni->stavPrihlaseni(),
                    false
                ),
            ];
        }
        $razitkoPosledniZmeny = new RazitkoPosledniZmenyPrihlaseni(
            $vypravec,
            $nejnovejsiZmenyStavuPrihlaseni->posledniZmenaStavuPrihlaseni(),
            $this->filesystem,
            self::RAZITKO_POSLEDNI_ZMENY
        );
        $this->echoJson([
            self::ZMENY => $zmenyProJson,
            self::RAZITKO_POSLEDNI_ZMENY => $razitkoPosledniZmeny->dejPotvrzeneRazitkoPosledniZmeny(),
        ]);
    }

    private function ajaxUzavritAktivitu(int $idAktivity, array $dataPriUspechu) {
        $aktivita = Aktivita::zId($idAktivity);
        if (!$aktivita) {
            $this->echoErrorJson('Chybné ID aktivity ' . $idAktivity);
            return;
        }
        $aktivita->dejPrezenci()->uloz($aktivita->dorazili());
        $aktivita->zamci();
        $aktivita->uzavri();
        $aktivita->refresh();

        $this->echoJson(
            array_merge(
                [
                    self::ZAMCENA => $aktivita->zamcena(),
                    self::UZAVRENA => $aktivita->uzavrena(),
                ],
                $dataPriUspechu
            )
        );
    }

    private function echoErrorJson(string $error): void {
        header("HTTP/1.1 400 Bad Request");
        $this->echoJson([self::ERRORS => [$error]]);
    }

    private function echoJson(array $data): void {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_THROW_ON_ERROR);
    }

    private function ajaxZmenitPritomnostUcastnika(int $idUzivatele, int $idAktivity, ?bool $dorazil) {
        $ucastnik = \Uzivatel::zId($idUzivatele);
        if (!$ucastnik) {
            $this->echoErrorJson('Chybné ID účastníka');
            return;
        }
        $aktivita = Aktivita::zId($idAktivity);
        if (!$aktivita) {
            $this->echoErrorJson('Chybné ID aktivity');
            return;
        }

        if ($dorazil === null) {
            $this->echoErrorJson('Chybějící příznak zda dorazil');
            return;
        }

        try {
            $aktivita->zkontrolujZdaSeMuzePrihlasit(
                $ucastnik,
                $this->testujeme
                    ? Aktivita::DOPREDNE | Aktivita::ZPETNE | Aktivita::STAV
                    : 0
            );
        } catch (\Chyba $chyba) {
            $this->echoErrorJson($chyba->getMessage());
            return;
        }

        if ($dorazil) {
            $aktivita->dejPrezenci()->ulozZeDorazil($ucastnik);
        } else {
            $aktivita->dejPrezenci()->zrusZeDorazil($ucastnik);
        }

        /** Abychom mměli nová data pro @see Aktivita::dorazilJakoCokoliv */
        $aktivita->refresh();

        $posledniZmenaStavuPrihlaseni = $aktivita->dejPrezenci()->posledniZmenaStavuPrihlaseni($ucastnik);

        $this->echoJson([
            self::PRIHLASEN => $aktivita->dorazilJakoCokoliv($ucastnik),
            self::CAS_POSLEDNI_ZMENY_PRIHLASENI => $posledniZmenaStavuPrihlaseni->casZmenyProJs(),
            self::STAV_PRIHLASENI => $posledniZmenaStavuPrihlaseni->typPrezenceProJs(),
            self::ID_LOGU => $posledniZmenaStavuPrihlaseni->idLogu(),
        ]);
    }

    private function ajaxUlozPrezenci(int $idAktivity, array $idDorazivsich) {
        $aktivita = Aktivita::zId($idAktivity);
        if (!$aktivita) {
            $this->echoErrorJson('Chybné ID aktivity' . $idAktivity);
            return;
        }
        $dorazili = \Uzivatel::zIds($idDorazivsich);
        $aktivita->dejPrezenci()->uloz($dorazili);

        $this->echoJson([self::AKTIVITA => $aktivita->rawDb(), self::DORAZILI => $dorazili]);
    }

    private function ajaxOmnibox(
        int    $idAktivity,
        string $term,
        array  $dataVOdpovedi,
        ?array $labelSlozenZ
    ) {
        $aktivita = Aktivita::zId($idAktivity);
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
                /* jenom zobrazeni - skutečné uložení, že dorazil, řešíme už po vybrání uživatele z omniboxu,
                   což je ještě před vykreslením účastníka */
                StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK,
                false
            );
            $prihlasenyUzivatelOmnibox['html'] = $ucastnikHtml;
        }
        unset($prihlasenyUzivatelOmnibox);

        $this->echoJson($omniboxData);
    }
}

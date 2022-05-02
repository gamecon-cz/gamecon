<?php declare(strict_types=1);

namespace Gamecon\Aktivita\OnlinePrezence;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\AktivitaPrezence;
use Gamecon\Aktivita\PosledniZmenyStavuPrihlaseni;
use Gamecon\Aktivita\RazitkoPosledniZmenyPrihlaseni;
use Gamecon\Aktivita\ZmenaStavuPrihlaseni;
use Symfony\Component\Filesystem\Filesystem;

class OnlinePrezenceAjax
{
    public const AJAX = 'ajax';
    public const POSLEDNI_ZMENY = 'posledni-zmeny';

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

    public function __construct(
        OnlinePrezenceHtml $onlinePrezenceHtml,
        Filesystem         $filesystem
    ) {
        $this->onlinePrezenceHtml = $onlinePrezenceHtml;
        $this->filesystem = $filesystem;
    }

    public function odbavAjax(\Uzivatel $vypravec) {
        if (!post(self::AJAX) && !get(self::AJAX)) {
            return false;
        }

        if (get('akce') === self::POSLEDNI_ZMENY) {
            $this->ajaxDejPosledniZmeny(post('zname_zmeny_prihlaseni'), $vypravec);
            return true;
        }

        if (post('akce') === 'uzavrit') {
            $this->ajaxUzavritAktivitu(
                (int)post('id'),
                ['maPravoNaZmenuHistorieAktivit' => $vypravec->maPravoNaZmenuHistorieAktivit()]
            );
            return true;
        }

        if (post('akce') === 'zmenitUcastnika') {
            $zdaDorazil = post('dorazil');
            if ($zdaDorazil !== null) {
                $zdaDorazil = (bool)$zdaDorazil;
            }
            $this->ajaxZmenitUcastnikaAktivity(
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
     * @param scalar[][]|scalar[][][] $puvodniPosledniZnameZmenyPrihlaseni
     * @param \Uzivatel $vypravec
     * @return void
     */
    private function ajaxDejPosledniZmeny(array $puvodniPosledniZnameZmenyPrihlaseni, \Uzivatel $vypravec) {
        $zmenyProJson = [];
        $aktivity = [];
        foreach ($puvodniPosledniZnameZmenyPrihlaseni as $puvodniPosledniZnameZmenyPrihlaseniNaAktivitu) {
            $ucastniciIds = [];
            $idAktivity = (int)$puvodniPosledniZnameZmenyPrihlaseniNaAktivitu['id_aktivity'];
            $aktivita = Aktivita::zId($idAktivity);
            $aktivity[] = $aktivita;
            /** struktura dat viz admin/files/online-prezence-posledni-zname-zmeny-prihlaseni.js */
            $puvodniZnameZmenyStavuPrihlaseni = new PosledniZmenyStavuPrihlaseni((int)$puvodniPosledniZnameZmenyPrihlaseniNaAktivitu['id_aktivity']);
            $zmenyStavuPrihlaseniPole = [];
            foreach ($puvodniPosledniZnameZmenyPrihlaseniNaAktivitu['ucastnici'] ?? [] as $puvodniZnamaZmenaPrihlaseni) {
                $posledniCasZmenyStavuPrihlaseni = new \DateTimeImmutable($puvodniZnamaZmenaPrihlaseni['cas_posledni_zmeny_prihlaseni']);
                $zmenaStavuPrihlaseni = $this->vytvorZmenuStavuPrihlaseni($puvodniZnamaZmenaPrihlaseni, $idAktivity);
                $ucastniciIds[] = $zmenaStavuPrihlaseni->idUzivatele();
                $zmenyStavuPrihlaseniPole[$posledniCasZmenyStavuPrihlaseni->getTimestamp()][] = $zmenaStavuPrihlaseni;
            }
            ksort($zmenyStavuPrihlaseniPole, SORT_NUMERIC);
            // chceme hledat jen od nejnovějších změn stavu přihlášení dále
            foreach (end($zmenyStavuPrihlaseniPole) ?: [] as $posledniZmenaStavuPrihlaseni) {
                $puvodniZnameZmenyStavuPrihlaseni->addPosledniZmenaStavuPrihlaseni($posledniZmenaStavuPrihlaseni);
            }
            $nejnovejsiZmenyStavuPrihlaseni = AktivitaPrezence::dejPosledniZmeny($puvodniZnameZmenyStavuPrihlaseni);
            foreach ($nejnovejsiZmenyStavuPrihlaseni->zmenyStavuPrihlaseni() as $zmenaStavuPrihlaseni) {
                $ucastnikJesteNebylZobrazen = !in_array($zmenaStavuPrihlaseni->idUzivatele(), $ucastniciIds, false);
                if ($ucastnikJesteNebylZobrazen && !$zmenaStavuPrihlaseni->dorazilNejak()) {
                    // nezajímá nás změna, že někdo nedorazil, když ho frontend dosud ani nezobrazoval
                    continue;
                }
                $zmenyProJson[] = [
                    'id_aktivity' => $nejnovejsiZmenyStavuPrihlaseni->getIdAktivity(),
                    'id_uzivatele' => $zmenaStavuPrihlaseni->idUzivatele(),
                    'cas_zmeny' => $zmenaStavuPrihlaseni->casZmenyProJs(),
                    'stav_prihlaseni' => $zmenaStavuPrihlaseni->stavPrihlaseniProJs(),
                    'html_ucastnika' => $ucastnikJesteNebylZobrazen
                        ? $this->onlinePrezenceHtml->sestavHmlUcastnikaAktivity(
                            \Uzivatel::zId($zmenaStavuPrihlaseni->idUzivatele()),
                            $aktivita,
                            $zmenaStavuPrihlaseni->dorazilNejak(),
                            false
                        )
                        : '', // pokud JS zná ID učastníka této aktivity, tak už má jeho HTML a nepotřebuje ho
                ];
            }
        }
        $razitkoPosledniZmeny = new RazitkoPosledniZmenyPrihlaseni($vypravec, $aktivity, $this->filesystem);
        $this->echoJson([
            'zmeny' => $zmenyProJson,
            /**
             * konstanta, abychom všude používali stejné klíče pro JS
             * viz online-prezence-posledni-zname-zmeny-prihlaseni.js nahratZmenyPrihlaseni()
             */
            RazitkoPosledniZmenyPrihlaseni::RAZITKO_POSLEDNI_ZMENY => $razitkoPosledniZmeny->dejPotvrzeneRazitkoPosledniZmeny(),
        ]);
    }

    private function vytvorZmenuStavuPrihlaseni(array $posledniZnamaZmenaPrihlaseni, int $idAktivity): ZmenaStavuPrihlaseni {
        $this->ohlidejData(['id_uzivatele', 'cas_posledni_zmeny_prihlaseni', 'stav_prihlaseni'], $posledniZnamaZmenaPrihlaseni);

        $posledniCasZmenyStavuPrihlaseni = new \DateTimeImmutable($posledniZnamaZmenaPrihlaseni['cas_posledni_zmeny_prihlaseni']);

        return ZmenaStavuPrihlaseni::vytvorZDatJavscriptu(
            (int)$posledniZnamaZmenaPrihlaseni['id_uzivatele'],
            $idAktivity,
            $posledniCasZmenyStavuPrihlaseni,
            $posledniZnamaZmenaPrihlaseni['stav_prihlaseni'],
        );
    }

    private function ohlidejData(array $kliceVyzadovanychHodnot, array $dataKeKontrole) {
        foreach ($kliceVyzadovanychHodnot as $klic) {
            if (empty($dataKeKontrole[$klic])) {
                throw new \RuntimeException("Chybí '$klic' v datech " . var_export($dataKeKontrole, true));
            }
        }
    }

    private function ajaxUzavritAktivitu(int $idAktivity, array $dataPriUspechu) {
        $aktivita = Aktivita::zId($idAktivity);
        if (!$aktivita) {
            $this->echoErrorJson('Chybné ID aktivity ' . $idAktivity);
            return;
        }
        $aktivita->dejPrezenci()->uloz($aktivita->prihlaseni());
        $aktivita->zamci();
        $aktivita->uzavri();
        $aktivita->refresh();

        $this->echoJson(
            array_merge(
                [
                    'zamcena' => $aktivita->zamcena(),
                    'uzavrena' => $aktivita->uzavrena(),
                ],
                $dataPriUspechu
            )
        );
    }

    private function echoErrorJson(string $error): void {
        header("HTTP/1.1 400 Bad Request");
        $this->echoJson(['errors' => [$error]]);
    }

    private function echoJson(array $data): void {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_THROW_ON_ERROR);
    }

    private function ajaxZmenitUcastnikaAktivity(int $idUzivatele, int $idAktivity, ?bool $dorazil) {
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

        if ($dorazil) {
            $aktivita->dejPrezenci()->ulozZeDorazil($ucastnik);
        } else {
            $aktivita->dejPrezenci()->zrusZeDorazil($ucastnik);
        }

        /** Abychom mměli nová data pro @see Aktivita::dorazilJakoCokoliv */
        $aktivita->refresh();

        $posledniZmenaStavuPrihlaseni = $aktivita->dejPrezenci()->dejPosledniZmenaStavuPrihlaseni($ucastnik);

        $this->echoJson([
            'prihlasen' => $aktivita->dorazilJakoCokoliv($ucastnik),
            'cas_posledni_zmeny_prihlaseni' => $posledniZmenaStavuPrihlaseni->casZmenyProJs(),
            'stav_prihlaseni' => $posledniZmenaStavuPrihlaseni->stavPrihlaseniProJs(),
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

        $this->echoJson(['aktivita' => $aktivita->rawDb(), 'doazili' => $dorazili]);
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
                true /* jenom zobrazeni - skutečné uložení, že dorazil, řešíme už po vybrání uživatele z omniboxu, což je ještě před vykreslením účastníka */,
                false
            );
            $prihlasenyUzivatelOmnibox['html'] = $ucastnikHtml;
        }
        unset($prihlasenyUzivatelOmnibox);

        $this->echoJson($omniboxData);
    }
}

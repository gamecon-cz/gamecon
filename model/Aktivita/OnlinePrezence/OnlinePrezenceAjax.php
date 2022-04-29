<?php declare(strict_types=1);

namespace Gamecon\Aktivita\OnlinePrezence;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\AktivitaPrezence;
use Gamecon\Aktivita\PosledniZmenyStavuPrihlaseni;
use Gamecon\Aktivita\ZmenaStavuPrihlaseni;

class OnlinePrezenceAjax
{
    public const POSLEDNI_ZMENY = 'posledni-zmeny';

    public static function urlPosledniZmenyPrihlaseni(): string {
        return getCurrentUrlWithQuery(['ajax' => 1, 'akce' => self::POSLEDNI_ZMENY]);
    }

    /**
     * @var OnlinePrezenceHtml
     */
    private $onlinePrezenceHtml;

    public function __construct(OnlinePrezenceHtml $onlinePrezenceHtml) {
        $this->onlinePrezenceHtml = $onlinePrezenceHtml;
    }

    public function odbavAjax(\Uzivatel $editujici) {
        if (!post('ajax') && !get('ajax')) {
            return false;
        }

        if (get('akce') === self::POSLEDNI_ZMENY) {
            $this->ajaxDejPosledniPlatneZmeny(post('zname_zmeny_prihlaseni'));
            return true;
        }

        if (post('akce') === 'uzavrit') {
            $this->ajaxUzavritAktivitu(
                (int)post('id'),
                ['maPravoNaZmenuHistorieAktivit' => $editujici->maPravoNaZmenuHistorieAktivit()]
            );
            return true;
        }

        if (post('akce') === 'zmenitUcastnika') {
            $dorazil = post('dorazil');
            if ($dorazil !== null) {
                $dorazil = (bool)$dorazil;
            }
            $this->ajaxZmenitUcastnikaAktivity((int)post('idUzivatele'), (int)post('idAktivity'), $dorazil);
            return true;
        }

        if (post('prezenceAktivity')) {
            $this->ajaxUlozPrezenci((int)post('prezenceAktivity'), array_keys(post('dorazil') ?: []));
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
     * @param scalar[][]|scalar[][][] $posledniZnameZmenyPrihlaseni
     * @return void
     */
    private function ajaxDejPosledniPlatneZmeny(array $posledniZnameZmenyPrihlaseni) {
        $zmenyProJson = [];
        foreach ($posledniZnameZmenyPrihlaseni as $posledniZnameZmenyPrihlaseniNaAktivitu) {
            $ucastniciIds = [];
            $idAktivity = (int)$posledniZnameZmenyPrihlaseniNaAktivitu['id_aktivity'];
            /** struktura dat viz admin/files/online-prezence-posledni-zname-zmeny-prihlaseni.js */
            $posledniZnameZmenyStavuPrihlaseni = new PosledniZmenyStavuPrihlaseni((int)$posledniZnameZmenyPrihlaseniNaAktivitu['id_aktivity']);
            $zmenyStavuPrihlaseniPole = [];
            foreach ($posledniZnameZmenyPrihlaseniNaAktivitu['ucastnici'] ?? [] as $posledniZnamaZmenaPrihlaseni) {
                $posledniCasZmenyStavuPrihlaseni = new \DateTimeImmutable($posledniZnamaZmenaPrihlaseni['cas_posledni_zmeny_prihlaseni']);
                $zmenaStavuPrihlaseni = $this->vytvorZmenuStavuPrihlaseni($posledniZnamaZmenaPrihlaseni);
                $ucastniciIds[] = $zmenaStavuPrihlaseni->idUzivatele();
                $zmenyStavuPrihlaseniPole[$posledniCasZmenyStavuPrihlaseni->getTimestamp()][] = $zmenaStavuPrihlaseni;
            }
            ksort($zmenyStavuPrihlaseniPole, SORT_NUMERIC);
            // chceme hledat jen od nejnovějších změn stavu přihlášení dále
            foreach (end($zmenyStavuPrihlaseniPole) ?: [] as $posledniZmenaStavuPrihlaseni) {
                $posledniZnameZmenyStavuPrihlaseni->addPosledniZmenaStavuPrihlaseni($posledniZmenaStavuPrihlaseni);
            }
            $nejnovejsiZmenyStavuPrihlaseni = AktivitaPrezence::dejPosledniZmeny($posledniZnameZmenyStavuPrihlaseni);
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
                            Aktivita::zId($idAktivity),
                            $zmenaStavuPrihlaseni->dorazilNejak(),
                            false
                        )
                        : '', // pokud JS zná ID učastníka této aktivity, tak už má jeho HTML a nepotřebuje ho
                ];
            }
        }
        $this->echoJson(['zmeny' => $zmenyProJson]);
    }

    private function vytvorZmenuStavuPrihlaseni(array $posledniZnamaZmenaPrihlaseni): ZmenaStavuPrihlaseni {
        $this->ohlidejData(['id_uzivatele', 'cas_posledni_zmeny_prihlaseni', 'stav_prihlaseni'], $posledniZnamaZmenaPrihlaseni);

        $posledniCasZmenyStavuPrihlaseni = new \DateTimeImmutable($posledniZnamaZmenaPrihlaseni['cas_posledni_zmeny_prihlaseni']);

        return ZmenaStavuPrihlaseni::vytvorZDatJavscriptu(
            (int)$posledniZnamaZmenaPrihlaseni['id_uzivatele'],
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
        $aktivita->ulozPrezenci($aktivita->prihlaseni());
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
        $aktivita = Aktivita::zId($idAktivity);
        if (!$ucastnik || !$aktivita || $dorazil === null) {
            $this->echoErrorJson('Chybné ID účastníka nebo aktivity nebo chybejici priznak zda dorazil');
            return;
        }

        if ($dorazil) {
            $aktivita->ulozZeDorazil($ucastnik);
        } else {
            $aktivita->zrusZeDorazil($ucastnik);
        }

        /** Abychom mměli nová data pro @see Aktivita::dorazilJakoCokoliv */
        $aktivita->refresh();

        $posledniZmenaStavuPrihlaseni = $aktivita->dejPrezenci()->posledniZmenaStavuPrihlaseni($ucastnik);

        $this->echoJson([
            'prihlasen' => $aktivita->dorazilJakoCokoliv($ucastnik),
            'casPosledniZmenyPrihlaseni' => $posledniZmenaStavuPrihlaseni->casZmenyProJs(),
            'stavPrihlaseni' => $posledniZmenaStavuPrihlaseni->stavPrihlaseniProJs(),
        ]);
    }

    private function ajaxUlozPrezenci(int $idAktivity, array $idDorazivsich) {
        $aktivita = Aktivita::zId($idAktivity);
        if (!$aktivita) {
            $this->echoErrorJson('Chybné ID aktivity' . $idAktivity);
            return;
        }
        $dorazili = \Uzivatel::zIds($idDorazivsich);
        $aktivita->ulozPrezenci($dorazili);

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

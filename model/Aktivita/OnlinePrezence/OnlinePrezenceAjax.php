<?php declare(strict_types=1);

namespace Gamecon\Aktivita\OnlinePrezence;

class OnlinePrezenceAjax
{

    /**
     * @var OnlinePrezenceHtml
     */
    private $onlinePrezenceHtml;

    public function __construct(OnlinePrezenceHtml $onlinePrezenceHtml) {
        $this->onlinePrezenceHtml = $onlinePrezenceHtml;
    }

    public function odbavAjax() {
        if (!post('ajax') && !get('ajax')) {
            return false;
        }
        if (post('akce') === 'uzavrit') {
            $this->ajaxUzavritAktivitu((int)post('id'));
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

    private function ajaxUzavritAktivitu(int $idAktivity) {
        $aktivita = \Aktivita::zId($idAktivity);
        if (!$aktivita) {
            $this->echoErrorJson('Chybné ID aktivity ' . $idAktivity);
            return;
        }
        $aktivita->ulozPrezenci($aktivita->prihlaseni());
        $aktivita->zamci();
        $aktivita->refresh();

        $this->echoJson(['zamcena' => $aktivita->zamcena()]);
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
        $aktivita = \Aktivita::zId($idAktivity);
        if (!$ucastnik || !$aktivita || $dorazil === null) {
            header("HTTP/1.1 400 Bad Request");
            $this->echoJson(['errors' => ['Chybné ID účastníka nebo aktivity nebo chybejici priznak zda dorazil']]);
            return;
        }
        if ($dorazil) {
            $aktivita->ulozZeDorazil($ucastnik);
        } else {
            if (!$aktivita->zrusZeDorazil($ucastnik)) {
                $this->echoErrorJson("Nepodařilo se zrušit účastníka {$ucastnik->jmenoNick()} z aktivity {$aktivita->nazev()}");
                return;
            }
        }
        /** Abychom mměli nová data pro @see \Aktivita::dorazilJakoCokoliv */
        $aktivita->refresh();

        $this->echoJson(['prihlasen' => $aktivita->dorazilJakoCokoliv($ucastnik)]);
    }

    private function ajaxUlozPrezenci(int $idAktivity, array $idDorazivsich) {
        $aktivita = \Aktivita::zId($idAktivity);
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
        $aktivita = \Aktivita::zId($idAktivity);
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
            true
        );
        foreach ($omniboxData as &$prihlasenyUzivatelOmnibox) {
            $prihlasenyUzivatel = \Uzivatel::zId($prihlasenyUzivatelOmnibox['value']);
            if (!$prihlasenyUzivatel) {
                continue;
            }
            $ucastnikHtml = $this->onlinePrezenceHtml->sestavHmlUcastnikaAktivity(
                $prihlasenyUzivatel,
                $aktivita,
                true /* jenom zobrazeni - skutečné uložení, že dorazil, řešíme až po vybrání uživatele z omniboxu */,
                false
            );
            $prihlasenyUzivatelOmnibox['html'] = $ucastnikHtml;
        }
        unset($prihlasenyUzivatelOmnibox);

        $this->echoJson($omniboxData);
    }
}

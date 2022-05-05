<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

class SystemoveNastaveniHtml
{
    /**
     * @var SystemoveNastaveni
     */
    private $systemoveNastaveni;

    public function __construct(SystemoveNastaveni $systemoveNastaveni) {
        $this->systemoveNastaveni = $systemoveNastaveni;
    }

    public function zobrazHtml() {

        $template = new \XTemplate(__DIR__ . '/templates/nastaveni.xtpl');

        $template->assign('ajaxKlic', SystemoveNastaveniAjax::AJAX_KLIC);
        $template->assign('postKlic', SystemoveNastaveniAjax::POST_KLIC);

        $zaznamyNastaveniProHtml = $this->dejZaznamyNastaveniProHtml();

        foreach ($zaznamyNastaveniProHtml as $zaznam) {
            foreach ($zaznam as $klic => $hodnota) {
                $template->assign($klic, $hodnota);
            }
            $template->parse('nastaveni.zaznam');
        }

        $template->parse('nastaveni');
    }

    private function dejHtmlInputType(string $datovyTyp) {
        switch (strtolower(trim($datovyTyp))) {
            case 'boolean' :
            case 'bool' :
                return 'checkbox';
            case 'integer' :
            case 'int' :
            case 'number' :
                return 'number';
            case 'date' :
                return 'date';
            case 'datetime' :
                return 'datetime-local';
            case 'string' :
            default :
                return 'text';
        }
    }

    public function dejZaznamyNastaveniProHtml(array $pouzeSTemitoKlici = null): array {
        $hodnotyNastaveni = $pouzeSTemitoKlici
            ? $this->systemoveNastaveni->dejZaznamyNastaveniPodleKlicu($pouzeSTemitoKlici)
            : $this->systemoveNastaveni->dejVsechnyZaznamyNastaveni();
        array_walk(
            $hodnotyNastaveni,
            function (array &$zaznam) {
                $zaznam['posledniZmena'] = (new \Gamecon\Cas\DateTimeCz($zaznam['kdy']))->relativni();
                $zaznam['zmenil'] = $zaznam['id_uzivatele']
                    ? \Uzivatel::zId($zaznam['id_uzivatele'])->jmenoNick()
                    : '';
                $zaznam['inputType'] = $this->dejHtmlInputType($zaznam['datovy_typ']);
            }
        );
        return $hodnotyNastaveni;
    }
}

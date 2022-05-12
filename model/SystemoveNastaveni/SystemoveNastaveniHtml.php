<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Gamecon\Cas\DateTimeCz;

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
        $template->assign('aktivniKlic', SystemoveNastaveniAjax::AKTIVNI_KLIC);
        $template->assign('hodnotaKlic', SystemoveNastaveniAjax::HODNOTA_KLIC);

        $template->assign('systemoveNastavenJsVerze', md5_file(__DIR__ . '/../../admin/files/systemove-nastaveni.js'));

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

    public function dejHtmlInputValue($hodnota, string $datovyTyp) {
        switch (strtolower(trim($datovyTyp))) {
            case 'date' :
                return $hodnota
                    ? (new DateTimeCz($hodnota))->formatDatumStandard()
                    : $hodnota;
            case 'datetime' :
                return $hodnota
                    ? (new DateTimeCz($hodnota))->formatCasStandard()
                    : $hodnota;
            default :
                return $hodnota;
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
                $zaznam['zmenil'] = '<strong>' . ($zaznam['id_uzivatele']
                        ? \Uzivatel::zId($zaznam['id_uzivatele'])->jmenoNick()
                        : '<i>SQL migrace</i>'
                    ) . '</strong><br>' . (new \Gamecon\Cas\DateTimeCz($zaznam['kdy']))->formatCasStandard();;
                $zaznam['inputType'] = $this->dejHtmlInputType($zaznam['datovy_typ']);
                $zaznam['inputValue'] = $this->dejHtmlInputValue($zaznam['hodnota'], $zaznam['datovy_typ']);
                $zaznam['vychoziHodnotaValue'] = $this->dejHtmlInputValue($zaznam['vychozi_hodnota'], $zaznam['datovy_typ']);
                $zaznam['checked'] = $zaznam['aktivni']
                    ? 'checked'
                    : '';
                $zaznam['disabled'] = $zaznam['vychozi_hodnota'] === ''
                    ? 'disabled'
                    : '';
                $zaznam['vychoziHodnotaDisplayClass'] = $zaznam['aktivni']
                    ? 'display-none'
                    : '';
                $zaznam['hodnotaDisplayClass'] = !$zaznam['aktivni']
                    ? 'display-none'
                    : '';
            }
        );
        return $hodnotyNastaveni;
    }
}

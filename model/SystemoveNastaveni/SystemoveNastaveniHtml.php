<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Gamecon\Cas\DateTimeCz;
use Gamecon\XTemplate\XTemplate;

class SystemoveNastaveniHtml
{
    public const SYNCHRONNI_POST_KLIC           = 'nastaveni';
    public const ZKOPIROVAT_OSTROU_KLIC         = 'zkopirovat_ostrou';
    public const EXPORTOVAT_ANONYMIZOVANOU_KLIC = 'exportovat_anonymizovanou';

    /**
     * @var SystemoveNastaveni
     */
    private $systemoveNastaveni;

    public function __construct(SystemoveNastaveni $systemoveNastaveni) {
        $this->systemoveNastaveni = $systemoveNastaveni;
    }

    public function zobrazHtml() {
        $template = new XTemplate(__DIR__ . '/templates/nastaveni.xtpl');

        $template->assign('ajaxKlic', SystemoveNastaveniAjax::AJAX_KLIC);
        $template->assign('postKlic', SystemoveNastaveniAjax::POST_KLIC);
        $template->assign('aktivniKlic', SystemoveNastaveniAjax::AKTIVNI_KLIC);
        $template->assign('hodnotaKlic', SystemoveNastaveniAjax::HODNOTA_KLIC);

        $template->assign(
            'systemoveNastavenJsVerze',
            md5_file(__DIR__ . '/../../admin/files/systemove-nastaveni.js')
        );

        $zaznamyNastaveniProHtml = $this->dejZaznamyNastaveniProHtml();
        $zaznamyPodleSkupin      = $this->seskupPodleSkupin($zaznamyNastaveniProHtml);

        foreach ($zaznamyPodleSkupin as $skupina => $zaznamyJedneSkupiny) {
            $this->vypisSkupinu($skupina, $zaznamyJedneSkupiny, $template);
        }

        // TODO fix import
        if (FALSE && $this->systemoveNastaveni->jsmeNaBete()) {
            $templateZkopirovaniOstre = new XTemplate(__DIR__ . '/templates/zkopirovani-ostre-databaze.xtpl');
            $templateZkopirovaniOstre->assign('synchronniPostKlic', self::SYNCHRONNI_POST_KLIC);
            $templateZkopirovaniOstre->assign('zkopirovatOstrouKlic', self::ZKOPIROVAT_OSTROU_KLIC);
            $templateZkopirovaniOstre->parse('zkopirovaniOstreDatabaze');
            $template->assign('zkopirovaniOstreDatabaze', $templateZkopirovaniOstre->text('zkopirovaniOstreDatabaze'));
            $template->parse('nastaveni.beta');
        }

        $templateAnonymniDatabaze = new XTemplate(__DIR__ . '/templates/export-anonymizovane-databaze.xtpl');
        $templateAnonymniDatabaze->assign('synchronniPostKlic', self::SYNCHRONNI_POST_KLIC);
        $templateAnonymniDatabaze->assign('exportovatAnonymizovanouKlic', self::EXPORTOVAT_ANONYMIZOVANOU_KLIC);
        $templateAnonymniDatabaze->parse('exportAnonymizovaneDatabaze');
        $template->assign('exportAnonymizovaneDatabaze', $templateAnonymniDatabaze->text('exportAnonymizovaneDatabaze'));
        $template->parse('nastaveni.exportAnonymizovaneDatabaze');

        $template->parse('nastaveni');
        $template->out('nastaveni');
    }

    private function seskupPodleSkupin(array $zaznamy): array {
        $zaznamyPodleSkupin = [];
        foreach ($zaznamy as $zaznam) {
            $zaznamyPodleSkupin[$zaznam['skupina']][] = $zaznam;
        }
        foreach ($zaznamyPodleSkupin as &$zaznamyJedneSkupiny) {
            usort(
                $zaznamyJedneSkupiny,
                static function (array $nejakyZaznam, array $jinyZaznam) {
                    return $nejakyZaznam['poradi'] <=> $jinyZaznam['poradi'];
                }
            );
        }
        return $zaznamyPodleSkupin;
    }

    private function vypisSkupinu(string $skupina, array $zaznamy, XTemplate $template) {
        $template->assign('nazevSkupiny', mb_ucfirst($skupina));
        $template->parse('nastaveni.skupina.nazev');

        foreach ($zaznamy as $zaznam) {
            foreach ($zaznam as $klic => $hodnota) {
                $template->assign($klic, $hodnota);
            }
            $template->parse('nastaveni.skupina.zaznam');
        }
        $template->parse('nastaveni.skupina');
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
            case 'date' : /* date a datetime vyžadují v Chrome nehezký formát, který nechceme
 https://stackoverflow.com/questions/30798906/the-specified-value-does-not-conform-to-the-required-format-yyyy-mm-dd-
    Navíc jediný benefit z date a datetime-local je nativní datepicker prohlížeče,
    který nechceme aradši použijeme jQuery plugin...
    Takže z toho prostě uděláme text input a nazdar */
            case 'datetime' :
            case 'string' :
            default :
                return 'text';
        }
    }

    private function dejHtmlTagInputType(string $datovyTyp) {
        switch (strtolower(trim($datovyTyp))) {
            case 'date' :
                return 'date';
            case 'datetime' :
                return 'datetime-local';
            case 'boolean' :
            case 'bool' :
            case 'integer' :
            case 'int' :
            case 'number' :
            case 'string' :
            default :
                return self::dejHtmlInputType($datovyTyp);
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
                $zaznam['zmenil']        = '<strong>' . ($zaznam['id_uzivatele']
                        ? \Uzivatel::zId($zaznam['id_uzivatele'])->jmenoNick()
                        : '<i>SQL migrace</i>'
                    ) . '</strong><br>' . (new \Gamecon\Cas\DateTimeCz($zaznam['kdy']))->formatCasStandard();;
                $zaznam['inputType']                  = $this->dejHtmlInputType($zaznam['datovy_typ']);
                $zaznam['tagInputType']               = $this->dejHtmlTagInputType($zaznam['datovy_typ']);
                $zaznam['inputValue']                 = $this->dejHtmlInputValue($zaznam['hodnota'], $zaznam['datovy_typ']);
                $zaznam['vychoziHodnotaValue']        = $this->dejHtmlInputValue($zaznam['vychozi_hodnota'], $zaznam['datovy_typ']);
                $zaznam['checked']                    = $zaznam['aktivni']
                    ? 'checked'
                    : '';
                $zaznam['disabled']                   = $zaznam['vychozi_hodnota'] === ''
                    ? 'disabled'
                    : '';
                $zaznam['vychoziHodnotaDisplayClass'] = $zaznam['aktivni']
                    ? 'display-none'
                    : '';
                $zaznam['hodnotaDisplayClass']        = !$zaznam['aktivni']
                    ? 'display-none'
                    : '';
            }
        );
        return $hodnotyNastaveni;
    }

    public function zpracujPost(): bool {
        $pozadavky = post(self::SYNCHRONNI_POST_KLIC);
        if (!$pozadavky) {
            return false;
        }
        if (!empty($pozadavky[self::ZKOPIROVAT_OSTROU_KLIC])) {
            $this->zkopirujOstrouDatabazi();
            oznameni('Ostrá databáze byla zkopírována');
            return true;
        }
        if (!empty($pozadavky[self::EXPORTOVAT_ANONYMIZOVANOU_KLIC])) {
            $this->exportujAnonymizovanouDatabazi();
            exit;
        }

        return false;
    }

    private function zkopirujOstrouDatabazi() {
        $kopieOstreDatabaze = new KopieOstreDatabaze();
        $kopieOstreDatabaze->zkopirujOstrouDatabazi();
    }

    private function exportujAnonymizovanouDatabazi() {
        $anonymizovanaDatabaze = AnonymizovanaDatabaze::vytvorZGlobals();
        $anonymizovanaDatabaze->obnov();
        $anonymizovanaDatabaze->exportuj();
    }
}

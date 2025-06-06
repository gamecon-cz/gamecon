<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Gamecon\Cas\DateTimeCz;
use Gamecon\XTemplate\XTemplate;
use Granam\RemoveDiacritics\RemoveDiacritics;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniStruktura as Sql;

class SystemoveNastaveniHtml
{
    public const SYNCHRONNI_POST_KLIC           = 'nastaveni';
    public const ZKOPIROVAT_OSTROU_KLIC         = 'zkopirovat_ostrou';
    public const EXPORTOVAT_ANONYMIZOVANOU_KLIC = 'exportovat_anonymizovanou';
    public const ZVYRAZNI                       = 'zvyrazni';

    /**
     * @var SystemoveNastaveni
     */
    private $systemoveNastaveni;

    public function __construct(SystemoveNastaveni $systemoveNastaveni)
    {
        $this->systemoveNastaveni = $systemoveNastaveni;
    }

    public function zobrazHtml()
    {
        $template = new XTemplate(__DIR__ . '/templates/systemove-nastaveni.xtpl');

        $template->assign('ajaxKlic', SystemoveNastaveniAjax::AJAX_KLIC);
        $template->assign('postKlic', SystemoveNastaveniAjax::POST_KLIC);
        $template->assign('vlastniKlic', SystemoveNastaveniAjax::VLASTNI_KLIC);
        $template->assign('hodnotaKlic', SystemoveNastaveniAjax::HODNOTA_KLIC);

        $template->assign(
            'systemoveNastavenJsVerze',
            md5_file(__DIR__ . '/../../admin/files/systemove-nastaveni.js'),
        );

        $zaznamyNastaveniProHtml = $this->dejZaznamyNastaveniProHtml();
        $zaznamyPodleSkupin      = $this->seskupPodleSkupin($zaznamyNastaveniProHtml);
        $klicKeZvyrazneni        = $this->klicKeZvyrazneni();

        foreach ($zaznamyPodleSkupin as $skupina => $zaznamyJedneSkupiny) {
            $this->vypisSkupinu($skupina, $zaznamyJedneSkupiny, $template, $klicKeZvyrazneni);
        }

        if ($this->systemoveNastaveni->jsmeNaBete()) {
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

    private function seskupPodleSkupin(array $zaznamy): array
    {
        $zaznamyPodleSkupin = [];
        foreach ($zaznamy as $zaznam) {
            $zaznamyPodleSkupin[$zaznam['skupina']][] = $zaznam;
        }
        foreach ($zaznamyPodleSkupin as &$zaznamyJedneSkupiny) {
            usort(
                $zaznamyJedneSkupiny,
                static function (array $nejakyZaznam, array $jinyZaznam) {
                    return $nejakyZaznam['poradi'] <=> $jinyZaznam['poradi'];
                },
            );
        }
        return $zaznamyPodleSkupin;
    }

    private function klicKeZvyrazneni(): string
    {
        return RemoveDiacritics::toConstantLikeName((string)get(self::ZVYRAZNI));
    }

    private function vypisSkupinu(string $skupina, array $zaznamy, XTemplate $template, string $klicKeZvyrazneni)
    {
        $template->assign('nazevSkupiny', mb_ucfirst($skupina));
        $template->parse('nastaveni.skupina.nazev');

        foreach ($zaznamy as $zaznam) {
            foreach ($zaznam as $klic => $hodnota) {
                $template->assign($klic, $hodnota);
            }
            $template->assign('zaznamClass', $zaznam[Sql::KLIC] === $klicKeZvyrazneni
                ? 'zvyrazni'
                : '',
            );
            $template->parse('nastaveni.skupina.zaznam');
        }
        $template->parse('nastaveni.skupina');
    }

    private function dejHtmlInputType(string $datovyTyp)
    {
        return match (strtolower(trim($datovyTyp))) {
            'boolean', 'bool' => 'checkbox',
            'integer', 'int', 'number' => 'number',
            'date', /* date a datetime vyžadují v Chrome nehezký formát, který nechceme
 https://stackoverflow.com/questions/30798906/the-specified-value-does-not-conform-to-the-required-format-yyyy-mm-dd-
    Navíc jediný benefit z date a datetime-local je nativní datepicker prohlížeče,
    který nechceme aradši použijeme jQuery plugin...
    Takže z toho prostě uděláme text input a nazdar */
            'datetime', 'string' => 'text',
            default => 'text',
        };
    }

    private function dejHtmlTagInputType(string $datovyTyp)
    {
        return match (strtolower(trim($datovyTyp))) {
            'date' => 'date',
            'datetime' => 'datetime-local',
            default => self::dejHtmlInputType($datovyTyp),
        };
    }

    private function dejHtmlInputValue($hodnota, string $datovyTyp)
    {
        return match (strtolower(trim($datovyTyp))) {
            'date' => $hodnota
                ? (new DateTimeCz($hodnota))->formatDatumStandardZarovnaneHtml()
                : $hodnota,
            'datetime' => $hodnota
                ? (new DateTimeCz($hodnota))->formatCasStandardZarovnaneHtml()
                : $hodnota,
            default => $hodnota,
        };
    }

    private function dejHtmlInputChecked($hodnota, string $datovyTyp): string
    {
        return match (strtolower(trim($datovyTyp))) {
            'bool', 'boolean' => $hodnota ? 'checked' : '',
            default => '',
        };
    }

    public function dejZaznamyNastaveniProHtml(array $pouzeSTemitoKlici = null, bool $prenacti = false): array
    {
        $hodnotyNastaveni = $pouzeSTemitoKlici
            ? $this->systemoveNastaveni->dejZaznamyNastaveniPodleKlicu($pouzeSTemitoKlici, $prenacti)
            : $this->systemoveNastaveni->dejVsechnyZaznamyNastaveni($prenacti);
        array_walk(
            $hodnotyNastaveni,
            function (array &$zaznam) {
                $zaznam['posledniZmena'] = (new DateTimeCz($zaznam[Sql::ZMENA_KDY]))->relativni();
                $zaznam['zmenil']        = '<strong>' . ($zaznam[Sql::ZMENA_KDY]
                        ? (\Uzivatel::zId($zaznam[Sql::ID_UZIVATELE]) ?? \Uzivatel::zId(\Uzivatel::SYSTEM))->jmenoNick()
                        : '<i>SQL migrace</i>'
                    ) . '</strong><br>' . (new DateTimeCz($zaznam[Sql::ZMENA_KDY]))->formatCasStandard();;
                $zaznam['inputType']                  = $this->dejHtmlInputType($zaznam[Sql::DATOVY_TYP]);
                $zaznam['tagInputType']               = $this->dejHtmlTagInputType($zaznam[Sql::DATOVY_TYP]);
                $zaznam['inputValue']                 = $this->dejHtmlInputValue($zaznam[Sql::HODNOTA], $zaznam[Sql::DATOVY_TYP]);
                $zaznam['inputChecked']               = $this->dejHtmlInputChecked($zaznam[Sql::HODNOTA], $zaznam[Sql::DATOVY_TYP]);
                $zaznam['vychoziHodnotaValue']        = $this->dejHtmlInputValue($zaznam[Sql::VYCHOZI_HODNOTA], $zaznam[Sql::DATOVY_TYP]);
                $zaznam['vychoziInputChecked']        = $this->dejHtmlInputChecked($zaznam[Sql::VYCHOZI_HODNOTA], $zaznam[Sql::DATOVY_TYP]);
                $zaznam['checked']                    = $zaznam[Sql::VLASTNI]
                    ? 'checked'
                    : '';
                $zaznam['checkboxDisabled']           = $zaznam[Sql::POUZE_PRO_CTENI] || $zaznam[Sql::VYCHOZI_HODNOTA] === ''
                    ? 'disabled'
                    : '';
                $zaznam['valueChangeDisabled']        = $zaznam[Sql::POUZE_PRO_CTENI]
                    ? 'disabled'
                    : '';
                $zaznam['vychoziHodnotaDisplayClass'] = $zaznam[Sql::VLASTNI]
                    ? 'display-none'
                    : '';
                $zaznam['hodnotaDisplayClass']        = !$zaznam[Sql::VLASTNI]
                    ? 'display-none'
                    : '';
            },
        );
        return $hodnotyNastaveni;
    }

    public function zpracujPost(): bool
    {
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

    private function zkopirujOstrouDatabazi()
    {
        $kopieOstreDatabaze = KopieOstreDatabaze::createFromGlobals();
        $kopieOstreDatabaze->zkopirujOstrouDatabazi();
    }

    private function exportujAnonymizovanouDatabazi(): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);
        $anonymizovanaDatabaze = AnonymizovanaDatabaze::vytvorZGlobals();
        $anonymizovanaDatabaze->obnov();
        $anonymizovanaDatabaze->exportuj();
    }
}

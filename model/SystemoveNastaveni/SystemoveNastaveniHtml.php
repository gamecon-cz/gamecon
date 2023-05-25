<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Gamecon\Cas\DateTimeCz;
use Gamecon\XTemplate\XTemplate;
use Granam\RemoveDiacritics\RemoveDiacritics;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniStruktura as Sql;
use Symfony\Component\Filesystem\Filesystem;

class SystemoveNastaveniHtml
{
    public const SYNCHRONNI_POST_KLIC           = 'nastaveni';
    public const ZKOPIROVAT_OSTROU_KLIC         = 'zkopirovat_ostrou';
    public const EXPORTOVAT_ANONYMIZOVANOU_KLIC = 'exportovat_anonymizovanou';
    public const ZALOHOVAT_KLIC                 = 'zalohovat_klic';
    public const OBNOVIT_KLIC                   = 'obnovit_klic';
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

        if (!$this->systemoveNastaveni->jsmeNaOstre()) {
            $templateZalohaAObnova = new XTemplate(__DIR__ . '/templates/zaloha-a-obnova-databaze.xtpl');
            $templateZalohaAObnova->assign('synchronniPostKlic', self::SYNCHRONNI_POST_KLIC);
            $templateZalohaAObnova->assign('zalohovatKlic', self::ZALOHOVAT_KLIC);
            $templateZalohaAObnova->assign('obnovitKlic', self::OBNOVIT_KLIC);
            foreach ($this->zalohyDatabaze() as $obdobi => $nazvyZaloh) {
                if (count($nazvyZaloh) === 0) {
                    continue;
                }
                $templateZalohaAObnova->assign('nazevSkupinyZaloh', $obdobi);
                foreach ($nazvyZaloh as $nazevZalohy) {
                    $templateZalohaAObnova->assign('nazevZalohy', $nazevZalohy);
                    $templateZalohaAObnova->parse('zalohaAObnovaDatabaze.skupina.zaloha');
                }
                $templateZalohaAObnova->parse('zalohaAObnovaDatabaze.skupina');
            }
            $templateZalohaAObnova->parse('zalohaAObnovaDatabaze');
            $template->assign('zalohaAObnovaDatabaze', $templateZalohaAObnova->text('zalohaAObnovaDatabaze'));

            $template->parse('nastaveni.neNaOstre');
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

    private function zalohyDatabaze(): array
    {
        $adresar = $this->adresarProRucniZalohu();
        $zalohy  = array_filter(
            scandir($adresar, SCANDIR_SORT_DESCENDING),
            static fn(string $slozka) => str_ends_with($slozka, '.sql') && is_file($adresar . '/' . $slozka),
        );
        return ['včera dnes a zítra' => $zalohy];
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

    private function dejHtmlTagInputType(string $datovyTyp)
    {
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

    public function dejHtmlInputValue($hodnota, string $datovyTyp)
    {
        switch (strtolower(trim($datovyTyp))) {
            case 'date' :
                return $hodnota
                    ? (new DateTimeCz($hodnota))->formatDatumStandardZarovnaneHtml()
                    : $hodnota;
            case 'datetime' :
                return $hodnota
                    ? (new DateTimeCz($hodnota))->formatCasStandardZarovnaneHtml()
                    : $hodnota;
            default :
                return $hodnota;
        }
    }

    public function dejZaznamyNastaveniProHtml(array $pouzeSTemitoKlici = null): array
    {
        $hodnotyNastaveni = $pouzeSTemitoKlici
            ? $this->systemoveNastaveni->dejZaznamyNastaveniPodleKlicu($pouzeSTemitoKlici)
            : $this->systemoveNastaveni->dejVsechnyZaznamyNastaveni();
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
                $zaznam['vychoziHodnotaValue']        = $this->dejHtmlInputValue($zaznam[Sql::VYCHOZI_HODNOTA], $zaznam[Sql::DATOVY_TYP]);
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

    public function zpracujPost(\Uzivatel $admin): bool
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
        if (!empty($pozadavky[self::ZALOHOVAT_KLIC])) {
            $this->zalohujDatabazi($admin);
            oznameni('Databáze byla zálohována');
            return true;
        }
        if (!empty($pozadavky[self::OBNOVIT_KLIC])) {
            $this->obnovDatabazi((string)$pozadavky[self::OBNOVIT_KLIC]);
            oznameni('Databáze byla obnovena');
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

    private function zalohujDatabazi(\Uzivatel $zalohujici)
    {
        $adresar   = $this->adresarProRucniZalohu();
        $mysqldump = NastrojeDatabaze::vytvorZGlobals()->vytvorMysqldumpHlavniDatabaze();
        $mysqldump->start($adresar . '/' . date(DateTimeCz::FORMAT_DB) . '_' . RemoveDiacritics::toConstantLikeValue($zalohujici->prezdivka()) . '.sql');
    }

    private function obnovDatabazi(string $nazevSouboru)
    {
        if ($this->systemoveNastaveni->jsmeNaOstre()) {
            throw new \Chyba('Je zakázáno obnovovat databázi na ostré');
        }
        $adresar          = $this->adresarProRucniZalohu();
        $soubor           = $adresar . '/' . $nazevSouboru;
        $nastrojeDatabaze = new NastrojeDatabaze($this->systemoveNastaveni);
        $spojeni          = dbConnectForAlterStructure(false);
        $nastrojeDatabaze->obnovDatabaziZeSouboru($soubor, $spojeni);
    }

    private function adresarProRucniZalohu(): string
    {
        $adresar = ZALOHA_DB_SLOZKA . '/manual';
        (new Filesystem())->mkdir($adresar);
        return $adresar;
    }

    private function exportujAnonymizovanouDatabazi()
    {
        $anonymizovanaDatabaze = AnonymizovanaDatabaze::vytvorZGlobals();
        $anonymizovanaDatabaze->obnov();
        $anonymizovanaDatabaze->exportuj();
    }
}

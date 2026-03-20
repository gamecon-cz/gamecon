<?php
declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use DateTimeInterface;
use Gamecon\Cas\DateTimeCz;
use Gamecon\XTemplate\XTemplate;
use Granam\RemoveDiacritics\RemoveDiacritics;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniStruktura as Sql;
use Gamecon\BackgroundProcess\BackgroundProcessService;

class SystemoveNastaveniHtml
{
    public const SYNCHRONNI_POST_KLIC           = 'nastaveni';
    public const ZKOPIROVAT_OSTROU_KLIC         = 'zkopirovat_ostrou';
    public const ZKOPIROVAT_ARCHIVNI_KLIC        = 'zkopirovat_archivni';
    public const ZKOPIROVAT_ZE_ZALOHY_KLIC      = 'zkopirovat_ze_zalohy';
    public const EXPORTOVAT_ANONYMIZOVANOU_KLIC = 'exportovat_anonymizovanou';
    public const UPDATE_ZUSTATKU_KLIC           = 'update_zustatku';
    public const ZVYRAZNI                       = 'zvyrazni';
    public const AJAX_STAV_KOPIE_KLIC           = 'stavKopieDatabazeZOstre';

    /**
     * @var SystemoveNastaveni
     */
    private $systemoveNastaveni;

    private bool $zobrazVysledekUpdateZustatku = false;

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
            'systemoveNastaveniJsVerze',
            md5_file(__DIR__ . '/../../admin/files/systemove-nastaveni.js'),
        );

        $zaznamyNastaveniProHtml = $this->dejZaznamyNastaveniProHtml();
        $zaznamyPodleSkupin = $this->seskupPodleSkupin($zaznamyNastaveniProHtml);
        $klicKeZvyrazneni = $this->klicKeZvyrazneni();

        foreach ($zaznamyPodleSkupin as $skupina => $zaznamyJedneSkupiny) {
            $this->vypisSkupinu($skupina, $zaznamyJedneSkupiny, $template, $klicKeZvyrazneni);
        }

        if ($this->systemoveNastaveni->jsmeNaBete() || $this->systemoveNastaveni->jsmeNaLocale()) {
            $templateZkopirovaniOstre = new XTemplate(__DIR__ . '/templates/zkopirovat-databazi-z-ostre.xtpl');
            $templateZkopirovaniOstre->assign('synchronniPostKlic', self::SYNCHRONNI_POST_KLIC);
            $templateZkopirovaniOstre->assign('zkopirovatOstrouKlic', self::ZKOPIROVAT_OSTROU_KLIC);
            $templateZkopirovaniOstre->assign('zkopirovatArchivniKlic', self::ZKOPIROVAT_ARCHIVNI_KLIC);
            $templateZkopirovaniOstre->assign('ajaxStavKopieKlic', self::AJAX_STAV_KOPIE_KLIC);

            $archivniRoky = range(2024, (int)(new \DateTimeImmutable())->format('Y') - 1);
            $archivniRokyOptions = implode(
                '',
                array_map(
                    static fn(int $rok) => "<option value=\"{$rok}\">{$rok}</option>",
                    $archivniRoky,
                ),
            );
            $templateZkopirovaniOstre->assign('archivniRokyOptions', $archivniRokyOptions);

            $souboruZaloh = $this->dejSouboruZaloh();
            $souboruZalohOptions = implode('', array_map(
                function (string $soubor): string {
                    $basename = basename($soubor);
                    $datum = new DateTimeCz('@' . filemtime($soubor));
                    $popisek = $this->formatujDatumSeStarim($datum);
                    return "<option value=\"{$basename}\">{$popisek}</option>";
                },
                $souboruZaloh,
            ));
            $templateZkopirovaniOstre->assign('zkopirovatZeZalohyKlic', self::ZKOPIROVAT_ZE_ZALOHY_KLIC);
            $templateZkopirovaniOstre->assign('souboruZalohOptions', $souboruZalohOptions);
            $templateZkopirovaniOstre->assign('maSouboruZaloh', !empty($souboruZaloh));

            // Zkontroluj, jestli proces běží
            $backgroundProcessService = BackgroundProcessService::vytvorZGlobals();
            $commandName = BackgroundProcessService::COMMAND_DB_COPY;
            $processInfo = $backgroundProcessService->getRunningProcessInfo($commandName);

            if ($processInfo) {
                $templateZkopirovaniOstre->assign('processRunning', true);
                $templateZkopirovaniOstre->assign('elapsedSeconds', $processInfo['elapsed_seconds']);
                $templateZkopirovaniOstre->assign('estimatedRemainingSeconds', $processInfo['estimated_remaining_seconds'] ?? 0);
                $templateZkopirovaniOstre->assign('progressPercent', $processInfo['progress_percent'] ?? 0);
                $templateZkopirovaniOstre->assign('elapsedFormatted', BackgroundProcessService::formatDuration($processInfo['elapsed_seconds']));
                $templateZkopirovaniOstre->assign('estimatedRemainingFormatted',
                    $processInfo['estimated_remaining_seconds'] !== null
                        ? BackgroundProcessService::formatDuration($processInfo['estimated_remaining_seconds'])
                        : 'neznámé',
                );
                $templateZkopirovaniOstre->parse('zkopirovatDatabaziZOstre.processBezi');
            } else {
                $templateZkopirovaniOstre->assign('processRunning', false);
                if (!empty($souboruZaloh)) {
                    $templateZkopirovaniOstre->parse('zkopirovatDatabaziZOstre.formular.zalohaForm');
                }
                if (!$this->systemoveNastaveni->jsmeNaLocale()) {
                    $templateZkopirovaniOstre->parse('zkopirovatDatabaziZOstre.formular.archivniForm');
                }
                $templateZkopirovaniOstre->parse('zkopirovatDatabaziZOstre.formular');
            }

            $templateZkopirovaniOstre->parse('zkopirovatDatabaziZOstre');
            $template->assign('zkopirovatDatabaziZOstre', $templateZkopirovaniOstre->text('zkopirovatDatabaziZOstre'));
            $template->parse('nastaveni.beta');
        }

        $templateAnonymniDatabaze = new XTemplate(__DIR__ . '/templates/export-anonymizovane-databaze.xtpl');
        $templateAnonymniDatabaze->assign('synchronniPostKlic', self::SYNCHRONNI_POST_KLIC);
        $templateAnonymniDatabaze->assign('exportovatAnonymizovanouKlic', self::EXPORTOVAT_ANONYMIZOVANOU_KLIC);
        $datumExportu = AnonymizovanaDatabaze::datumPoslednihoExportu();
        if ($datumExportu) {
            $templateAnonymniDatabaze->assign('datumExportu', $this->formatujDatumSeStarim($datumExportu));
            $templateAnonymniDatabaze->parse('exportAnonymizovaneDatabaze.existuje');
        } else {
            $templateAnonymniDatabaze->parse('exportAnonymizovaneDatabaze.neexistuje');
        }
        $templateAnonymniDatabaze->parse('exportAnonymizovaneDatabaze');
        $template->assign('exportAnonymizovaneDatabaze', $templateAnonymniDatabaze->text('exportAnonymizovaneDatabaze'));
        $template->parse('nastaveni.exportAnonymizovaneDatabaze');

        $this->vypisUpdateZustatku($template);

        $template->parse('nastaveni');
        $template->out('nastaveni');
    }

    private function formatujDatumSeStarim(DateTimeInterface $datum): string
    {
        return $datum->format(DateTimeCz::FORMAT_DB) . ' (' . DateTimeCz::createFromInterface($datum)->stari() . ')';
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
                static function (
                    array $nejakyZaznam,
                    array $jinyZaznam,
                ) {
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

    private function vypisSkupinu(
        string    $skupina,
        array     $zaznamy,
        XTemplate $template,
        string    $klicKeZvyrazneni,
    ) {
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
            'boolean', 'bool'          => 'checkbox',
            'integer', 'int', 'number' => 'number',
            'date', /* date a datetime vyžadují v Chrome nehezký formát, který nechceme
 https://stackoverflow.com/questions/30798906/the-specified-value-does-not-conform-to-the-required-format-yyyy-mm-dd-
    Navíc jediný benefit z date a datetime-local je nativní datepicker prohlížeče,
    který nechceme aradši použijeme jQuery plugin...
    Takže z toho prostě uděláme text input a nazdar */
            'datetime', 'string'       => 'text',
            default                    => 'text',
        };
    }

    private function dejHtmlTagInputType(string $datovyTyp)
    {
        return match (strtolower(trim($datovyTyp))) {
            'date'     => 'date',
            'datetime' => 'datetime-local',
            default    => self::dejHtmlInputType($datovyTyp),
        };
    }

    private function dejHtmlInputValue(
        $hodnota,
        string $datovyTyp,
    ) {
        return match (strtolower(trim($datovyTyp))) {
            'date'     => $hodnota
                ? (new DateTimeCz($hodnota))->formatDatumStandardZarovnaneHtml()
                : $hodnota,
            'datetime' => $hodnota
                ? (new DateTimeCz($hodnota))->formatCasStandardZarovnaneHtml()
                : $hodnota,
            default    => $hodnota,
        };
    }

    private function dejHtmlInputChecked(
        $hodnota,
        string $datovyTyp,
    ): string {
        return match (strtolower(trim($datovyTyp))) {
            'bool', 'boolean' => $hodnota
                ? 'checked'
                : '',
            default           => '',
        };
    }

    public function dejZaznamyNastaveniProHtml(
        array $pouzeSTemitoKlici = null,
        bool  $prenacti = false,
    ): array {
        $hodnotyNastaveni = $pouzeSTemitoKlici
            ? $this->systemoveNastaveni->dejZaznamyNastaveniPodleKlicu($pouzeSTemitoKlici, $prenacti)
            : $this->systemoveNastaveni->dejVsechnyZaznamyNastaveni($prenacti);
        array_walk(
            $hodnotyNastaveni,
            function (
                array &$zaznam,
            ) {
                $zaznam['posledniZmena'] = (new DateTimeCz($zaznam[Sql::ZMENA_KDY]))->relativni();
                $zaznam['zmenil'] = '<strong>' . ($zaznam[Sql::ZMENA_KDY]
                        ? (\Uzivatel::zId($zaznam[Sql::ID_UZIVATELE]) ?? \Uzivatel::zId(\Uzivatel::SYSTEM))->jmenoNick()
                        : '<i>SQL migrace</i>'
                    ) . '</strong><br>' . (new DateTimeCz($zaznam[Sql::ZMENA_KDY]))->formatCasStandard();;
                $zaznam['inputType'] = $this->dejHtmlInputType($zaznam[Sql::DATOVY_TYP]);
                $zaznam['tagInputType'] = $this->dejHtmlTagInputType($zaznam[Sql::DATOVY_TYP]);
                $zaznam['inputValue'] = $this->dejHtmlInputValue($zaznam[Sql::HODNOTA], $zaznam[Sql::DATOVY_TYP]);
                $zaznam['inputChecked'] = $this->dejHtmlInputChecked($zaznam[Sql::HODNOTA], $zaznam[Sql::DATOVY_TYP]);
                $zaznam['vychoziHodnotaValue'] = $this->dejHtmlInputValue($zaznam[Sql::VYCHOZI_HODNOTA], $zaznam[Sql::DATOVY_TYP]);
                $zaznam['vychoziInputChecked'] = $this->dejHtmlInputChecked($zaznam[Sql::VYCHOZI_HODNOTA], $zaznam[Sql::DATOVY_TYP]);
                $zaznam['checked'] = $zaznam[Sql::VLASTNI]
                    ? 'checked'
                    : '';
                $zaznam['checkboxDisabled'] = $zaznam[Sql::POUZE_PRO_CTENI] || $zaznam[Sql::VYCHOZI_HODNOTA] === ''
                    ? 'disabled'
                    : '';
                $zaznam['valueChangeDisabled'] = $zaznam[Sql::POUZE_PRO_CTENI]
                    ? 'disabled'
                    : '';
                $zaznam['vychoziHodnotaDisplayClass'] = $zaznam[Sql::VLASTNI]
                    ? 'display-none'
                    : '';
                $zaznam['hodnotaDisplayClass'] = !$zaznam[Sql::VLASTNI]
                    ? 'display-none'
                    : '';
            },
        );

        return $hodnotyNastaveni;
    }

    public function zpracujPost(?\Uzivatel $uzivatel): bool
    {
        $pozadavky = post(self::SYNCHRONNI_POST_KLIC);
        if (!$pozadavky) {
            return false;
        }
        if (!empty($pozadavky[self::ZKOPIROVAT_OSTROU_KLIC])) {
            try {
                $this->zkopirujDatabazi($uzivatel);
                oznameni('Kopírování databáze bylo spuštěno na pozadí. Proces může trvat několik minut. Sledujte jeho průběh níže.');
            } catch (\RuntimeException $e) {
                chyba($e->getMessage());
            }

            return true;
        }
        if (!empty($pozadavky[self::ZKOPIROVAT_ARCHIVNI_KLIC])) {
            $rok = (int)($pozadavky[self::ZKOPIROVAT_ARCHIVNI_KLIC]);
            $backupFile = $this->systemoveNastaveni->rootAdresarProjektu() . '/../' . $rok . '/backup/db/export_latest.sql.gz';
            try {
                $this->zkopirujZeSouboruZalohy($uzivatel, $backupFile);
                oznameni("Kopírování databáze {$rok} bylo spuštěno na pozadí...");
            } catch (\RuntimeException $e) {
                chyba($e->getMessage());
            }

            return true;
        }
        if (!empty($pozadavky[self::ZKOPIROVAT_ZE_ZALOHY_KLIC])) {
            $soubor = basename((string)$pozadavky[self::ZKOPIROVAT_ZE_ZALOHY_KLIC]);
            try {
                $this->zkopirujZeSouboruZalohy($uzivatel, $this->backupDir() . '/' . $soubor);
                oznameni("Kopírování ze zálohy {$soubor} bylo spuštěno na pozadí. Sledujte průběh níže.");
            } catch (\RuntimeException $e) {
                chyba($e->getMessage());
            }

            return true;
        }
        if (!empty($pozadavky[self::EXPORTOVAT_ANONYMIZOVANOU_KLIC])) {
            try {
                $this->exportujAnonymizovanouDatabazi();
                exit;
            } catch (\RuntimeException $e) {
                chyba($e->getMessage());
            }

            return true;
        }
        if (!empty($pozadavky[self::UPDATE_ZUSTATKU_KLIC])) {
            $this->zobrazVysledekUpdateZustatku = true;

            return false; // neděláme redirect, zobrazíme výsledek přímo
        }

        return false;
    }

    private function zkopirujZeSouboruZalohy(?\Uzivatel $requestedBy, string $backupFilePath): void
    {
        $backgroundProcessService = BackgroundProcessService::vytvorZGlobals();
        $commandName = BackgroundProcessService::COMMAND_DB_COPY;
        if ($backgroundProcessService->isProcessRunning($commandName)) {
            throw new \RuntimeException('Kopírování databáze již běží');
        }
        $workerScript = $this->systemoveNastaveni->rootAdresarProjektu()  . '/admin/scripts/zvlastni/systemove-nastaveni/workers/_database-copy-worker.php';
        $backgroundProcessService->startBackgroundProcess(
            $commandName,
            $workerScript,
            ['backupFile' => $backupFilePath],
            ['started_by' => $requestedBy->id()],
        );
    }

    private function backupDir(): string
    {
        if ($this->systemoveNastaveni->jsmeNaLocale()) {
            return $this->systemoveNastaveni->rootAdresarProjektu() . '/backup/db';
        }
        return $this->systemoveNastaveni->rootAdresarProjektu() . '/../ostra/backup/db';
    }

    private function dejSouboruZaloh(): array
    {
        $backupDir = $this->backupDir();
        if (!is_dir($backupDir)) {
            return [];
        }
        $soubory = glob($backupDir . '/export_*.sql.gz');
        if (!$soubory) {
            return [];
        }
        rsort($soubory); // newest first
        return $soubory; // return full paths
    }

    private function zkopirujDatabazi(?\Uzivatel $requestedBy, ?string $zdrojovaDbName = null)
    {
        $backgroundProcessService = BackgroundProcessService::vytvorZGlobals();
        $commandName = BackgroundProcessService::COMMAND_DB_COPY;

        // Zkontroluj, jestli už proces neběží
        if ($backgroundProcessService->isProcessRunning($commandName)) {
            throw new \RuntimeException('Kopírování databáze již běží');
        }

        // Spusť worker script na pozadí
        $workerScript = $this->systemoveNastaveni->rootAdresarProjektu() . '/admin/scripts/zvlastni/systemove-nastaveni/workers/_database-copy-worker.php';

        $backgroundProcessService->startBackgroundProcess(
            $commandName,
            $workerScript,
            $zdrojovaDbName ? ['sourceDb' => $zdrojovaDbName] : [],
            ['started_by' => $requestedBy->id()],
        );
    }

    private function vypisUpdateZustatku(XTemplate $template): void
    {
        $templateUpdateZustatku = new XTemplate(__DIR__ . '/templates/update-zustatku.xtpl');
        $templateUpdateZustatku->assign('synchronniPostKlic', self::SYNCHRONNI_POST_KLIC);
        $templateUpdateZustatku->assign('updateZustatkuKlic', self::UPDATE_ZUSTATKU_KLIC);
        $templateUpdateZustatku->assign('rocnik', ROCNIK);

        if ($this->zobrazVysledekUpdateZustatku) {
            $sqlParts = [
                <<<SQL
-- smazat všechny místnosti, aby se mohly nahrát každý rok znovu a nehrozilo, že to někdo začne zadávat k aktivitám, když to ještě není nahrané
DELETE FROM akce_lokace WHERE TRUE;
DELETE FROM lokace WHERE TRUE;
SQL,
            ];
            $vsechnaIds = dbOneArray('SELECT DISTINCT id_uzivatele FROM uzivatele_hodnoty');
            foreach (array_chunk($vsechnaIds, 100) as $chunkIds) {
                foreach (\Uzivatel::zIds($chunkIds) as $uzivatel) {
                    $finance    = $uzivatel->finance();
                    $sqlParts[] = <<<SQL
UPDATE uzivatele_hodnoty
SET zustatek={$finance->stav()} /* původní zůstatek z předchozích ročníků {$finance->zustatekZPredchozichRocniku()} */,
    poznamka='',
    ubytovan_s='',
    infopult_poznamka='',
    pomoc_typ='',
    pomoc_vice='',
    op=''
WHERE id_uzivatele={$uzivatel->id()};
SQL;
                }
                \Uzivatel::smazCache();
            }
            $templateUpdateZustatku->assign('sqlPrikazy', implode("\n", $sqlParts));
            $templateUpdateZustatku->parse('updateZustatku.vysledek');
        } else {
            $templateUpdateZustatku->parse('updateZustatku.formular');
        }

        $templateUpdateZustatku->parse('updateZustatku');
        $template->assign('updateZustatku', $templateUpdateZustatku->text('updateZustatku'));
        $template->parse('nastaveni.updateZustatku');
    }

    private function exportujAnonymizovanouDatabazi(): void
    {
        $anonymizovanaDatabaze = AnonymizovanaDatabaze::vytvorZGlobals();
        $anonymizovanaDatabaze->exportuj();
    }

    /**
     * AJAX endpoint pro zjištění stavu kopírování databáze
     */
    public function ajaxStavKopieDatabazeZOstre(): void
    {
        header('Content-Type: application/json');

        $backgroundProcessService = BackgroundProcessService::vytvorZGlobals();
        $commandName = BackgroundProcessService::COMMAND_DB_COPY;

        $processInfo = $backgroundProcessService->getRunningProcessInfo($commandName);

        if ($processInfo) {
            echo json_encode([
                'running'                     => true,
                'elapsedSeconds'              => $processInfo['elapsed_seconds'],
                'estimatedRemainingSeconds'   => $processInfo['estimated_remaining_seconds'],
                'progressPercent'             => $processInfo['progress_percent'],
                'elapsedFormatted'            => BackgroundProcessService::formatDuration($processInfo['elapsed_seconds']),
                'estimatedRemainingFormatted' => $processInfo['estimated_remaining_seconds'] !== null
                    ? BackgroundProcessService::formatDuration($processInfo['estimated_remaining_seconds'])
                    : 'neznámé',
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $lastInfo = $backgroundProcessService->getLastCompletedProcessInfo($commandName);
        echo json_encode([
            'running'      => false,
            'failed'       => $lastInfo && $lastInfo['status'] === 'failed',
            'errorMessage' => $lastInfo ? $lastInfo['error_message'] : null,
        ], JSON_UNESCAPED_UNICODE);
    }
}

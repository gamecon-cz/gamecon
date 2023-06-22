<?php

declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Gamecon\SystemoveNastaveni\Exceptions\ChybnaHodnotaSystemovehoNastaveni;
use Gamecon\Vyjimkovac\Vyjimkovac;
use Tracy\Logger;
use \Uzivatel;

class SystemoveNastaveniAjax
{
    public const AJAX_KLIC    = 'ajax';
    public const POST_KLIC    = 'nastaveni-ajax';
    public const VLASTNI_KLIC = 'vlastni';
    public const HODNOTA_KLIC = 'hodnota';

    public function __construct(
        private readonly SystemoveNastaveni     $systemoveNastaveni,
        private readonly SystemoveNastaveniHtml $systemoveNastaveniHtml,
        private readonly Uzivatel               $editujici,
        private readonly Vyjimkovac             $vyjimkovac,
    )
    {
    }

    public function zpracujPost(): bool
    {
        if (!get(self::AJAX_KLIC)) {
            return false;
        }
        $zmeny = post(self::POST_KLIC);
        if (!$zmeny) {
            return false;
        }

        try {
            foreach ($zmeny as $klic => $zmena) {
                if (array_key_exists(self::HODNOTA_KLIC, $zmena)) {
                    $this->systemoveNastaveni->ulozZmenuHodnoty(trim($zmena[self::HODNOTA_KLIC]), $klic, $this->editujici);
                }
                if (array_key_exists(self::VLASTNI_KLIC, $zmena)) {
                    $this->systemoveNastaveni->ulozZmenuPriznakuVlastni(
                    // filter_var z "true" udělá true a z "false" udělá false
                        filter_var(trim($zmena[self::VLASTNI_KLIC]), FILTER_VALIDATE_BOOLEAN),
                        $klic,
                        $this->editujici,
                    );
                }
            }
        } catch (ChybnaHodnotaSystemovehoNastaveni $invalidSystemSettingsValue) {
            $this->echoJson(['error' => 'Neplatná hodnota. ' . $invalidSystemSettingsValue->getMessage()], false);

            return true;
        } catch (\Throwable $throwable) {
            $this->vyjimkovac->zaloguj($throwable);

            $this->echoJson(['error' => 'Interní chyba. Kontaktuj vývojáře.'], false);

            return true;
        }

        $soucasneStavy = $this->systemoveNastaveniHtml->dejZaznamyNastaveniProHtml(array_keys($zmeny), true);
        $soucasnyStav  = reset($soucasneStavy);
        $this->echoJson($soucasnyStav, true);

        return true;
    }

    private function echoJson(array $data, bool $success)
    {
        header('Content-type: application/json');
        if (!$success) {
            http_response_code(400);
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

class SystemoveNastaveniAjax
{
    public const AJAX_KLIC = 'ajax';
    public const POST_KLIC = 'nastaveni';
    public const AKTIVNI_KLIC = 'aktivni';
    public const HODNOTA_KLIC = 'hodnota';

    /**
     * @var \Uzivatel
     */
    private $editujici;
    /**
     * @var SystemoveNastaveni
     */
    private $systemoveNastaveni;
    /**
     * @var SystemoveNastaveniHtml
     */
    private $systemoveNastaveniHtml;

    public function __construct(
        SystemoveNastaveni     $systemoveNastaveni,
        SystemoveNastaveniHtml $systemoveNastaveniHtml,
        \Uzivatel              $editujici
    ) {
        $this->systemoveNastaveni = $systemoveNastaveni;
        $this->systemoveNastaveniHtml = $systemoveNastaveniHtml;
        $this->editujici = $editujici;
    }

    public function zpracujPost(): bool {
        if (!get(self::AJAX_KLIC)) {
            return false;
        }
        $zmeny = post(self::POST_KLIC);
        if (!$zmeny) {
            return false;
        }
        foreach ($zmeny as $klic => $zmena) {
            if (array_key_exists(self::HODNOTA_KLIC, $zmena)) {
                $this->systemoveNastaveni->ulozZmenuHodnoty($zmena[self::HODNOTA_KLIC], $klic, $this->editujici);
            }
            if (array_key_exists(self::AKTIVNI_KLIC, $zmena)) {
                $this->systemoveNastaveni->ulozZmenuPlatnosti((bool)$zmena[self::AKTIVNI_KLIC], $klic, $this->editujici);
            }
        }

        $soucasneStavy = $this->systemoveNastaveniHtml->dejZaznamyNastaveniProHtml(array_keys($zmeny));
        $soucasnyStav = reset($soucasneStavy);
        $this->echoJson($soucasnyStav);

        return true;
    }

    private function echoJson(array $data) {
        header('Content-type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

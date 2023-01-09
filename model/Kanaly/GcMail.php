<?php

namespace Gamecon\Kanaly;

use \Gamecon\Cas\DateTimeCz;

/**
 * Třída pro sestavování mailu
 */
class GcMail
{

    private string $text;
    private array $adresati = [];
    private $predmet;

    public function __construct(string $text = '') {
        $this->text = $text;
    }

    public function adresat(string $adresat): self {
        $this->adresati[] = $adresat;
        return $this;
    }

    public function adresati(array $adresati): self {
        $this->adresati = $adresati;
        return $this;
    }

    /**
     * @param string $text utf-8 řetězec
     * @return string enkódovaný řetězec pro použití v hlavičce
     */
    private static function encode($text) {
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }

    /**
     * Odešle sestavenou zprávu.
     * @return bool jestli se zprávu podařilo odeslat
     */
    public function odeslat() {
        $from    = self::encode('GameCon') . ' <info@gamecon.cz>';
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset="UTF-8";',
            'From: ' . $from,
            'Reply-To: ' . $from,
        ];

        if (defined('MAILY_DO_SOUBORU') && MAILY_DO_SOUBORU) {
            return $this->zalogovatDo(MAILY_DO_SOUBORU, $headers);
        } else {
            return mail(
                implode(', ', $this->adresati),
                self::encode($this->predmet),
                $this->text,
                implode("\r\n", $headers)
            );
        }
    }

    public function predmet(string $predmet): self {
        $this->predmet = $predmet;
        return $this;
    }

    public function text(string $text): self {
        $this->text = $text;
        return $this;
    }

    private function zalogovatDo(string $soubor, array $hlavicky) {
        $text = (
            implode("\n", $hlavicky) . "\n" .
            "Čas: " . (new DateTimeCz)->formatDb() . "\n" .
            "Adresát: '" . implode(', ', $this->adresati) . "'\n" .
            "Předmět: '$this->predmet'\n" .
            trim($this->text) . "\n\n"
        );
        return file_put_contents($soubor, $text, FILE_APPEND);
    }

}

<?php

use \Gamecon\Cas\DateTimeCz;

/**
 * Třída pro sestavování mailu
 */
class GcMail {

  private
    $adresat,
    $predmet,
    $text;

  function __construct($zprava = '') {
    $this->text = $zprava;
  }

  function adresat($a = null) {
    if($a === null) {
      return $this->adresat;
    } else {
      $this->adresat = $a;
      return $this;
    }
  }

  /**
   * @param string $text utf-8 řetězec
   * @return string enkódovaný řetězec pro použití v hlavičce
   */
  private static function encode($text) {
    return '=?UTF-8?B?'.base64_encode($text).'?=';
  }

  /**
   * Odešle sestavenou zprávu.
   * @return bool jestli se zprávu podařilo odeslat
   */
  function odeslat() {
    $from = self::encode('GameCon').' <info@gamecon.cz>';
    $headers = [
      'MIME-Version: 1.0',
      'Content-Type: text/html; charset="UTF-8";',
      'From: ' . $from,
      'Reply-To: ' . $from
    ];

    if(defined('MAILY_DO_SOUBORU') && MAILY_DO_SOUBORU) {
      return $this->zalogovatDo(MAILY_DO_SOUBORU, $headers);
    } else {
      return mail(
        $this->adresat,
        self::encode($this->predmet),
        $this->text,
        implode("\r\n", $headers)
      );
    }
  }

  function predmet(/* variadic */) {
    if(func_num_args() == 0) return $this->predmet;

    $this->predmet = func_get_arg(0);
    return $this;
  }

  function text(/* variadic */) {
    if(func_num_args() == 0) return $this->text;

    $this->text = func_get_arg(0);
    return $this;
  }

  private function zalogovatDo($soubor, $hlavicky) {
    $text = (
      implode("\n", $hlavicky) . "\n" .
      "Čas: " . (new DateTimeCz)->formatDb() . "\n" .
      "Adresát: '$this->adresat'\n" .
      "Předmět: '$this->predmet'\n" .
      trim($this->text) . "\n\n"
    );
    return file_put_contents($soubor, $text, FILE_APPEND);
  }

}

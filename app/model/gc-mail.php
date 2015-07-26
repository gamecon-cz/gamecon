<?php

/**
 * Třída pro sestavování mailu
 */

class GcMail {

  protected $predmet, $adresat, $odesilatel, $text;

  function __construct($zprava = '') {
    $this->text = $zprava;
  }

  function adresat($a = null) {
    return $a ? $this->adresat = $a : $this->adresat;
  }

  function odesilatel($a = null) {
    //todo (potřeba implementovat v GcMail::odeslat())
  }

  /**
   * Odešle sestavenou zprávu
   * Starý kód, možno fixnout
   */
  public function odeslat() {
    $adresat = VETEV == VYVOJOVA ? 'godric@korh.cz' : $this->adresat ; //TODO místo odeslání někam logovat

    $from = self::encode('GameCon').' <info@gamecon.cz>';
    $headers = [
      'MIME-Version: 1.0',
      'Content-Type: text/html; charset="UTF-8";',
      'From: ' . $from,
      'Reply-To: ' . $from
    ];

    return mail(
      $adresat,
      self::encode($this->predmet),
      $this->text,
      implode("\r\n", $headers)
    );
  }

  function predmet($a = null) {
    return $a ? $this->predmet = $a : $this->predmet;
  }

  function text(...$a) {
    if(empty($a)) return $this->text;
    $this->text = $a[0];
    return $this;
  }

  /** Enkóduje utf-8 text pro použití v hlavičce */
  protected static function encode($text) {
    return '=?UTF-8?B?'.base64_encode($text).'?=';
    //return "=?$encoding?Q?".imap_8bit($text)."?=";
  }

}

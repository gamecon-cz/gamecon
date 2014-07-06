<?php

/**
 * Třída pro sestavování mailu
 */ 

class GcMail
{

  protected $predmet, $adresat, $odesilatel, $text;
  
  public function __construct($zprava)
  {
    $this->text=$zprava;
  }
  
  public function adresat($a=null)
  { return $a?$this->adresat=$a:$this->adresat; }
  
  public function odesilatel($a=null)
  {
    //todo (potřeba implementovat v GcMail::odeslat()) 
  }
  
  /**
   * Odešle sestavenou zprávu
   * Starý kód, možno fixnout   
   */      
  public function odeslat()
  {
    if(VETEV == VYVOJOVA) return; //TODO místo přeskočení někam logovat

    $from='=?UTF-8?B?'.base64_encode('GameCon').'?= <info@gamecon.cz>';
    $headers=array(
      'MIME-Version: 1.0',
      'Content-Type: text/html; charset="UTF-8";',
      'From: ' . $from,
      'Reply-To: ' . $from
    );
    
    return mail(
      $this->adresat,
      '=?UTF-8?B?'.base64_encode($this->predmet).'?=',
      $this->text,
      implode("\r\n", $headers)
    );
  }
  
  public function predmet($a=null)
  { return $a?$this->predmet=$a:$this->predmet; }
  
  ////////// protected věci //////////
  
  /**
   * Magic
   */     
  static protected function headerEnc($text, $encoding="utf-8")
  {
    return "=?$encoding?Q?".imap_8bit($text)."?=";
  }
        
}

?>

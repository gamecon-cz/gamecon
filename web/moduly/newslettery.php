<?php

use Gamecon\Newsletter\NewsletterPrihlaseni;

/**
 * @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

$this->blackarrowStyl(true);
$this->bezPaticky(true);
$this->info()->nazev('Newslettery');

/** @var Uzivatel|null $u */
if (post('subscribe')) {
  $email = trim((string)post('email'));
  if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    NewsletterPrihlaseni::prihlas($email, $systemoveNastaveni);
    Chyba::nastav(hlaska('newsletterPrihlasen', $email), Chyba::OZNAMENI);
  } else {
    Chyba::nastav(hlaska('neplatnyEmail'));
  }
  back();
}

?>

<div class="formular formular_stranka">
  <div class="bg"></div>

  <div class="formular_strankaNadpis" style="top: 50%">Chcete dostávat informace do emailu?</div>

  <form method="post">
    <label class="formular_polozka">
      <input type="email" name="email" autofocus placeholder="Vložte e-mail" tabindex="1" required>
    </label>

    <input type="hidden" name="subscribe" value="true">

    <input type="submit" value="Přihlásit k odběru" class="formular_primarni formular_primarni-sipka" tabindex="1">
  </form>

  <p>Maximálně tři e-maily ročně s hlavními informacemi pro blížící se ročník</p>
</div>

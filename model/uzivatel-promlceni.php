<?php

/**
 *
 */
class UzivatelPromlceni {
  
  /**
   * Promlčení zustatku u uživatele (nastavení zůstatku na 0)
   *
   * @param Uzivatel $uzp
   * @param int $idAdm id admina, který provedl promlčení
   * @throws Exception
   */
  function promlc($uzp, $idAdm) {
    $id = $uzp->id();
    $zustatek = $uzp->zustatek();
    dbBegin();
    try {
      dbUpdate('uzivatele_hodnoty', ['zustatek' => 0], ['id_uzivatele' => $id]);
      dbCommit();
    } catch(Exception $e) {
      // catch a rollback nutný, jinak chyba způsobí visící perzist. spojení a deadlocky
      dbRollback();
      throw $e;
    }

    $this->zaloguj("Promlčení provedl admin s id:          $idAdm");
    $this->zaloguj("Promlčení zůstatku pro uživatele s id: $id");
    $this->zaloguj("Promlčená částka:                      $zustatek Kč" . "\n");
  }

  /**
   * Zapíše zprávu do logu promlčování uživatelů.
   */
  private function zaloguj($zprava) {
    $soubor = SPEC . '/promlceni.log';
    $cas = date('Y-m-d H:i:s');
    file_put_contents($soubor, "$cas $zprava\n", FILE_APPEND);
  }
}

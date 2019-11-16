<?php

/**
 *
 */
class UzivatelSlucovani {

  /**
   * @return pole dvojic [tabulka, sloupec] odkazující na id_uzivatele
   */
  private function odkazujiciTabulky() {
    $odkazy = dbQuery('
      SELECT table_name, column_name
      FROM information_schema.key_column_usage
      WHERE
        table_schema = "' . DB_NAME . '" AND
        referenced_table_name = "uzivatele_hodnoty" AND
        referenced_column_name = "id_uzivatele"
    ')->fetch_all();

    // přidat odkazy kde není v db nastaven cizí klíč
    $odkazy[] = ['akce_prihlaseni_log', 'id_uzivatele'];
    $odkazy[] = ['platby', 'provedl'];
    $odkazy[] = ['akce_seznam', 'zamcel'];

    return $odkazy;
  }

  /**
   * @param array $zmeny páry sloupec => hodnota, které se mají upravit v
   * novém uživateli
   */
  function sluc(Uzivatel $stary, Uzivatel $novy, $zmeny) {
    $staryId = $stary->id();
    $novyId  = $novy->id();

    dbBegin();
    try {
      // převedení referencí na nového uživatele
      foreach($this->odkazujiciTabulky() as [$tabulka, $sloupec]) {
        $ignore = $tabulka == 'r_uzivatele_zidle' ? 'IGNORE' : ''; // u židlí ignorovat duplicity
        dbQuery("UPDATE $ignore $tabulka SET $sloupec = $novyId WHERE $sloupec = $staryId");
      }

      // smazání duplicitního uživatele - první aby update nezpůsobil duplicity
      dbDelete('uzivatele_hodnoty', ['id_uzivatele' => $staryId]);

      // aktualizace nového uživatele
      dbUpdate('uzivatele_hodnoty', $zmeny, ['id_uzivatele' => $novyId]);

      dbCommit();
    } catch(Exception $e) {
      // catch a rollback nutný, jinak chyba způsobí visící perzist. spojení a deadlocky
      dbRollback();
      throw $e;
    }

    $this->zaloguj("do id $novyId sloučeno a smazáno id $staryId");
    $this->zaloguj("  původní zůstatek smazaného účtu:  " . $stary->finance()->zustatekZPredchozichRocniku());
    $this->zaloguj("  původní zůstatek nového účtu:     " . $novy->finance()->zustatekZPredchozichRocniku());
    $this->zaloguj("  email smazaného účtu:             " . $stary->mail());
    $this->zaloguj("  email nového účtu:                " . $novy->mail());
    $novy = Uzivatel::zId($novy->id()); // přenačtení uživatele, aby se aktualizovaly finance
    $this->zaloguj("  aktuální nový zůstatek:           " . $novy->finance()->zustatekZPredchozichRocniku());
    $this->zaloguj("  aktuální nový email:              " . $novy->mail() . "\n");
  }

  /**
   * Zapíše zprávu do logu slučování uživatelů.
   */
  private function zaloguj($zprava) {
    $soubor = SPEC . '/slucovani.log';
    $cas = date('Y-m-d H:i:s');
    file_put_contents($soubor, "$cas $zprava\n", FILE_APPEND);
  }

}

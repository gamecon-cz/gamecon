<?php

/**
 * Rys pro načítání vzájemně se odkazujících kolekcí pomocí jediného dotazu.
 *
 * Příklad: Mám kolekci 100 aktivit. Chci zobrazit u každé aktivity
 * organizátora. Normálně bych při volání Aktivita::organizator() musel
 * načíst nový objekt Uzivatel z databáze, tj. vyvolal bych 100 dotazů do
 * databáze. Tento rys mi umožní z kolekce aktivit vyčíst všechna id
 * organizátorů a načíst automaticky všechny potřebné organizátory jedním
 * dotazem. Načtení organizátorů a přiřazení k všem aktivitám se provede
 * automaticky ve chvíli, kdy popvré zavolám metodu Aktivita::organizator()
 * a mimo třídu Aktivita o něm nemusím vědět.
 *
 * Metody využívají jakoby pojmenované argumenty, aby byl čitelnější zápis
 * jednotlivých argumentů v třídach, které budou rys používat.
 */
trait Prednacitani {

  /**
   * Vrací nadřízenou kolekci objektů, do kterých se bude přednačítat.
   *
   * Kolekce musí být ve formátu id objektu => objekt. Ideální je naplnit
   * ji v metodě zWhere().
   */
  abstract protected function kolekce();

  /**
   *
   */
  protected function prednacti1N($argumenty) {
    // TODO uvést, že je to nepřímá varianta
    throw new Exception('not implemented');
  }

  /**
   * Načte objekty s vazbou M:N, tj. pomocí nějaké vazební tabulky. Do
   * vybraného atributu v zdrjových objektech doplní pole (může být i prázdné).
   *
   * @param atribut Název atributu, do kterého se má zapsat pole odkazovaných
   * objektů.
   * @param cil Název třídy, jejíž instance se mají vytvořit jako cíle. Vhodné
   * použít zápis Trida::class místo 'Trida'.
   * @param tabulka Název vazební tabulky.
   * @param zdrojSloupec Název sloupce, kde jsou id objektů zdrojové kolekce.
   * @param cilSloupec Název sloupce, kde jsou id objektů cílové třídy.
   */
  protected function prednactiMN($argumenty) {
    // načtení pojmenovaných argumentů
    $atribut        =   $argumenty['atribut'];
    $cil            =   $argumenty['cil'];
    $tabulka        =   $argumenty['tabulka'];
    $zdrojSloupec   =   $argumenty['zdrojSloupec'];
    $cilSloupec     =   $argumenty['cilSloupec'];
    $kolekce        =   $this->kolekce();

    // dotaz vracející dvojice: id zdroje => ids cílů oddělené čárkou
    $q = dbQuery('
      SELECT
        '.dbQi($zdrojSloupec).',
        GROUP_CONCAT('.dbQi($cilSloupec).') as '.dbQv($cilSloupec).'
      FROM        '.dbQi($tabulka).'
      WHERE       '.dbQi($zdrojSloupec).' IN ($1)
      GROUP BY    '.dbQi($zdrojSloupec).'
    ', [array_keys($kolekce)]);

    // TODO extra metody na vytvoření indexů

    // načtení ids cílů plus jejich vložení do zdrojových objektů
    $cilIds = '0';
    foreach($q as $r) {
      $zdrojObjekt = $kolekce[$r[$zdrojSloupec]];
      $zdrojObjekt->$atribut = explode(',', $r[$cilSloupec]);
      $cilIds .= ',' . $r[$cilSloupec];
    }

    // vytvoření indexu cílů k vyhledávání podle id
    $cile = $cil::zIds(array_unique(explode(',', $cilIds)));
    $cileIndex = [];
    foreach($cile as $c) {
      $cileIndex[$c->id()] = $c;
    }

    // nahrazení ids v zdrojových objektech za skutečné cílové objekty
    foreach($kolekce as $zdrojObjekt) {
      if(!isset($zdrojObjekt->$atribut)) $zdrojObjekt->$atribut = [];
      foreach($zdrojObjekt->$atribut as &$a) $a = $cileIndex[$a];
      unset($a);
    }
  }

  /**
   *
   */
  protected function prednactiN1($argumenty) {
    // TODO uvést, že je to přímá varianta
    throw new Exception('not implemented');
  }

}

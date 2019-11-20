<?php

/**
 * Třída pro vytvoření a vypsání reportu 
 */

class Report
{
  private
    $sql,         // text dotazu, z kterého se report generuje
    $o,           // odpověď dotazu
    $hlavicky,    // hlavičky (názvy sloupců) výsledku
    $poleObsah,   // obsah ve formě pole
    $csvSep=';';  // oddělovač v csv souborech

  const
    BEZ_STYLU = 1;

  /**
   * Vytiskne report jako CSV
   */
  function tCsv()
  {
    $jmeno=substr($_SERVER['REQUEST_URI'],strrpos($_SERVER['REQUEST_URI'],'/')+1); // část url za posledním lomítkem
    header('Content-type: application/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$jmeno.'.csv"');
    echo(chr(0xEF).chr(0xBB).chr(0xBF)); //BOM bajty pro nastavení UTF-8 ve výsledném souboru
    $out=fopen('php://output','w'); //získáme filedescriptor výstupu stránky pro použití v fputcsv
    fputcsv($out,$this->hlavicky(),$this->csvSep);
    while($r=$this->radek())
      fputcsv($out,$r,$this->csvSep);
  }

  /**
   * Vytiskne report v zadaném formátu. Pokud není zadán, použije výchozí csv.
   * @throws Exception pokud formát není podporován
   */
  function tFormat($format = null) {
    if(!$format)                $this->tCsv(); // výchozí
    elseif($format == 'csv')    $this->tCsv();
    elseif($format == 'html')   $this->tHtml();
    else                        throw new Exception('formát není podporován');
  }

  /**
   * Vytiskne report jako HTML tabulku
   */
  function tHtml($param = 0)
  {
    if(!($param & self::BEZ_STYLU)) {
      echo '<style>
        table { border-collapse: collapse; }
        td, th { border: solid 1px; padding: 1px 4px; }
        tr:hover { background-color: #eee; }
      </style>';
    }
    echo '<table><tr>';
    foreach($this->hlavicky() as $h)
      echo "<th>$h</th>";
    echo '</tr>';
    while($r=$this->radek())
      echo '<tr><td>'.implode('</td><td>',$r).'</td></tr>';
    echo '</table>';
  }

  /**
   * Vytvoří report z asoc. polí, jako hlavičky použije klíče
   */
  static function zPole($pole) {
    return self::zPoli(array_keys($pole[0]), $pole);
  }

  /**
   * Vytvoří report ze zadaných polí
   * @param array $hlavicky hlavičkový řádek
   * @param array  $obsah pole normálních řádků
   */
  static function zPoli(array $hlavicky, array $obsah)
  {
    $report = new self();
    $report->hlavicky = $hlavicky;
    $report->poleObsah = $obsah;
    return $report;
  }

  /**
   * Vytvoří report ze zadaného SQL dotazu (selectu)
   */   
  static function zSql($dotaz)
  {
    $report=new self();
    $report->sql=$dotaz;
    return $report;
  }
  
  //////////////////////
  // Neveřejné metody //
  //////////////////////
  
  /**
   * Konstruktor
   */           
  protected function __construct()
  {
    $o=null;
    $hlavicky=null;
  }
  
  private function hlavicky()
  {
    if($this->hlavicky)
      return $this->hlavicky;
    if(!$this->o)
      $this->o=dbQuery($this->sql);
    for($i=0; $i<mysqli_num_fields($this->o); $i++)
    {
        $field_info=mysqli_fetch_field($this->o);
        $this->hlavicky[]=$field_info->name;
    }
    return $this->hlavicky;
  }
  
  private function radek()
  {
    if(isset($this->poleObsah)) {
      $t = current($this->poleObsah);
      next($this->poleObsah);
      return $t;
    }
    if(!$this->o)
      $this->o=dbQuery($this->sql);
    return mysqli_fetch_row($this->o);
  }
  
}

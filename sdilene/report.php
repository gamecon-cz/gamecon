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
    $csvSep=';';  // oddělovač v csv souborech

  /*
  const
    NAZEV=0,     // indexy do tabulky $prehled
    VYSLEDNY=15;
  */
  
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
   * Vytiskne report jako HTML tabulku
   */
  function tHtml()
  {
    echo '<style>
      table { border-collapse: collapse; }
      td, th { border: solid 1px; padding: 1px 4px; }
      tr:hover { background-color: #eee; }
    </style>';
    echo '<table><tr>';
    foreach($this->hlavicky() as $h)
      echo "<th>$h</th>";
    echo '</tr>';
    while($r=$this->radek())
      echo '<tr><td>'.implode('</td><td>',$r).'</td></tr>';
  }     
  
  /**
   * Vytvoří report ze zadaného SQL dotazu (selectu)
   */   
  static function zSql($dotaz)
  {
    $report=new Report();
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
    for($i=0; $i<mysql_num_fields($this->o); $i++) 
    {
        $field_info=mysql_fetch_field($this->o,$i);
        $this->hlavicky[]=$field_info->name;
    }
    return $this->hlavicky;
  }
  
  private function radek()
  {
    if(!$this->o)
      $this->o=dbQuery($this->sql);
    return mysql_fetch_row($this->o);    
  }
  
}

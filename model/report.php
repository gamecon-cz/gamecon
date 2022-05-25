<?php

use Box\Spout\Writer\Common\Creator\WriterEntityFactory;

/**
 * Třída pro vytvoření a vypsání reportu
 */
class Report
{
    private $sql;                   // text dotazu, z kterého se report generuje
    private $sqlParametry;          // parametry dotazu, z kterého se report generuje
    private $o;                     // odpověď dotazu
    private $hlavicky;              // hlavičky (názvy sloupců) výsledku
    private $poleObsah;             // obsah ve formě pole
    private $csvSeparator = ';';    // oddělovač v csv souborech

    public const BEZ_STYLU = 1;

    /**
     * Vytiskne report jako XLSX
     */
    public function tXlsx() {
        $writer = WriterEntityFactory::createXLSXWriter();

        // část url za posledním lomítkem
        $posledniCastUrl = substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], '/') + 1);
        $zacatekQuery = strpos($posledniCastUrl, '?');
        $posledniPath = $zacatekQuery
            ? substr($posledniCastUrl, 0, $zacatekQuery)
            : $posledniCastUrl;
        $fileName = $posledniPath . '.xlsx';
        $writer->openToBrowser($fileName); // stream data directly to the browser

        $rowFromValues = WriterEntityFactory::createRowFromArray($this->hlavicky());
        $writer->addRow($rowFromValues);

        while ($radek = $this->radek()) {
            $rowFromValues = WriterEntityFactory::createRowFromArray($radek);
            $writer->addRow($rowFromValues);
        }

        $writer->close();
    }

    /**
     * Vytiskne report jako CSV
     */
    public function tCsv() {
        $jmeno = substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], '/') + 1); // část url za posledním lomítkem
        header('Content-type: application/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $jmeno . '.csv"');
        echo(chr(0xEF) . chr(0xBB) . chr(0xBF)); //BOM bajty pro nastavení UTF-8 ve výsledném souboru
        $out = fopen('php://output', 'wb'); //získáme filedescriptor výstupu stránky pro použití v fputcsv
        $this->zapisCsvRadek($out, $this->hlavicky());
        while ($radek = $this->radek()) {
            $this->zapisCsvRadek($out, $radek);
        }
    }

    private function zapisCsvRadek($stream, array $radek) {
        fputcsv($stream, $this->odstranTagyZPole($radek), $this->csvSeparator);
    }

    private function odstranTagyZPole(array $values): array {
        return array_map('strip_tags', $values);
    }

    /**
     * Vytiskne report v zadaném formátu. Pokud není zadán, použije výchozí csv.
     * @throws Exception pokud formát není podporován
     */
    public function tFormat(?string $format = null) {
        $format = trim((string)$format);
        if (!$format || $format === 'xlsx') {
            $this->tXlsx();
        } elseif ($format === 'csv') {
            $this->tCsv();
        } elseif ($format === 'html') {
            $this->tHtml();
        } else {
            throw new Exception(sprintf("Formát '%s' není podporován", $format));
        }
    }

    /**
     * Vytiskne report jako HTML tabulku
     */
    public function tHtml($param = 0) {
        if (!($param & self::BEZ_STYLU)) {
            echo <<<HTML
<style>
    table { border-collapse: collapse; }
    td, th { border: solid 1px; padding: 1px 4px; }
    tr:hover { background-color: #eee; }
</style>
HTML;
        }
        echo '<table><tr>';
        foreach ($this->hlavicky() as $h) {
            echo "<th>$h</th>";
        }
        echo '</tr>';
        while ($r = $this->radek()) {
            echo '<tr><td>' . implode('</td><td>', $r) . '</td></tr>';
        }
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
     * @param array $obsah pole normálních řádků
     */
    static function zPoli(array $hlavicky, array $obsah): self {
        $report = new static();
        $report->hlavicky = $hlavicky;
        $report->poleObsah = $obsah;
        return $report;
    }

    /**
     * Vytvoří report ze zadaného SQL dotazu (selectu)
     * @param string $dotaz
     * @param array|null $dotazParametry = []
     */
    static function zSql(string $dotaz, array $dotazParametry = null): self {
        $report = new static();
        $report->sql = $dotaz;
        $report->sqlParametry = $dotazParametry;
        return $report;
    }

    //////////////////////
    // Neveřejné metody //
    //////////////////////

    /**
     * Konstruktor
     */
    protected function __construct() {
    }

    private function hlavicky() {
        if ($this->hlavicky) {
            return $this->hlavicky;
        }
        if (!$this->o) {
            $this->o = dbQuery($this->sql, $this->sqlParametry);
        }
        for ($i = 0, $sloupcu = mysqli_num_fields($this->o); $i < $sloupcu; $i++) {
            $field_info = mysqli_fetch_field($this->o);
            $this->hlavicky[] = $field_info->name;
        }
        return $this->hlavicky;
    }

    private function radek() {
        if (isset($this->poleObsah)) {
            $t = current($this->poleObsah);
            next($this->poleObsah);
            return $t;
        }
        if (!$this->o) {
            $this->o = dbQuery($this->sql, $this->sqlParametry);
        }
        return mysqli_fetch_row($this->o);
    }

}

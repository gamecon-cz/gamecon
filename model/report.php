<?php

use OpenSpout\Writer\Common\Creator\WriterEntityFactory;
use OpenSpout\Writer\Common\Creator\Style\StyleBuilder;
use OpenSpout\Writer\XLSX\Entity\SheetView;

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
    public function tXlsx(?string $nazevReportu, int $freeRows) {
        $previousMemoryLimit = ini_get('memory_limit');

        try {
            ini_set('memory_limit', '1G');

            $writer = WriterEntityFactory::createXLSXWriter();

            $fileName = $this->nazevSouboru('xlsx', $nazevReportu);
            $writer->openToBrowser($fileName); // stream data directly to the browser

            if ($freeRows > 0) {
                $sheetView = (new SheetView())->setFreezeRow($freeRows);
                $writer->getCurrentSheet()->setSheetView($sheetView);
            }

            $rows = [];

            $headerStyle = (new StyleBuilder())->setFontBold()->setFontSize(10)->build();
            $headerRow = WriterEntityFactory::createRowFromArray($this->hlavicky(), $headerStyle);
            $rows[] = $headerRow;

            $integerStyle = (new StyleBuilder())->setFormat('0')->build();
            $numberStyle = (new StyleBuilder())->setFormat('0.0')->build();
            $moneyStyle = (new StyleBuilder())->setFormat('0.00')->build();
            $fontSizeStyle = (new StyleBuilder())->setFontSize(10)->setFontName('Arial')->build();
            while ($radek = $this->radek()) {
                $cells = [];
                foreach ($radek as $hodnota) {
                    if ((string)(int)$hodnota === trim((string)$hodnota)) {
                        $cells[] = WriterEntityFactory::createCell((int)$hodnota, $integerStyle);
                    } elseif (preg_match('~^-?\d+[.,]\d{2}$~', trim((string)$hodnota))) {
                        $cells[] = WriterEntityFactory::createCell((float)$hodnota, $moneyStyle);
                    } else if ((string)(float)$hodnota === trim((string)$hodnota)
                        || preg_match('~^-?\d+([.,]\d+)?$~', trim((string)$hodnota))
                    ) {
                        $cells[] = WriterEntityFactory::createCell((float)$hodnota, $numberStyle);
                    } else {
                        $cells[] = WriterEntityFactory::createCell(
                            (string)$hodnota !== ''
                                ? (string)$hodnota
                                : ' ' // jinak je bohužel ignorován styl (font a jeho velikost) v prázdných buňkách
                        );
                    }
                }
                $rows[] = WriterEntityFactory::createRow($cells, $fontSizeStyle);
            }

            foreach ($this->calculateColumnsWidth($rows) as $columnNumber => $columnWidth) {
                // musí být před prvním addRow
                $writer->setColumnWidth($columnWidth, $columnNumber);
            }

            foreach ($rows as $row) {
                $writer->addRow($row);
            }

            $writer->close();
        } finally {
            if ($previousMemoryLimit) {
                ini_set('memory_limit', $previousMemoryLimit);
            }
        }
    }

    /**
     * @param \OpenSpout\Common\Entity\Row[] $rows
     * @return int[]
     */
    private function calculateColumnsWidth(array $rows): array {
        $widths = [];
        foreach ($rows as $row) {
            foreach ($row->getCells() as $index => $cell) {
                $pomer = $cell->getStyle()->isFontBold()
                    ? 1.5
                    : 1.3;
                $widths[$index + 1] = max(
                    (int)ceil(mb_strlen((string)$cell->getValue()) / $pomer) + 1,
                    $widths[$index + 1] ?? 1
                );
            }
        }
        return $widths;
    }

    private function nazevSouboru(string $pripona, ?string $nazevReportu): string {
        $nazevReportu = $nazevReportu !== null
            ? preg_replace('~[^[:alnum:]_-]~', '_', removeDiacritics(trim($nazevReportu)))
            : $this->nazevReportuZRequestu();
        return $nazevReportu . '_' . (new \Gamecon\Cas\DateTimeCz())->formatCasSoubor() . '.' . $pripona;
    }

    public function nazevReportuZRequestu(): string {
        // část url za posledním lomítkem
        $posledniCastUrl = substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['REQUEST_URI'], '/') + 1);
        $poziceZacatkuQuery = strpos($posledniCastUrl, '?');
        return $poziceZacatkuQuery !== false
            ? substr($posledniCastUrl, 0, $poziceZacatkuQuery)
            : $posledniCastUrl;
    }

    /**
     * Vytiskne report jako CSV
     */
    public function tCsv(string $nazevReportu = null) {
        $fileName = $this->nazevSouboru('csv', $nazevReportu);
        header('Content-type: application/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
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
    public function tFormat(string $format = null, string $nazev = null, int $freeRows = 1) {
        $format = trim((string)$format);
        if (!$format || $format === 'xlsx') {
            $this->tXlsx($nazev, $freeRows);
        } elseif ($format === 'csv') {
            $this->tCsv($nazev);
        } elseif ($format === 'html') {
            $this->tHtml();
        } else {
            throw new Chyba(sprintf("Formát '%s' není podporován", $format));
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

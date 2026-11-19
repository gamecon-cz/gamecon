<?php

declare(strict_types=1);

namespace Gamecon\HodnoceniTurnaje;

use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\ODS\Options as OdsOptions;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Soubor s kritérii, který org turnaje načte do editoru: tabulka XLSX, ODS nebo CSV, nebo textový zápis. Formát
 * pozná podle přípony názvu, nahraný soubor ji v dočasné cestě nemá. Zápisy popisuje @see ParserSablonyHodnoceni.
 */
final class SouborSablonyHodnoceni
{
    public const array PRIPONY = ['xlsx', 'ods', 'csv', 'txt', 'md'];

    /** Malý sešit má jednotky kB, tohle stačí s velkou rezervou a nepustí na server nic obřího. */
    public const int MAX_VELIKOST = 2 * 1024 * 1024;

    /**
     * @param string    $cesta        soubor na disku
     * @param string    $nazev        původní název souboru (podle přípony se pozná formát)
     * @param list<int> $vychoziSkala když v tabulce škála není (text ji mít musí)
     * @throws ChybaSablonyHodnoceni
     */
    public static function nacti(string $cesta, string $nazev, array $vychoziSkala): SablonaHodnoceni
    {
        if ((int)@filesize($cesta) > self::MAX_VELIKOST) {
            throw new ChybaSablonyHodnoceni([sprintf(
                'Soubor je moc velký, kritéria se vejdou do %d MB.',
                self::MAX_VELIKOST / 1024 / 1024,
            )]);
        }
        $pripona = mb_strtolower(pathinfo($nazev, PATHINFO_EXTENSION));

        return match ($pripona) {
            'xlsx', 'ods' => ParserSablonyHodnoceni::rozeberTabulku(self::prectiSesit($cesta, $pripona), $vychoziSkala),
            'csv'         => ParserSablonyHodnoceni::rozeberTabulku(self::prectiCsv(self::prectiText($cesta)), $vychoziSkala),
            'txt', 'md'   => ParserSablonyHodnoceni::rozeber(self::prectiText($cesta)),
            'xls'         => throw new ChybaSablonyHodnoceni([
                'Starý formát XLS neumím přečíst. V Excelu tabulku ulož jako XLSX nebo CSV.',
            ]),
            default       => throw new ChybaSablonyHodnoceni([sprintf(
                'Soubor %s neumím přečíst. Kritéria načtu z tabulky XLSX, ODS nebo CSV, nebo z textového souboru .txt.',
                $pripona === ''
                    ? 'bez přípony'
                    : '.' . $pripona,
            )]),
        };
    }

    /**
     * Řádky CSV podle čísla řádku. Oddělovač pozná z prvního neprázdného řádku: český Excel dává středník, jiný
     * program čárku nebo tabulátor.
     *
     * @return \Generator<int, list<string>>
     */
    public static function prectiCsv(string $text): \Generator
    {
        $text = ParserSablonyHodnoceni::normalizuj($text);
        $prvniRadek = '';
        foreach (explode("\n", $text) as $radek) {
            if (trim($radek) !== '') {
                $prvniRadek = $radek;
                break;
            }
        }
        $pocty = [
            ';'  => substr_count($prvniRadek, ';'),
            ','  => substr_count($prvniRadek, ','),
            "\t" => substr_count($prvniRadek, "\t"),
        ];
        arsort($pocty);
        $oddelovac = (string)array_key_first($pocty);
        if ($pocty[$oddelovac] === 0) {
            $oddelovac = ';';
        }

        $soubor = fopen('php://temp', 'r+b');
        fwrite($soubor, $text);
        rewind($soubor);
        $cisloRadku = 0;
        try {
            while (($bunky = fgetcsv($soubor, null, $oddelovac, '"', '')) !== false) {
                $cisloRadku++;
                // prázdný řádek je [null]
                yield $cisloRadku => array_map(static fn (?string $bunka): string => (string)$bunka, $bunky);
            }
        } finally {
            fclose($soubor);
        }
    }

    /**
     * Řádky prvního listu podle čísla řádku, i prázdné, aby čísla v chybách seděla s tabulkou.
     *
     * @return \Generator<int, list<string>>
     * @throws ChybaSablonyHodnoceni
     */
    private static function prectiSesit(string $cesta, string $pripona): \Generator
    {
        if ($pripona === 'ods') {
            $moznosti = new OdsOptions();
            $moznosti->SHOULD_PRESERVE_EMPTY_ROWS = true;
            $ctecka = new OdsReader($moznosti);
        } else {
            $moznosti = new XlsxOptions();
            $moznosti->SHOULD_PRESERVE_EMPTY_ROWS = true;
            $ctecka = new XlsxReader($moznosti);
        }
        try {
            $ctecka->open($cesta);
            foreach ($ctecka->getSheetIterator() as $list) {
                $cisloRadku = 0;
                foreach ($list->getRowIterator() as $radek) {
                    $cisloRadku++;
                    yield $cisloRadku => array_map(self::getTextBunky(...), $radek->toArray());
                }
                // kritéria jsou na prvním listu
                break;
            }
        } catch (OpenSpoutException) {
            throw new ChybaSablonyHodnoceni([sprintf(
                'Soubor nejde přečíst jako tabulku %s. Je opravdu z Excelu nebo LibreOffice?',
                mb_strtoupper($pripona),
            )]);
        } finally {
            $ctecka->close();
        }
    }

    private static function prectiText(string $cesta): string
    {
        $text = @file_get_contents($cesta);
        if ($text === false) {
            throw new ChybaSablonyHodnoceni(['Soubor se nepodařilo přečíst, zkus ho nahrát znovu.']);
        }

        return $text;
    }

    /**
     * Číslo z tabulky jako text bez desetinné části, když ji nemá (škála 1 2 3 v buňkách je 1.0, 2.0…).
     */
    private static function getTextBunky(mixed $bunka): string
    {
        return match (true) {
            is_string($bunka)                                   => $bunka,
            is_int($bunka)                                      => (string)$bunka,
            is_float($bunka) && floor($bunka) === $bunka
            && abs($bunka) < 1e15                               => (string)(int)$bunka,
            is_float($bunka)                                    => (string)$bunka,
            is_bool($bunka)                                     => $bunka
                ? '1'
                : '0',
            $bunka instanceof \DateTimeInterface                => $bunka->format('j. n. Y'),
            default                                             => '',
        };
    }
}

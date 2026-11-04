<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

/**
 * Požadavky na nahrávání po částech tak, jak je posílá admin/files/nahravani-po-castech.js. Třída, která trait
 * používá, musí používat i ZadostiDoAdmina a TestovaciDokumenty a mít adresář `$zdroje` pro soubory k nahrání.
 */
trait PosilaniPoCastech
{
    /**
     * @param string $adresa modul, na jehož adresu skript požadavky posílá (adresa stránky s formulářem)
     *
     * @return string id nahrávání, které je celé poslané
     */
    private function posliSouborPoCastech(string $session, string $cesta, string $nazev, string $adresa = 'web/soubory'): string
    {
        $zacatek = $this->posli($session, 'zahaj', [], http_build_query([
            'velikost' => filesize($cesta),
            'nazev'    => $nazev,
        ]), 'application/x-www-form-urlencoded', adresa: $adresa);
        self::assertSame(200, $zacatek['kod'], $zacatek['telo']);
        $id = $zacatek['json']['id'];
        $castBajtu = $zacatek['json']['castBajtu'];

        $soubor = fopen($cesta, 'rb');
        self::assertNotFalse($soubor);
        try {
            for ($index = 0; ! feof($soubor); ++$index) {
                $cast = (string) fread($soubor, $castBajtu);
                if ($cast === '') {
                    break;
                }
                $odpoved = $this->posli($session, 'cast', [
                    'nahrani' => $id,
                    'index'   => $index,
                ], $cast, adresa: $adresa);
                self::assertSame(200, $odpoved['kod'], $odpoved['telo']);
                self::assertTrue($odpoved['json']['ok']);
            }
        } finally {
            fclose($soubor);
        }

        return $id;
    }

    /**
     * @param array<string, int|string> $dotaz
     * @param string                    $adresa modul s případným dotazem, ke kterému se přidá `nahravaniPoCastech`
     *
     * @return array{kod: int, telo: string, json: array<string, mixed>|null}
     */
    private function posli(
        string $cookieFile,
        string $krok,
        array $dotaz,
        string $telo,
        string $typTela = 'application/octet-stream',
        bool $sHlavickou = true,
        string $adresa = 'web/soubory',
    ): array {
        $curl = $this->curl($cookieFile, $adresa . (str_contains($adresa, '?') ? '&' : '?') . http_build_query([
            'nahravaniPoCastech' => $krok,
        ] + $dotaz));
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $telo);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: ' . $typTela,
            ...($sHlavickou ? ['X-Gamecon-Nahravani: 1'] : []),
        ]);
        curl_setopt($curl, CURLOPT_TIMEOUT, 120);
        $odpoved = curl_exec($curl);
        self::assertNotFalse($odpoved, curl_error($curl));

        return [
            'kod'  => curl_getinfo($curl, CURLINFO_HTTP_CODE),
            'telo' => (string) $odpoved,
            'json' => json_decode((string) $odpoved, true),
        ];
    }

    /**
     * ZIP bez komprese, aby měl skutečně tolik megabajtů, kolik se řekne.
     */
    private function velkyZip(int $megabajtu): string
    {
        $data = $this->zdroje . '/data.bin';
        $soubor = fopen($data, 'wb');
        for ($blok = 0; $blok < $megabajtu / 4; ++$blok) {
            fwrite($soubor, random_bytes(4 * 1024 * 1024));
        }
        fclose($soubor);
        $cesta = $this->zdroje . '/velky.zip';
        $zip = new \ZipArchive();
        $zip->open($cesta, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFile($data, 'data.bin');
        $zip->setCompressionName('data.bin', \ZipArchive::CM_STORE);
        $zip->close();
        unlink($data);

        return $cesta;
    }

    /**
     * PDF o několika MB, tedy z víc než jedné části.
     */
    private function pdfSOdsazenim(int $megabajtu): string
    {
        $cesta = $this->zdroje . '/' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($cesta, self::pdfBajty() . str_repeat('% vata ' . "\n", $megabajtu * 1024 * 1024 / 8));

        return $cesta;
    }
}

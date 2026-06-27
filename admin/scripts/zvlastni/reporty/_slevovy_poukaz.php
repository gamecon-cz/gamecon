<?php

/**
 * Sdílené funkce pro slevové poukazy – generování kódu a vykreslení tiskového
 * PNG poukazu. Prefix `_` brání tomu, aby se soubor načetl jako samostatná
 * admin stránka; ostatní skripty si ho includnou.
 */

/**
 * Vygeneruje nový (zaručeně unikátní) slevový kód, uloží ho a vrátí.
 */
function vygenerujSlevovyKod(int $createdBy): string
{
    $charset = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // bez znaků matoucích při opisu (0/O, 1/I…)
    do {
        $kod = '';
        for ($i = 0; $i < 10; $i++) {
            $kod .= $charset[random_int(0, strlen($charset) - 1)];
        }
    } while (dbRecordExists('slevove_kody', ['kod' => $kod]));

    dbQuery(
        'INSERT INTO slevove_kody(kod, createdBy, createdAt, usedBy, usedAt, invalidated)
         VALUES ($0, $1, NOW(), NULL, NULL, 0)',
        [$kod, $createdBy],
    );

    return $kod;
}

/**
 * Vykreslí kód do grafiky poukazu a vrátí PNG jako binární řetězec.
 */
function vykresliSlevovyPoukazPng(string $kod): string
{
    $poukaz      = new Imagick(ADMIN . '/files/design/poukaz2026.png');
    $imagickDraw = new ImagickDraw();
    $imagickDraw->setFont(ADMIN . '/files/design/JetBrainsMono-Bold.ttf');
    $imagickDraw->setFontSize(110);
    $imagickDraw->setTextKerning(13);
    $imagickDraw->setFillColor(new ImagickPixel('#000000'));
    $imagickDraw->rotate(-5.8);
    $imagickDraw->annotation(605, 645, $kod);
    $poukaz->drawImage($imagickDraw);
    $poukaz->setFormat('png');

    return $poukaz->getImageBlob();
}

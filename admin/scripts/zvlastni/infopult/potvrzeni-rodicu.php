<?php

/**
 * @var Uzivatel|void $u
 * @var Uzivatel|void $uPracovni
 */

use Gamecon\Pravo;

if (empty($u) || !$u->maPravo(Pravo::ADMINISTRACE_INFOPULT)) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden 🚫';
    return;
}

$idUzivatele = get('id');

$uPotvrzeni = Uzivatel::zId($idUzivatele, true);

if ($uPotvrzeni->potvrzeniZakonnehoZastupceSouborOd() === null) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not Found 🔎';
    return;
}

$cestaKSouboruSPotvrzenimRodicu = $uPotvrzeni->cestaKSouboruSPotvrzenimRodicu();
if (!is_readable($cestaKSouboruSPotvrzenimRodicu)) {
    $cestaKSouboruSPotvrzenimRodicu = $uPotvrzeni->cestaKSouboruSPotvrzenimRodicu('pdf');
    if (!is_readable($cestaKSouboruSPotvrzenimRodicu)) {
        header('HTTP/1.1 404 Not Found');
        echo 'Not Found 🔎';
        return;
    }
}

header('HTTP/1.1 200 OK');
$changeTime = filectime($cestaKSouboruSPotvrzenimRodicu);
if ($changeTime) {
    header(
        'Last-Modified: ' .
        (new DateTimeImmutable())->setTimestamp($changeTime)->format(DateTimeInterface::RFC7231)
    );
}
header('ETag: ' . md5_file($cestaKSouboruSPotvrzenimRodicu));
header('Content-Length: ' . filesize($cestaKSouboruSPotvrzenimRodicu));
header('Content-Type: ' . mime_content_type($cestaKSouboruSPotvrzenimRodicu));

readfile($cestaKSouboruSPotvrzenimRodicu);
exit();

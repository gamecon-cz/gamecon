<?php

/**
 * @var Uzivatel|void $u
 */

if (empty($u)) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden 🚫';
    return;
}

if ($u->potvrzeniZakonnehoZastupceSouborOd() === null) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not Found 🔎';
    return;
}

if (!is_readable($u->cestaKSouboruSPotvrzenimRodicu())) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not Found 🔎';
    return;
}

header('HTTP/1.1 200 OK');
$changeTime = filectime($u->cestaKSouboruSPotvrzenimRodicu());
if ($changeTime) {
    header(
        'Last-Modified: ' .
        (new DateTimeImmutable())->setTimestamp($changeTime)->format(DateTimeInterface::RFC7231)
    );
}
header('ETag: ' . md5_file($u->cestaKSouboruSPotvrzenimRodicu()));
header('Content-Length: ' . filesize($u->cestaKSouboruSPotvrzenimRodicu()));
header('Content-Type: ' . mime_content_type($u->cestaKSouboruSPotvrzenimRodicu()));

readfile($u->cestaKSouboruSPotvrzenimRodicu());
exit();

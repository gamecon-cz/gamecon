<?php
/**
 * @var Uzivatel|void $u
 * @var Uzivatel|void $uPracovni
 */
if (empty($u)) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden 🚫';
    return;
}

$idUzivatele = get('id');

if (empty($uPracovni) && !$idUzivatele) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not Found 🔎';
    return;
}

$uPracovni = $idUzivatele && (!$uPracovni || (int)$idUzivatele !== (int)$uPracovni->id())
    ? Uzivatel::zId($idUzivatele)
    : $uPracovni;

if (empty($uPracovni)) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not Found 🔎';
    return;
}

if (!is_readable($uPracovni->cestaKSouboruSPotvrzenimProtiCovidu())) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not Found 🔎';
    return;
}

header('HTTP/1.1 200 OK');
$changeTime = filectime($uPracovni->cestaKSouboruSPotvrzenimProtiCovidu());
if ($changeTime) {
    header(
        'Last-Modified: ' .
        (new DateTimeImmutable())->setTimestamp($changeTime)->format(DateTimeInterface::RFC7231)
    );
}
header('ETag: ' . md5_file($uPracovni->cestaKSouboruSPotvrzenimProtiCovidu()));
header('Content-Length: ' . filesize($uPracovni->cestaKSouboruSPotvrzenimProtiCovidu()));
header('Content-Type: ' . mime_content_type($uPracovni->cestaKSouboruSPotvrzenimProtiCovidu()));

readfile($uPracovni->cestaKSouboruSPotvrzenimProtiCovidu());
exit();

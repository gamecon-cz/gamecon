<?php
/**
 * @var Modul $this
 * @var Uzivatel|void $u
 */

$this->bezStranky(true);

if (empty($u)) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden 🚫';
    return;
}

$idUzivatele = (int)get('id');

if (!$idUzivatele) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not Found 🔎';
    return;
}

if ($idUzivatele !== $u->id()) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not Found 🔎';
    return;
}

if (get('smazat')) {
    $u->smazPotvrzeniProtiCovidu();
    if (is_ajax()) {
        $covidSekceFunkce = require __DIR__ . '/covid-sekce-funkce.php';
        echo json_encode(['covidSekce' => $covidSekceFunkce($u->shop())]);
        exit();
    }
    oznameni('Tvé potvrzení ke Covidu bylo smazáno.');
    back();
}

if (!is_readable($u->cestaKSouboruSPotvrzenimProtiCovidu())) {
    header('HTTP/1.1 404 Not Found');
    echo 'Not Found 🔎';
    return;
}

header('HTTP/1.1 200 OK');
$changeTime = filectime($u->cestaKSouboruSPotvrzenimProtiCovidu());
if ($changeTime) {
    header(
        'Last-Modified: ' .
        (new DateTimeImmutable())->setTimestamp($changeTime)->format(DateTimeInterface::RFC7231)
    );
}
header('ETag: ' . md5_file($u->cestaKSouboruSPotvrzenimProtiCovidu()));
header('Content-Length: ' . filesize($u->cestaKSouboruSPotvrzenimProtiCovidu()));
header('Content-Type: ' . mime_content_type($u->cestaKSouboruSPotvrzenimProtiCovidu()));

readfile($u->cestaKSouboruSPotvrzenimProtiCovidu());
exit();

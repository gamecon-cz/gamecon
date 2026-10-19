<?php

declare(strict_types=1);

use Gamecon\Web\QrOdkaz;

/**
 * QR kódy se sociálními sítěmi a dalšími odkazy pro orgy (plakáty, letáky, prezentace).
 *
 * @var Modul                       $this
 * @var Gamecon\XTemplate\XTemplate $t
 * @var Uzivatel|null               $u
 */
if (! $u) {
    throw new Neprihlasen();
}
if (! $u->jeOrganizator()) {
    throw new Nenalezeno();
}

$this->info()->nazev('QR kódy k sockám');
$this->pridejJsSoubor(__DIR__ . '/../soubory/blackarrow/qrka/script.js');

// Logo doprostřed kódu = název PNG souboru v soubory/blackarrow/qrka/. Zatím bez log, ať kódy vypadají jednotně.
$qrKody = [
    'instagram'       => ['Instagram', 'https://gamecon.cz/instagram', null],
    'facebook'        => ['Facebook', 'https://gamecon.cz/facebook', null],
    'discord'         => ['Discord', 'https://gamecon.cz/discord', null],
    'youtube'         => ['YouTube', 'https://gamecon.cz/youtube', null],
    'tiskova_kronika' => ['Tisková kronika', 'https://gamecon.cz/soubory/obsah/download/tiskova_kronika.pdf', null],
];

foreach ($qrKody as $id => [$nazev, $url, $logo]) {
    $qrOdkaz = new QrOdkaz(
        $url,
        "qr-{$id}",
        $logo !== null ? __DIR__ . "/../soubory/blackarrow/qrka/{$logo}" : null,
    );
    $t->assign([
        'id'    => $id,
        'nazev' => htmlspecialchars($nazev),
        'url'   => htmlspecialchars($qrOdkaz->url()),
        'png'   => $qrOdkaz->pngDataUri(),
        'svg'   => $qrOdkaz->svgDataUri(),
    ]);
    $t->parse('qrka.kod');
}

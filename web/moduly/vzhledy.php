<?php

declare(strict_types=1);

use Gamecon\Web\VzhledWebu;
use Gamecon\Web\VzhledWebuEnum;
use Gamecon\XTemplate\XTemplate;

/**
 * Rozcestník variant moderního vzhledu (VzhledWebuEnum): titulka v každé variantě vedle sebe, na prezentaci návrhů.
 * Rámy ukazují titulku přes ?nahledVzhledu=…, který si nic nepamatuje, variantu zapne až odkaz pod jejím názvem.
 * Samostatná stránka bez kostry webu, šablona je mimo adresáře vzhledů, ať ji Modul::sablona() nenačte sám.
 * Po výběru varianty se smaže spolu s ostatními variantami.
 *
 * @var Modul $this
 */

$this->bezStranky(true);

$rozcestnik = new XTemplate(__DIR__ . '/../sablony/vzhledy.xtpl');
$rozcestnik->assign([
    'base' => URL_WEBU . '/',
    'css'  => 'soubory/moderni/vzhledy.css?version=' . md5_file(__DIR__ . '/../soubory/moderni/vzhledy.css'),
    'js'   => 'soubory/moderni/vzhledy.js?version=' . md5_file(__DIR__ . '/../soubory/moderni/vzhledy.js'),
]);
foreach (VzhledWebuEnum::getModerni() as $vzhled) {
    $rozcestnik->assign([
        'pismeno'    => $vzhled->getPismenoVarianty(),
        'nazev'      => htmlspecialchars((string) $vzhled->getNazevVarianty()),
        'popis'      => htmlspecialchars((string) $vzhled->getPopisVarianty()),
        'urlNahledu' => htmlspecialchars(VzhledWebu::getUrlNahledu(URL_WEBU . '/', $vzhled)),
        'urlZapnuti' => htmlspecialchars(VzhledWebu::getUrlPrepnuti(URL_WEBU . '/', [], $vzhled)),
    ]);
    $rozcestnik->parse('vzhledy.varianta');
}
$rozcestnik->parse('vzhledy');
$rozcestnik->out('vzhledy');

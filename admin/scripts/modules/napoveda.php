<?php

declare(strict_types=1);

use Gamecon\Pravo;

/**
 * Uživatelská nápověda k administraci a k veřejnému webu. Kapitoly jsou
 * Markdown soubory v docs/napoveda/ (verzované s kódem, nasazované spolu
 * s aplikací). Uživatel vidí jen kapitoly k částem adminu, na které má právo.
 *
 * nazev: Nápověda
 * pravo: 100
 * prava: 100, 101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 112, 113
 *
 * @var Uzivatel $u
 */

$slozkaKapitol = realpath(__DIR__ . '/../../../docs/napoveda');

// Pořadí zde určuje pořadí v navigaci. Práva: kapitolu vidí uživatel
// s KTERÝMKOLI z uvedených práv, prázdné pole = vidí každý, kdo vidí Nápovědu.
$kapitoly = [
    'uvod'          => [],
    'verejny-web'   => [],
    'infopult'      => [Pravo::ADMINISTRACE_INFOPULT],
    'uzivatel'      => [Pravo::ADMINISTRACE_UBYTOVANI, Pravo::ADMINISTRACE_INFOPULT],
    'aktivity'      => [Pravo::ADMINISTRACE_AKCE],
    'moje-aktivity' => [Pravo::ADMINISTRACE_MOJE_AKTIVITY],
    'prezence'      => [Pravo::ADMINISTRACE_PREZENCE],
    'finance'       => [Pravo::ADMINISTRACE_FINANCE],
    'penize'        => [Pravo::ADMINISTRACE_PENIZE],
    'reporty'       => [Pravo::ADMINISTRACE_REPORTY],
    'statistiky'    => [Pravo::ADMINISTRACE_STATISTIKY],
    'web'           => [Pravo::ADMINISTRACE_WEB],
    'nastaveni'     => [Pravo::ADMINISTRACE_NASTAVENI],
    'prava'         => [Pravo::ADMINISTRACE_PRAVA],
];

$dostupneKapitoly = [];
foreach ($kapitoly as $slug => $potrebnaPrava) {
    $maPravoNaKapitolu = $potrebnaPrava === [];
    foreach ($potrebnaPrava as $potrebnePravo) {
        if ($u->maPravo($potrebnePravo)) {
            $maPravoNaKapitolu = true;
            break;
        }
    }
    if (!$maPravoNaKapitolu) {
        continue;
    }
    $soubor = $slozkaKapitol . '/' . $slug . '.md';
    if (!is_file($soubor)) {
        continue;
    }
    $obsahKapitoly = (string)file_get_contents($soubor);
    $nadpis        = preg_match('@^#\s+(.+)$@m', $obsahKapitoly, $m)
        ? trim($m[1])
        : $slug;
    $dostupneKapitoly[$slug] = [
        'nadpis' => $nadpis,
        'obsah'  => $obsahKapitoly,
    ];
}

if ($dostupneKapitoly === []) {
    echo '<p>Nápověda zatím neobsahuje žádnou kapitolu, kterou bys mohl' . $u->koncovkaDlePohlavi() . ' vidět.</p>';
    return;
}

$vybranaKapitola = get('kapitola');
if (!isset($dostupneKapitoly[$vybranaKapitola])) {
    $vybranaKapitola = array_key_first($dostupneKapitoly);
}
?>
<style>
    .napoveda {
        display: flex;
        gap: 2em;
        align-items: flex-start;
    }

    .napoveda__menu {
        flex: 0 0 14em;
        position: sticky;
        top: 1em;
    }

    .napoveda__menu ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .napoveda__menu li {
        margin: 0;
    }

    .napoveda__menu a {
        display: block;
        padding: .35em .6em;
        border-radius: .3em;
        text-decoration: none;
    }

    .napoveda__menu a.napoveda__aktivni {
        background: #ddd;
        font-weight: bold;
    }

    .napoveda__obsah {
        flex: 1 1 auto;
        max-width: 55em;
        line-height: 1.5;
    }

    .napoveda__obsah h1 {
        margin-top: 0;
    }

    .napoveda__obsah table {
        border-collapse: collapse;
    }

    .napoveda__obsah th,
    .napoveda__obsah td {
        border: 1px solid #bbb;
        padding: .3em .6em;
    }

    .napoveda__paticka {
        margin-top: 3em;
        font-size: .85em;
        color: #666;
        border-top: 1px solid #ddd;
        padding-top: .5em;
    }
</style>
<div class="napoveda">
    <nav class="napoveda__menu">
        <ul>
            <?php foreach ($dostupneKapitoly as $slug => $kapitola) { ?>
                <li>
                    <a href="napoveda?kapitola=<?= urlencode($slug) ?>"
                       class="<?= $slug === $vybranaKapitola ? 'napoveda__aktivni' : '' ?>">
                        <?= htmlspecialchars($kapitola['nadpis'], ENT_QUOTES) ?>
                    </a>
                </li>
            <?php } ?>
        </ul>
    </nav>
    <div class="napoveda__obsah">
        <?= markdownNoCache($dostupneKapitoly[$vybranaKapitola]['obsah']) ?>
        <p class="napoveda__paticka">
            Našel<?= $u->koncovkaDlePohlavi() ?> jsi v nápovědě chybu nebo ti tu něco chybí?
            Kapitoly žijí v repozitáři ve složce <code>docs/napoveda/</code> —
            napiš správci webu, nebo rovnou navrhni úpravu.
        </p>
    </div>
</div>

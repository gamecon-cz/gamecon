<?php

declare(strict_types=1);

/**
 * Posune TERMÍNY pro diferenční scénáře — načítá se přes auto_prepend_file, tedy dřív než
 * jakýkoli kód aplikace. „Teď" zůstává skutečné; termíny se posunou proti němu, takže se
 * aplikace chová, jako by bylo cílové datum. Sloupec `nabizet_do` posouvá `cas.sh` v DB.
 *
 * Termíny se v aplikaci nedrží jen v DB: SystemoveNastaveni je publikuje jako konstanty a
 * u nenastavených (`vlastni = 0`) si je dopočítá z ročníku. Posouvat řádky v DB proto nestačí
 * — legacy jede dál po skutečných datech. Konstanty se ale definují pod `if (!defined(...))`,
 * takže kdo je nadefinuje první, vyhrává, a žádná úprava jádra není potřeba.
 *
 * Datum se bere z GAMECON_DIFF_DATUM (env). Bez něj soubor nedělá nic.
 */

$cilovyDen = getenv('GAMECON_DIFF_DATUM');
if ($cilovyDen === false || $cilovyDen === '') {
    return;
}

$dnes   = new DateTimeImmutable('today');
$cil    = new DateTimeImmutable($cilovyDen);
$posun  = (int) $dnes->diff($cil)->format('%r%a');

// Skutečné hodnoty ročníku 2026; posouvají se o stejný počet dní jako „dnešek", takže
// vzájemné odstupy zůstanou zachované.
$terminy = [
    'REG_GC_OD'                                       => '2026-05-13 20:26:00',
    'REG_GC_DO'                                       => '2026-07-26 22:00:00',
    'GC_BEZI_OD'                                      => '2026-07-23 12:00:00',
    'GC_BEZI_DO'                                      => '2026-07-26 23:59:59',
    'PRVNI_VLNA_KDY'                                  => '2026-05-20 20:26:00',
    'DRUHA_VLNA_KDY'                                  => '2026-06-10 20:26:00',
    'HROMADNE_ODHLASOVANI_2'                          => '2026-07-19 23:59:59',
    'HROMADNE_ODHLASOVANI_3'                          => '2026-05-01 23:59:59',
    'UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE'           => '2026-07-19',
    'JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE'               => '2026-07-19',
    'PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE' => '2026-07-15',
    'MIKINY_LZE_OBJEDNAT_A_MENIT_DO_DNE'              => '2026-06-15',
    'TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE'              => '2026-08-01',
];

foreach ($terminy as $klic => $puvodni) {
    $posunuto = (new DateTimeImmutable($puvodni))->modify(sprintf('%+d days', -$posun));
    $format   = strlen($puvodni) === 10 ? 'Y-m-d' : 'Y-m-d H:i:s';
    if (!defined($klic)) {
        define($klic, $posunuto->format($format));
    }
}

<?php

use Gamecon\XTemplate\XTemplate;

/**
 * Stránka s linky na reporty
 *
 * Reporty jsou obecně neoptimalizovaný kód (cyklické db dotazy apod.), nepočítá
 * se s jejich časově kritickým použitím.
 *
 * nazev: Reporty
 * pravo: 104
 */

$pouzitiReportu = static function (array $r): array {
    return [
        'jmeno_posledniho_uzivatele' => $r['id_posledniho_uzivatele']
            ? (new Uzivatel(dbOneLine('SELECT * FROM uzivatele_hodnoty WHERE id_uzivatele=' . $r['id_posledniho_uzivatele'])))->jmenoNick()
            : '',
        'cas_posledniho_pouziti'     => $r['cas_posledniho_pouziti']
            ? (new DateTime($r['cas_posledniho_pouziti'], new DateTimeZone($r['casova_zona_posledniho_pouziti'])))->format('j. m. Y H:m:s')
            : '',
        'pocet_pouziti'              => $r['pocet_pouziti'],
    ];
};

$t = new XTemplate(__DIR__ . '/reporty.xtpl');

$univerzalniReporty = dbFetchAll(<<<SQL
SELECT reporty.*,
       reporty_log_pouziti.id_uzivatele AS id_posledniho_uzivatele,
       reporty_log_pouziti.cas_pouziti AS cas_posledniho_pouziti,
       reporty_log_pouziti.casova_zona AS casova_zona_posledniho_pouziti
FROM (
  SELECT skript, nazev, format_xlsx, format_html,
        COUNT(reporty_log_pouziti.id) AS pocet_pouziti,
        MAX(reporty_log_pouziti.id) AS id_posledniho_logu
  FROM reporty
  LEFT JOIN reporty_log_pouziti ON reporty.id = reporty_log_pouziti.id_reportu
  LEFT JOIN uzivatele_hodnoty ON reporty_log_pouziti.id_uzivatele = uzivatele_hodnoty.id_uzivatele
  WHERE reporty.viditelny
  GROUP BY reporty.id
) AS reporty
LEFT JOIN reporty_log_pouziti ON reporty_log_pouziti.id = id_posledniho_logu
ORDER BY reporty.nazev
SQL
);

foreach ($univerzalniReporty as $r) {
    $pouziti = $pouzitiReportu($r);
    $kontext = [
        'nazev'                      => str_ireplace(['{ROK}', '{ROCNIK}'], ROCNIK, $r['nazev']),
        'html'                       => $r['format_html']
            ? '<a href="reporty/' . $r['skript'] . (strpos('?', $r['skript']) === false ? '?' : '&') . 'format=html" target="_blank">html</a>'
            : '',
        'xlsx'                       => $r['format_xlsx']
            ? '<a href="reporty/' . $r['skript'] . (strpos('?', $r['skript']) === false ? '?' : '&') . 'format=xlsx">xlsx</a>'
            : '',
        'jmeno_posledniho_uzivatele' => $pouziti['jmeno_posledniho_uzivatele'],
        'cas_posledniho_pouziti'     => $pouziti['cas_posledniho_pouziti'],
        'pocet_pouziti'              => $pouziti['pocet_pouziti'],
    ];
    $t->assign($kontext);
    $t->parse('reporty.report');
}

$quickReporty = dbFetchAll(<<<SQL
SELECT reporty_quick.*,
       reporty_log_pouziti.id_uzivatele AS id_posledniho_uzivatele,
       reporty_log_pouziti.cas_pouziti AS cas_posledniho_pouziti,
       reporty_log_pouziti.casova_zona AS casova_zona_posledniho_pouziti
FROM (
  SELECT reporty_quick.id, reporty_quick.nazev,
  COUNT(reporty_log_pouziti.id) AS pocet_pouziti,
  MAX(reporty_log_pouziti.id) AS id_posledniho_logu
  FROM reporty_quick
  LEFT JOIN reporty ON reporty.skript = CONCAT('quick-', reporty_quick.id)
  LEFT JOIN reporty_log_pouziti ON reporty.id = reporty_log_pouziti.id_reportu
  LEFT JOIN uzivatele_hodnoty ON reporty_log_pouziti.id_uzivatele = uzivatele_hodnoty.id_uzivatele
  GROUP BY reporty_quick.id
) AS reporty_quick
LEFT JOIN reporty_log_pouziti ON reporty_log_pouziti.id = id_posledniho_logu
ORDER BY nazev
SQL
);
foreach ($quickReporty as $r) {
    $pouziti = $pouzitiReportu($r);
    $kontext = [
        'id'                         => $r['id'],
        'nazev'                      => $r['nazev'],
        'jmeno_posledniho_uzivatele' => $pouziti['jmeno_posledniho_uzivatele'],
        'cas_posledniho_pouziti'     => $pouziti['cas_posledniho_pouziti'],
        'pocet_pouziti'              => $pouziti['pocet_pouziti'],
    ];
    $t->assign($kontext);
    $t->parse('reporty.quick');
}

$t->parse('reporty');
$t->out('reporty');

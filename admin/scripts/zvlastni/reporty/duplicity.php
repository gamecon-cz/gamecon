<?php
require __DIR__ . '/sdilene-hlavicky.php';

$data = dbFetchPairs(<<<SQL
SELECT a.id_uzivatele, b.id_uzivatele
FROM uzivatele_hodnoty a
JOIN uzivatele_hodnoty b ON (
    (
        (a.jmeno_uzivatele = b.jmeno_uzivatele AND a.prijmeni_uzivatele = b.prijmeni_uzivatele AND a.jmeno_uzivatele != '')
        AND (a.datum_narozeni = b.datum_narozeni AND a.datum_narozeni != 0 AND a.datum_narozeni != '1970-01-01' AND a.pohlavi = b.pohlavi)
    ) AND a.id_uzivatele < b.id_uzivatele
)
ORDER BY a.id_uzivatele
SQL);
$completeData = [];
foreach ($data as $a => $b) {
    $u1 = Uzivatel::zId($a, true);
    $u2 = Uzivatel::zId($b, true);
    $completeData[] = [
        $u1->id(), $u1->login(), $u1->celeJmeno(), $u1->mail(), $u1->telefon(), $u1->cisloOp(), $u1->datumNarozeni()->formatDatumStandard(), $u1->uliceACp(),
        $u2->id(), $u2->login(), $u2->celeJmeno(), $u2->mail(), $u2->telefon(), $u2->cisloOp(), $u2->datumNarozeni()->formatDatumStandard(), $u2->uliceACp(),
        ];
}
$r = Report::zPoli(['id', 'login', 'jméno', 'mail', 'telefon', 'op', 'narození', 'ulice', 'id', 'login', 'jméno', 'mail', 'telefon', 'op', 'narození', 'ulice',], $completeData);

$r->tFormat(get('format'));

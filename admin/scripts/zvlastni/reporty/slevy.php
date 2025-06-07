<?php
require __DIR__ . '/sdilene-hlavicky.php';

$r = Report::zSql('
SELECT s.id_uzivatele, uu.login_uzivatele, uu.jmeno_uzivatele, uu.prijmeni_uzivatele,
       uu.email1_uzivatele, castka, up.id_uzivatele provedlId, up.login_uzivatele provedlLogin,
       date(s.provedeno) kdy_den, time(s.provedeno) kdy_cas, s.poznamka
FROM slevy s
         JOIN uzivatele_hodnoty uu ON s.id_uzivatele = uu.id_uzivatele
         JOIN uzivatele_hodnoty up ON s.provedl = up.id_uzivatele
ORDER BY kdy_den DESC, kdy_cas DESC
');

$r->tFormat(get('format'));

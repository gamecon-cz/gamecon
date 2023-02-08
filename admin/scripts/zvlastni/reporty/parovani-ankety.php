<?php

use Gamecon\Role\Zidle;

require __DIR__ . '/sdilene-hlavicky.php';

$hodnoty = '';
$znackyText = '';

if (post('znacky')) {
    $znacky = explode("\n", post('znacky'));
    $o = dbQuery('
    SELECT u.*
    FROM uzivatele_hodnoty u
    JOIN platne_zidle_uzivatelu z ON(z.id_uzivatele = u.id_uzivatele AND z.id_zidle=' . Zidle::PRIHLASEN_NA_LETOSNI_GC . ')
  ');
    $i = 0;
    $uzivatele = [];
    while ($r = mysqli_fetch_assoc($o)) {
        $un = new Uzivatel($r);
        $pohlavi = $r['pohlavi'] === 'f' ? 'žena' : 'muž';
        $uzivatele[$r['id_uzivatele']][] = $pohlavi;
        $uzivatele[$r['id_uzivatele']][] = $un->vek();
        //$uzivatele[$r['id_uzivatele']][]=$un->mail();
    }
    foreach ($znacky as $znacka) {
        if (!preg_match('@\d+@', $znacka)) {
            continue;
        }
        $id = bcdiv(hexdec($znacka), 971);
        if ($id && isset($uzivatele[$id])) {
            $hodnoty .= implode("\t", $uzivatele[$id]) . "\n";
        } else {
            $hodnoty .= "\n";
        }
        $znackyText .= $znacka . "\n";
    }
}

?>

<h1>Údaje k doplnění anketní tabulky</h1>
<p>Do levého sloupce zkopírujte sloupec s značkami (bez hlavičkové buňky). V pravém se pak objeví údaje, možno ctrl+c
    ctrl+v zpět do godoc tabulky. S požadavky na rozšíření o další údaje mě kontaktujte klidně.</p>

<form method="post">
    <textarea style="width:100px;height:400px" name="znacky"><?php echo $znackyText ?></textarea>
    <textarea style="width:600px;height:400px" name="hodnoty"><?php echo $hodnoty ?></textarea>
    <br/><input type="submit" value="Načíst">
</form>

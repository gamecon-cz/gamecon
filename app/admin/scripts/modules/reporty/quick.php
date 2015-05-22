<?php

/**
 * nazev: Přidat quick report
 * pravo: 104
 */


$r = null;
$f = new DbFormGc('reporty');
if(get('id')) {
  $r = dbOneLine('select * from reporty where id = $1', [get('id')]);
  $f->loadRow($r);
}
$f->processPost();

if($r) {
  $sql = strtr($r['dotaz'], [
    '{rok}' =>  ROK,
  ]);
  try {
    ob_start();
    Report::zSql($sql)->tHtml(Report::BEZ_STYLU);
    ob_end_flush();
  } catch(DbException $e) {
    ob_end_clean();
    echo 'chyba: '.$e->getMessage();
  }
  echo '<br><br>';
}


?>

<style>
  .dbForm textarea { width: 500px; }
</style>

<?=$f->full()?>
<?php

/**
 * nazev: Přidat quick report
 * pravo: 104
 */

function getConstants() {
  $c = get_defined_constants(true)['user'];
  $keys = array_keys($c);
  array_walk($keys, function(&$k) {
    $k = '{' . str_replace('_', '', lcfirst(ucwords(strtolower($k), '_'))) . '}';
  });
  $c = array_combine($keys, $c);
  return $c;
}

$r = null;
$f = new DbFormGc('reporty');
if(get('id')) {
  $r = dbOneLine('select * from reporty where id = $1', [get('id')]);
  $f->loadRow($r);
}
$f->processPost();

if($r) {
  $sql = strtr($r['dotaz'], getConstants());
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
  .dbForm textarea { width: 500px; font-family: monospace; }
</style>

<?=$f->full()?>

<script>
  // vypntí kontroly pravopisu kvůli vkládání kódu
  $('.dbForm textarea').last().attr('spellcheck', 'false');
</script>

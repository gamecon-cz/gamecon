<?php

/**
 * nazev: Přidat quick report
 * pravo: 104
 */

function quickReportValidation(array $data) {
  if (!$data['dotaz']) {
    throw new \LogicException('Chybi quick report dotaz');
  }
  $dotazKeKontole = $data['dotaz'];
  // bohuzel nam Wedos neumoznuje read-only uzivatele
  if (!preg_match('~(^|\W)(SELECT|SHOW)\W~i', $dotazKeKontole)) {
    throw new \LogicException('Quick report dotaz neobsahuje SELECT ani SHOW');
  }
  $dotazKeKontole = str_ireplace('SHOW CREATE', 'SHOW', $dotazKeKontole); // pro zjednoduseni kontroly, dotaz sam se nemeni
  if (preg_match('~(^|\W)(INSERT|UPDATE|DELETE|DROP|CREATE|MODIFY|RENAME|SET)\W~i', $dotazKeKontole)) {
    throw new \LogicException('Quick report dotaz muze obsahovat jen SELECT a SHOW: ' . $data['dotaz']);
  }
}

$r = null;
$f = new DbFormGc('reporty_quick');
if(get('id')) {
  $r = dbOneLine('select * from reporty_quick where id = $1', [get('id')]);
  $f->loadRow($r);
}
$f->processPost('quickReportValidation');

if($r) {
  $sql = str_ireplace('{ROK}', ROK, $r['dotaz']);
  try {
    ob_start();
    $report = Report::zSql($sql);
    if(get('format')) {
      $BEZ_DEKORACE = true;
      $report->tFormat(get('format'));
      return;
    } else {
      $report->tHtml(Report::BEZ_STYLU);
    }
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

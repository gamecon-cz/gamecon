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
  try {
    dbQuery($data['dotaz']);
  } catch (\Throwable $throwable) {
    throw new \LogicException(
      'Quick report dotaz je chybny: ' . $throwable->getMessage(),
      $throwable->getCode(),
      $throwable
    );
  }
}

$quickReportId = get('id');

$r = null;
$f = new DbFormGc('reporty_quick');
if($quickReportId) {
  $r = dbOneLine('SELECT * FROM reporty_quick WHERE id = $1', [$quickReportId]);
  $f->loadRow($r);
}
$saveResult = $f->processPost('quickReportValidation', false);
if ($saveResult) {
  $savedReportId = $quickReportId;
  if ($saveResult !== true) {
    $newReportId = $saveResult;
    dbQuery(<<<SQL
  INSERT INTO reporty (skript, nazev, format_xlsx, format_html, viditelny)
  VALUES (CONCAT('quick-', $1), (SELECT reporty_quick.nazev FROM reporty_quick WHERE reporty_quick.id = $1), 1, 1, 0)
SQL
      , [$newReportId]
    );
    $savedReportId = $newReportId;
  }
  if(is_ajax()) {
    die(json_encode(['id' => $savedReportId]));
  }
  back('../reporty');
}
?>

<style>
  .dbForm textarea { width: 500px; font-family: monospace; }
</style>

<?=$f->full()?>

<?php if ($quickReportId): ?>
  <div><br><a href="./reporty/quick?id=<?=$quickReportId?>">Vyzkoušet</a></div>
<?php endif; ?>

<script>
  // vypntí kontroly pravopisu kvůli vkládání kódu
  $('.dbForm textarea').last().attr('spellcheck', 'false');
</script>

<?php
$categoriesSourceFile = __DIR__ . '/062_opravene_kategorie_sjednocenych_tagu.csv';
$categoriesHandle = fopen($categoriesSourceFile, 'rb');
if (!$categoriesHandle) {
  throw new RuntimeException('Can not open ' . $categoriesSourceFile);
}

$expectedCategoryHeaders = ['Kategorie', 'Subkategorie', 'Řadící kód'];
$fetchedCategoryHeaders = fgetcsv($categoriesHandle, 0, ',');
if (!$fetchedCategoryHeaders || $fetchedCategoryHeaders !== $expectedCategoryHeaders) {
  fclose($categoriesHandle);
  throw new RuntimeException(
    sprintf(
      'Chybny vstupni soubor %s, v zahlavi chybi sloupce %s a prebyvaji %s',
      $categoriesSourceFile,
      var_export(array_diff($expectedCategoryHeaders, $fetchedCategoryHeaders ?? []), true),
      var_export(array_diff($fetchedCategoryHeaders ?? [], $expectedCategoryHeaders), true)
    )
  );
}
$categories = [];
while ($row = fgetcsv($categoriesHandle, 0, ',')) {
  $categories[] = array_map('trim', $row);
}
fclose($categoriesHandle);

return $categories;
<?php
$fixedTagsSourceFile = __DIR__ . '/../055_sjednocene_tagy.csv';
$fixedTagsHandle = fopen($fixedTagsSourceFile, 'rb');
if (!$fixedTagsHandle) {
  throw new RuntimeException('Can not open ' . $fixedTagsSourceFile);
}

$expectedTagHeaders = ['orig. pořadí', 'id', 'puvodni nazev', 'Kategorie', 'Kategorie - hypotetické', 'opraveny nazev', 'poznamka'];
$fetchedTagHeaders = fgetcsv($fixedTagsHandle, 0, ',');
if (!$fetchedTagHeaders || $fetchedTagHeaders !== $expectedTagHeaders) {
  fclose($fixedTagsHandle);
  throw new RuntimeException(
    sprintf(
      'Chybny vstupni soubor %s, v zahlavi chybi sloupce %s a prebyvaji %s',
      $fixedTagsSourceFile,
      var_export(array_diff($expectedTagHeaders, $fetchedTagHeaders ?? []), true),
      var_export(array_diff($fetchedTagHeaders ?? [], $expectedTagHeaders), true)
    )
  );
}
$fixedTags = [];
while ($row = fgetcsv($fixedTagsHandle, 0, ',')) {
  unset($row[0] /* orig. pořadí */, $row[4] /* Kategorie - hypotetické */);
  $fixedTags[] = array_map('trim', $row);
}
fclose($fixedTagsHandle);

return $fixedTags;

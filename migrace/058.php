<?php
/** @var \Godric\DbMigrations\Migration $this */

// CATEGORIES
$fetchCategories = function (): array {
  $categoriesSourceFile = __DIR__ . '/pomocne/055_kategorie_sjednocenych_tagu.csv';
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
};
$categories = $fetchCategories();
$mainCategories = [];
$parentCategoryName = null;
$subCategories = [];
foreach ($categories as $category) {
  if ($category[0]) { // Kategorie
    $mainCategories[] = $category;
    $parentCategoryName = $category[0];
  } else {
    $subCategories[$parentCategoryName][] = $category;
  }
}
// TAGS
$fetchTags = function (): array {
  $fixedTagsSourceFile = __DIR__ . '/pomocne/055_sjednocene_tagy.csv';
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
};
$tags = $fetchTags();
$removeDiacriticsAndToLower = function (string $value): string {
  $withoutDiacritics = '';
  $specialsReplaced = \str_replace(
    ['̱', '̤', '̩', 'Ə', 'ə', 'ʿ', 'ʾ', 'ʼ',],
    ['', '', '', 'E', 'e', "'", "'", "'",],
    $value
  );
  \preg_match_all('~(?<words>\w*)(?<nonWords>\W*)~u', $specialsReplaced, $matches);
  foreach ($matches['words'] as $index => $word) {
    $wordWithoutDiacritics = \transliterator_transliterate('Any-Latin; Latin-ASCII', $word);
    $withoutDiacritics .= $wordWithoutDiacritics . $matches['nonWords'][$index];
  }
  return strtolower($withoutDiacritics);
};
$checkNameUniqueness = function (array $tags) use ($removeDiacriticsAndToLower) {
  $opraveneNazvy = [];
  $puvodniNazvy = [];
  $duplicitniPuvodniNazvy = [];
  $duplicitniNazvy = [];
  $opraveneNazvyBezDiakritiky = [];
  $duplicitniNazvyBezDiakritiky = [];
  $opraveneNazvyBezDiakritikyAMezer = [];
  $duplicitniNazvyBezDiakritikyAMezer = [];
  foreach ($tags as $tag) {
    $opravenyNazev = $tag[5];
    if ($opravenyNazev === '-') {
      continue; // convinced for deletion
    }
    $puvodniNazev = $tag[2];
    $predchoziPuvodniNazev = $puvodniNazvy[$puvodniNazev][2] ?? false;
    if ($predchoziPuvodniNazev) {
      $duplicitniPuvodniNazvy[] = $puvodniNazev;
      continue;
    }
    $puvodniNazvy[$puvodniNazev] = $tag;
    $predchoziKategorie = $opraveneNazvy[$opravenyNazev][3] ?? false;
    $kategorie = $tag[3];
    if ($predchoziKategorie !== false && $kategorie !== $predchoziKategorie) {
      $duplicitniNazvy[] = "{$opravenyNazev} (s kategorii '{$kategorie}' proti predchozi '{$predchoziKategorie}')";
      continue;
    }
    $opraveneNazvy[$opravenyNazev] = $tag;
    $opravenyNazevBezDiakritiky = $removeDiacriticsAndToLower($opravenyNazev);
    $predchoziNazevStejnyBezDiakritiky = $opraveneNazvyBezDiakritiky[$opravenyNazevBezDiakritiky][5] ?? false;
    if ($predchoziNazevStejnyBezDiakritiky && $predchoziNazevStejnyBezDiakritiky !== $opravenyNazev) {
      $duplicitniNazvyBezDiakritiky[] = "'{$opravenyNazevBezDiakritiky}' ('{$opravenyNazev}' proti predchozimu nazvu '{$predchoziNazevStejnyBezDiakritiky}')";
      continue;
    }
    $kategorieBezDiakritiky = $removeDiacriticsAndToLower($kategorie);
    $predchoziKategorieBezDiakritiky = $opraveneNazvyBezDiakritiky[$opravenyNazevBezDiakritiky]['kategorie_bez_diakritiky'] ?? false;
    if ($predchoziKategorieBezDiakritiky !== false && $kategorieBezDiakritiky !== $predchoziKategorieBezDiakritiky) {
      $duplicitniNazvyBezDiakritiky[] = "'{$opravenyNazevBezDiakritiky}' ('{$opravenyNazev}' s kategorii '{$kategorie}' proti predchozi '{$predchoziKategorie}')";
      continue;
    }
    $opraveneNazvyBezDiakritiky[$opravenyNazevBezDiakritiky] = $tag;
    $opraveneNazvyBezDiakritiky[$opravenyNazevBezDiakritiky]['kategorie_bez_diakritiky'] = $kategorieBezDiakritiky;
    $opravenyNazevBezDiakritikyAMezer = preg_replace('~\s~', '', $opravenyNazevBezDiakritiky);
    $predchoziNazevStejnyBezDiakritikyAMezer = $opraveneNazvyBezDiakritikyAMezer[$opravenyNazevBezDiakritikyAMezer][5] ?? false;
    if ($predchoziNazevStejnyBezDiakritikyAMezer && $predchoziNazevStejnyBezDiakritikyAMezer !== $opravenyNazev) {
      $duplicitniNazvyBezDiakritikyAMezer[] = "'{$opravenyNazevBezDiakritikyAMezer}' ('{$opravenyNazev}' proti predchozimu nazvu '{$predchoziNazevStejnyBezDiakritikyAMezer}')";
      continue;
    }
    $kategorieBezDiakritikyAMezer = preg_replace('~\s~', '', $kategorieBezDiakritiky);
    $predchoziKategorieBezDiakritikyAMezer = $opraveneNazvyBezDiakritikyAMezer[$opravenyNazevBezDiakritikyAMezer]['kategorie_bez_diakritiky_a_mezer'] ?? false;
    if ($predchoziKategorieBezDiakritikyAMezer !== false && $kategorieBezDiakritikyAMezer !== $predchoziKategorieBezDiakritikyAMezer) {
      $duplicitniNazvyBezDiakritikyAMezer[] = "'{$opravenyNazevBezDiakritikyAMezer}' ('{$opravenyNazev}' s kategorii '{$kategorie}' proti predchozi '{$predchoziKategorie}')";
      continue;
    }
    $opraveneNazvyBezDiakritikyAMezer[$opravenyNazevBezDiakritikyAMezer] = $tag;
    $opraveneNazvyBezDiakritikyAMezer[$opravenyNazevBezDiakritikyAMezer]['kategorie_bez_diakritiky_a_mezer'] = $kategorieBezDiakritikyAMezer;
  }
  $errorMessages = [];
  if ($duplicitniNazvy) {
    sort($duplicitniNazvy);
    $errorMessages[] = sprintf('Nektere opravene nazvy jsou vicekrat: %s', implode(', ', $duplicitniNazvy));
  }
  if ($duplicitniPuvodniNazvy) {
    sort($duplicitniPuvodniNazvy);
    $errorMessages[] = sprintf('Nektere puvodni nazvy jsou vicekrat: %s', implode(', ', $duplicitniPuvodniNazvy));
  }
  if ($duplicitniNazvyBezDiakritiky) {
    sort($duplicitniNazvyBezDiakritiky);
    $errorMessages[] = sprintf('Nektere opravene nazvy jsou bez hacku a carek a malymi pismeny stejne: %s', implode(', ', $duplicitniNazvyBezDiakritiky));
  }
  if ($duplicitniNazvyBezDiakritikyAMezer) {
    sort($duplicitniNazvyBezDiakritikyAMezer);
    $errorMessages[] = sprintf('Nektere opravene nazvy jsou bez hacku, carek, bilych znaku a malymi pismeny stejne: %s', implode(', ', $duplicitniNazvyBezDiakritikyAMezer));
  }
  if ($errorMessages) {
    throw new RuntimeException(implode("\n", $errorMessages));
  }
};
$checkNameUniqueness($tags);
// TAGS FOR SQL
$tagsForSql = array_map(
  function (array $row): array {
    if (!$row[1]) {
      $row[1] = null; // turn empty ID (empty string) into null to activate MySQL auto-increment
    }
    return $row;
  },
  $tags
);
// has to move tags without ID to end to avoid conflict of auto-generated ID with an existing ID, if auto-generated were inserted first
usort($tagsForSql, function (array $someRow, array $anotherRow) {
  $someId = $someRow[1];
  $anotherId = $anotherRow[1];
  if ($someId && $anotherId) {
    return $someId <=> $anotherId; // lower first
  }
  if ($someId) {
    return -1; // someId exists and goes first, anotherId is empty and goes last
  }
  if ($anotherId) {
    return 1; // someId is empty and goes last, anotherId exist and goes first
  }
  return strcmp($someRow[5], $anotherRow[5]); // both IDs are empty, just sort them alphabetically by name
});
$intoSqlValues = function (array $values): string {
  return implode(
    ",\n", // ('foo','bar'),('baz','quz')
    array_map(
      function (array $row): string {
        return sprintf(
          '(%s)', // ('foo','bar')
          implode(
            ',', // 'foo','bar'
            array_map(
              function (?string $value): string {
                if ($value === null) {
                  return 'NULL';
                }
                if (preg_match('~^[(].+[)]$~', $value)) {
                  return $value; // some sub-select
                }
                  /** @var \Godric\DbMigrations\Migration $this */
                  return sprintf("'%s'", mysqli_real_escape_string($this->connection, $value));
              },
              $row
            )
          )
        );
      },
      $values
    )
  );
};
$fixedTagsSql = $intoSqlValues($tagsForSql);
$tagsAutoIncrementStart = 0;
foreach ($tags as $tag) {
  $tagsAutoIncrementStart = max($tagsAutoIncrementStart, (int)$tag[1] /* id */);
}
$tagsAutoIncrementStart++; // start after previous last ID to avoid (almost impossible) accidental usage of new record instead of old one
// CATEGORIES FOR SQL
$mainCategoriesForSql = array_map(
  function (array $mainCategory): array {
    $mainCategory[1] = null; // no parent category
    return $mainCategory;
  },
  $mainCategories
);
$mainCategoriesSql = $intoSqlValues($mainCategoriesForSql);
$subCategoriesForSql = [];
foreach ($subCategories as $parentCategoryName => $subCategoriesWithSameParent) {
  $subCategoriesWithSameParentForSql = array_map(
    function (array $subCategory) use ($parentCategoryName): array {
      $subCategory[0] = $subCategory[1]; // sub-category name moved to first position
      $subCategory[1] = sprintf(
        '(SELECT id FROM kategorie_sjednocenych_tagu AS parent_category WHERE nazev = "%s")',
        mysqli_real_escape_string($this->connection, $parentCategoryName)
      ); // parent category ID
      return $subCategory;
    },
    $subCategoriesWithSameParent
  );
  foreach ($subCategoriesWithSameParentForSql as $subCategoryWithSameParentForSql) {
    $subCategoriesForSql[] = $subCategoryWithSameParentForSql;
  }
}
$subCategoriesSql = $intoSqlValues($subCategoriesForSql);
// SQL INSERT
$query = <<<SQL
CREATE TEMPORARY TABLE sjednocene_tagy_temp LIKE tagy;
ALTER TABLE sjednocene_tagy_temp ADD COLUMN nazev_kategorie VARCHAR(128), ADD COLUMN opraveny_nazev VARCHAR(128), ADD COLUMN poznamka TEXT;
INSERT INTO sjednocene_tagy_temp(id, nazev, nazev_kategorie, opraveny_nazev, poznamka) VALUES {$fixedTagsSql};
ALTER TABLE sjednocene_tagy_temp ADD INDEX (nazev_kategorie), ADD INDEX (opraveny_nazev);
CREATE TABLE IF NOT EXISTS kategorie_sjednocenych_tagu(
    id INT UNSIGNED NOT NULL UNIQUE AUTO_INCREMENT,
    nazev VARCHAR(128) PRIMARY KEY,
    id_hlavni_kategorie INT UNSIGNED,
    poradi INT UNSIGNED NOT NULL,
    FOREIGN KEY (id_hlavni_kategorie) REFERENCES kategorie_sjednocenych_tagu(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
INSERT INTO kategorie_sjednocenych_tagu(nazev, id_hlavni_kategorie, poradi)
VALUES {$mainCategoriesSql};
INSERT INTO kategorie_sjednocenych_tagu(nazev, id_hlavni_kategorie, poradi)
VALUES {$subCategoriesSql};
CREATE TABLE IF NOT EXISTS sjednocene_tagy (
    id INT UNSIGNED NOT NULL UNIQUE AUTO_INCREMENT,
    id_kategorie_tagu INT UNSIGNED NULL,
    nazev VARCHAR(128) PRIMARY KEY,
    poznamka TEXT NOT NULL DEFAULT '',
    FOREIGN KEY FK_kategorie_tagu(id_kategorie_tagu) REFERENCES kategorie_sjednocenych_tagu(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
ALTER TABLE sjednocene_tagy AUTO_INCREMENT={$tagsAutoIncrementStart};
INSERT /* intentionally not IGNORE to detect invalid input data, see bellow */ INTO sjednocene_tagy(id, id_kategorie_tagu, nazev, poznamka)
SELECT sjednocene_tagy_temp.id, kategorie_sjednocenych_tagu.id, sjednocene_tagy_temp.opraveny_nazev, GROUP_CONCAT(DISTINCT sjednocene_tagy_temp.poznamka SEPARATOR '; ')
FROM sjednocene_tagy_temp
JOIN kategorie_sjednocenych_tagu ON kategorie_sjednocenych_tagu.nazev = sjednocene_tagy_temp.nazev_kategorie
WHERE sjednocene_tagy_temp.opraveny_nazev != '-' -- strange records convinced for deletion
GROUP BY sjednocene_tagy_temp.opraveny_nazev, kategorie_sjednocenych_tagu.id; -- intentionally grouped also by kategorie_sjednocenych_tagu.id to get fatal in case of duplicated opraveny_nazev but different kategorie_sjednocenych_tagu.id => logic error in source data
CREATE TABLE IF NOT EXISTS akce_sjednocene_tagy LIKE akce_tagy;
INSERT IGNORE INTO akce_sjednocene_tagy(id_akce, id_tagu)
SELECT akce_tagy.id_akce, sjednocene_tagy.id
FROM akce_tagy
INNER JOIN sjednocene_tagy_temp ON sjednocene_tagy_temp.id = akce_tagy.id_tagu
INNER JOIN sjednocene_tagy ON sjednocene_tagy.nazev = sjednocene_tagy_temp.opraveny_nazev;
DROP TEMPORARY TABLE sjednocene_tagy_temp;
SQL;
try {
  /** @var \Godric\DbMigrations\Migration $this */
  $this->q($query);
} catch (\Exception $exception) {
  throw new RuntimeException(
    sprintf("Migration %s failed: '%s'. Check it: \n%s", basename(__FILE__, '.php'), $exception->getMessage(), $query),
    $exception->getCode(),
    $exception
  );
}

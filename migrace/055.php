<?php
$fetchTags = function (): array {
  $fixedTagsSourceFile = __DIR__ . '/pomocne/055_sjednocene_tagy.csv';
  $fixedTagsHandle = fopen($fixedTagsSourceFile, 'rb');
  if (!$fixedTagsHandle) {
    throw new RuntimeException('Can not open ' . $fixedTagsSourceFile);
  }

  $expectedHeader = ['orig. pořadí', 'id', 'puvodni nazev', 'Kategorie', 'Kategorie - hypotetické', 'opraveny nazev', 'poznamka'];
  $fetchedHeaders = fgetcsv($fixedTagsHandle, 0, ';');

  if (!$fetchedHeaders || $fetchedHeaders !== $expectedHeader) {
    fclose($fixedTagsHandle);
    throw new RuntimeException("Corrupted $fixedTagsSourceFile, header does not match: " . array_diff($expectedHeader, $fetchedHeaders ?? []));
  }

  $fixedTags = [];
  while ($row = fgetcsv($fixedTagsHandle, 0, ';')) {
    unset($row[0] /* orig. pořadí */, $row[4] /* Kategorie - hypotetické */);
    $fixedTags[] = $row;
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

$fixedTagsSql = implode(
  ',', // ('foo','bar'),('baz','quz')
  array_map(
    function (array $row) {
      return sprintf(
        '(%s)', // ('foo','bar')
        implode(
          ',', // 'foo','bar'
          array_map(
            function (string $value) {
              return "'" . mysqli_real_escape_string($this->db, $value) . "'"; // 'foo'
            },
            $row
          )
        )
      );
    },
    $tags
  )
);

$autoIncrementStart = 0;
foreach ($tags as $tag) {
  $autoIncrementStart = max($autoIncrementStart, (int)$tag[1] /* id */);
}
$autoIncrementStart++; // start after previous last ID to avoid (almost impossible) accidental usage of new record instead of old one

/** @var \Godric\DbMigrations\Migration $this */
$this->q(<<<SQL
CREATE TEMPORARY TABLE sjednocene_tagy_temp LIKE tagy;
ALTER TABLE sjednocene_tagy_temp ADD COLUMN kategorie VARCHAR(128), ADD COLUMN opraveny_nazev VARCHAR(128), ADD COLUMN poznamka TEXT;
INSERT INTO sjednocene_tagy_temp(id, nazev, kategorie, opraveny_nazev, poznamka) VALUES {$fixedTagsSql};
ALTER TABLE sjednocene_tagy_temp ADD INDEX (kategorie), ADD INDEX (opraveny_nazev);

CREATE TABLE IF NOT EXISTS kategorie_tagu(
    id INT UNSIGNED NOT NULL UNIQUE AUTO_INCREMENT,
    kategorie VARCHAR(128) PRIMARY KEY
);
INSERT IGNORE INTO kategorie_tagu(kategorie) SELECT kategorie FROM sjednocene_tagy_temp;

CREATE TABLE IF NOT EXISTS sjednocene_tagy (
    id INT UNSIGNED NOT NULL UNIQUE AUTO_INCREMENT,
    id_kategorie_tagu INT UNSIGNED NULL,
    nazev VARCHAR(128) PRIMARY KEY,
    poznamka TEXT,
    FOREIGN KEY FK_kategorie_tagu(id_kategorie_tagu) REFERENCES kategorie_tagu(id)
);
ALTER TABLE sjednocene_tagy AUTO_INCREMENT={$autoIncrementStart};
INSERT /* intentionally not IGNORE to detect invalid input data, see bellow */ INTO sjednocene_tagy(id_kategorie_tagu, nazev, poznamka)
SELECT kategorie_tagu.id, sjednocene_tagy_temp.opraveny_nazev, GROUP_CONCAT(DISTINCT sjednocene_tagy_temp.poznamka SEPARATOR '; ')
FROM sjednocene_tagy_temp
JOIN kategorie_tagu ON kategorie_tagu.kategorie = sjednocene_tagy_temp.kategorie
WHERE sjednocene_tagy_temp.opraveny_nazev != '-' -- strange records convinced for deletion
GROUP BY sjednocene_tagy_temp.opraveny_nazev, kategorie_tagu.id; -- intentionally grouped also by kategorie_tagu.id to get fatal in case of duplicated opraveny_nazev but different kategorie_tagu.id => logic error in source data

DROP TEMPORARY TABLE sjednocene_tagy_temp;
SQL
);

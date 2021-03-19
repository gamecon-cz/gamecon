<?php
return static function(array $tags) {
  $removeDiacriticsAndToLower = require __DIR__ . '/removeDiacriticsAndToLowerFunction.php';

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
<?php
return static function(string $value): string {
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
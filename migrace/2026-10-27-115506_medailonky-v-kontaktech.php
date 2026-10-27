<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Medailonky orgů na stránce Kontakty měly vlastní styl v obsahu stránky, jiný než
// medailonky Pánů jeskyně a Dungeon Masterů. Teď je všechny kreslí sdílený
// _spolecne/medailonek.less, takže tady se přepíše jen značkování medailonků a ze
// <style> stránky zmizí pravidla, která na ně mířila. Texty, fotky a kontakty
// zůstávají, jak je někdo v adminu napsal.
//
// Převádí se všechno, nebo nic: kdyby někdo stránku mezitím přestavěl a některý
// medailonek by vzoru neodpovídal, zůstal by se starým značkováním bez stylu.
// Stránka pak zůstane, jak je, a převést ji jde ručně podle vzoru v medailonek.less.

$stranka = $this->q(<<<'SQL'
SELECT id_stranky, obsah
FROM stranky
WHERE url_stranky = 'kontakty'
SQL,
)->fetch_assoc();
if (!$stranka) {
    return;
}
$obsah = $stranka['obsah'];

$puvodnichMedailonku = preg_match_all('~<article class="\s*org\s*">~', $obsah);
if ($puvodnichMedailonku === 0) {
    return; // už převedeno
}

// medailonky Rady měly vlastní obal, ostatní sekce žádný; obalí se níž všechny stejně
$obsah = preg_replace('~<div class="orgove">(.*?)</div>(\s*</section>)~s', '$1$2', $obsah);

// stránku edituje člověk v adminu, tak ať je značkování odsazené jednotně
$odsad = static fn(string $html, int $mezer): string => preg_replace(
    '~^[ \t]*(?=\S)~m',
    str_repeat(' ', $mezer),
    preg_replace('~^[ \t]+$~m', '', trim($html)),
);

$obsah = preg_replace_callback(
    '~\s*(?<komentar><!--(?:(?!-->).)*-->)?\s*'
    . '<article class="\s*org\s*">\s*'
    . '<figure class="photo">\s*<img src="(?<fotka>[^"]+)"\s*/?>\s*</figure>\s*'
    . '<div class="[^"]*\bdescription\b[^"]*">\s*<h3 class="jmeno">(?<jmeno>.*?)</h3>(?<popis>.*?)</div>\s*'
    . '<details[^>]*>\s*<summary[^>]*>(?<shrnuti>.*?)</summary>\s*<article>(?<text>.*?)</article>\s*</details>\s*'
    . '</article>~su',
    static function (array $medailonek) use ($odsad): string {
        $komentar = $medailonek['komentar'] !== ''
            ? "        {$medailonek['komentar']}\n"
            : '';
        $popis    = $odsad(preg_replace('~<p>\s+~u', '<p>', $medailonek['popis']), 16);
        $text     = $odsad($medailonek['text'], 20);

        return "\n\n" . $komentar . <<<HTML
        <article class="medailonek">
            <img class="medailonek_fotka" src="{$medailonek['fotka']}" alt="">
            <div class="medailonek_popis">
                <h3 class="medailonek_jmeno">{$medailonek['jmeno']}</h3>
{$popis}
            </div>
            <details class="medailonek_vice">
                <summary>{$medailonek['shrnuti']}</summary>
                <div class="medailonek_text">
{$text}
                </div>
            </details>
        </article>
HTML;
    },
    $obsah,
    -1,
    $prevedenychMedailonku,
);
if ($prevedenychMedailonku !== $puvodnichMedailonku) {
    return;
}

// za sebou jdoucí medailonky jedné sekce do mřížky
$obsah = preg_replace_callback(
    '~(?:\n\n        (?:<!--(?:(?!-->).)*-->\n        )?<article class="medailonek">.*?\n        </article>)+~s',
    static fn(array $medailonky): string => "\n    <div class=\"medailonky\">\n"
        . substr($medailonky[0], 2)
        . "\n    </div>",
    $obsah,
);

$obsah = preg_replace_callback(
    '~<style>.*?</style>~s',
    static function (array $styl): string {
        $styl = $styl[0];
        foreach ([
                     '.org', '.org + .org', '.org:has(details[open])',
                     '.photo', '.photo > img', '.jmeno',
                     '.description', '.description > h3', '.description > p',
                     '.o_sobe', '.bez_mezery',
                     'details', 'details > summary', 'details[open]',
                 ] as $selektor) {
            $styl = preg_replace('~^[ \t]*' . preg_quote($selektor, '~') . '\s*\{[^{}]*\}[ \t]*\R~m', '', $styl);
        }
        // @media, ve kterých nic nezůstalo
        $styl = preg_replace('~^[ \t]*@media[^{]*\{\s*\}[ \t]*\R~m', '', $styl);

        $styl = preg_replace('~\R(?:[ \t]*\R){2,}~', "\n\n", $styl);

        return preg_replace('~\R[ \t]*\R([ \t]*\})~', "\n$1", $styl);
    },
    $obsah,
);

$obsahSql = "'" . $this->connection->real_escape_string($obsah) . "'";
$this->q(<<<SQL
UPDATE stranky
SET obsah = {$obsahSql}
WHERE id_stranky = {$stranka['id_stranky']}
SQL,
);

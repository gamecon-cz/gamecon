<?php
/**
 * @var Uzivatel $u
 * @var Uzivatel|null $uPracovni
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

if (post('uzivatelProPripsaniSlevy')) {
    $uzivatel = Uzivatel::zId(post('uzivatelProPripsaniSlevy'));
    if (!$uzivatel) {
        chyba(sprintf('Uživatel %d neexistuje.', post('uzivatelProPripsaniSlevy')));
    }
    if (!post('sleva')) {
        chyba('Zadej slevu.');
    }
    if (!$uzivatel->gcPrihlasen()) {
        chyba(sprintf('Uživatel %s není přihlášen na GameCon.', $uzivatel->jmenoNick()));
    }
    $pripsanaSleva   = $uzivatel->finance()->pripisSlevu(
        post('sleva'),
        post('poznamkaKUzivateliProPripsaniSlevy'),
        $u,
    );
    $numberFormatter = NumberFormatter::create('cs', NumberFormatter::PATTERN_DECIMAL);
    oznameni(
        sprintf(
            'Sleva %s připsána k uživateli %s.',
            $numberFormatter->formatCurrency(
                $pripsanaSleva,
                'CZK',
            ),
            $uzivatel->jmenoNick(),
        ),
    );
}

<?php
/**
 * Vyplnění prezence a uzavření aktivity online.
 */

/** @var Uzivatel $u */
/** @var Uzivatel|null $uPracovni */

$ucastnikTemplate = new XTemplate(basename(__DIR__ . '/online-prezence-ucastnik.xtpl'));

$sestavHmlUcastnikaAktivity = function (
    Uzivatel $prihlasenyUzivatel,
    Aktivita $aktivita,
    bool     $naAktivite
) use ($ucastnikTemplate): string {
    $ucastnikTemplate->assign('u', $prihlasenyUzivatel);
    $ucastnikTemplate->assign('a', $aktivita);

    $ucastnikTemplate->assign('checked', $naAktivite ? 'checked' : '');
    $ucastnikTemplate->parse('ucastnik.checkbox');

    $ucastnikTemplate->parse('ucastnik.' . ($prihlasenyUzivatel->gcPritomen() ? 'pritomen' : 'nepritomen'));
    $ucastnikTemplate->parse('ucastnik');
    return $ucastnikTemplate->text('ucastnik');
};

if (post('ajax') || get('ajax')) {
    if (post('akce') === 'uzavrit') {
        $aktivita = Aktivita::zId(post('id'));
        if (!$aktivita) {
            header("HTTP/1.1 400 Bad Request");
            header('Content-Type: application/json');
            echo json_encode([
                'errors' => ['Chybné ID aktivity'],
            ], JSON_THROW_ON_ERROR);
            exit;
        }
        header('Content-Type: application/json');
        echo json_encode([], JSON_THROW_ON_ERROR);  // TODO da se vubec aktivita zavrit?
        exit;
    }

    if (post('akce') === 'zmenitUcastnika') {
        $ucastnik = Uzivatel::zId(post('idUzivatele'));
        $aktivita = Aktivita::zId(post('idAktivity'));
        $dorazil = post('dorazil');
        if (!$ucastnik || !$aktivita || $dorazil === null) {
            header("HTTP/1.1 400 Bad Request");
            header('Content-Type: application/json');
            echo json_encode([
                'errors' => ['Chybné ID účastníka nebo aktivity nebo chybejici priznak zda dorazil'],
            ], JSON_THROW_ON_ERROR);
            exit;
        }
        if ($dorazil) {
            $aktivita->ulozPrezenciDorazivsiho($ucastnik);
        } else {
            $aktivita->zrusDorazeni($ucastnik);
        }
        $aktivita->refresh();

        header('Content-Type: application/json');
        echo json_encode(['prihlasen' => $aktivita->dorazilIJakoNahradnik($ucastnik)], JSON_THROW_ON_ERROR);
        exit;
    }

    if (post('prezenceAktivity')) {
        $aktivita = Aktivita::zId(post('prezenceAktivity'));
        $dorazili = Uzivatel::zIds(array_keys(post('dorazil') ?: []));
        $aktivita->ulozPrezenci($dorazili);

        header('Content-Type: application/json');
        echo json_encode([
            'aktivita' => $aktivita->rawDb(),
            'doazili' => $dorazili,
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    if (get('omnibox')) {
        $idAktivityProOmnibox = get('id-aktivity');
        $aktivita = Aktivita::zId($idAktivityProOmnibox); // TODO remove already added users
        if (!$aktivita) {
            header("HTTP/1.1 400 Bad Request");
            header('Content-Type: application/json');
            echo json_encode([
                'errors' => ['Chybné ID aktivity ' . var_export($idAktivityProOmnibox, true)],
            ], JSON_THROW_ON_ERROR);
            exit;
        }
        $omniboxData = omnibox(
            get('term') ?: '',
            true,
            get('dataVOdpovedi') ?: [],
            get('labelSlozenZ'),
            array_map(
                static function (Uzivatel $prihlaseny) {
                    return (int)$prihlaseny->id();
                }, $aktivita->prihlaseni()
            ),
            true
        );
        foreach ($omniboxData as &$prihlasenyUzivatelOmnibox) {
            $prihlasenyUzivatel = Uzivatel::zId($prihlasenyUzivatelOmnibox['value']);
            $ucastnikHtml = $sestavHmlUcastnikaAktivity($prihlasenyUzivatel, $aktivita, true);
            $prihlasenyUzivatelOmnibox['html'] = $ucastnikHtml;
        }
        unset($prihlasenyUzivatelOmnibox);

        header('Content-Type: application/json');
        echo json_encode($omniboxData, JSON_THROW_ON_ERROR);
        exit;
    }

    header("HTTP/1.1 400 Bad Request");
    header('Content-Type: application/json');
    echo json_encode([
        'errors' => ['Neznámý požadavek'],
    ], JSON_THROW_ON_ERROR);
    exit;
}

$t = new XTemplate(basename(__DIR__ . '/online-prezence.xtpl'));

require __DIR__ . '/../modules/prezence/_casy.php'; // vhackování vybírátka času

$zacatek = null; // bude nastaven přes referenci v nasledujici funkci
$t->assign('casy', _casy($zacatek, true));

$aktivity = $zacatek
    ? Aktivita::zRozmezi($zacatek, $zacatek)
    : [];

if (!$zacatek) {
    $t->parse('onlinePrezence.nevybrano');
} else if (count($aktivity) === 0) {
    $t->parse('onlinePrezence.zadnaAktivita');
}
$t->assign('omniboxUrl', basename(__FILE__, '.php'));
$t->assign('prezenceUrl', basename(__DIR__ . '/../modules/prezence/prezence.php', '.php'));

$ucastnikTemplate = new XTemplate(basename(__DIR__ . '/online-prezence-ucastnik.xtpl'));

foreach ($aktivity as $aktivita) {
    $vyplnena = $aktivita->vyplnenaPrezence();
    $zamcena = $aktivita->zamcena();
    $t->assign('a', $aktivita);

    foreach ($aktivita->prihlaseni() as $prihlasenyUzivatel) {
        $ucastnikHtml = $sestavHmlUcastnikaAktivity($prihlasenyUzivatel, $aktivita, false);
        $t->assign('ucastnikHtml', $ucastnikHtml);
        $t->parse('onlinePrezence.aktivita.form.ucastnik');
    }
    if ($zamcena && (!$vyplnena || $u->maPravo(\Gamecon\Pravo::ZMENA_HISTORIE_AKTIVIT))) {
        if ($vyplnena && $u->maPravo(\Gamecon\Pravo::ZMENA_HISTORIE_AKTIVIT)) {
            $t->parse('onlinePrezence.aktivita.form.submit.pozorVyplnena');
        }
    }

    $t->parse('onlinePrezence.aktivita.form.submit');

    $t->assign('nadpis', implode(' – ', array_filter([$aktivita->nazev(), $aktivita->orgJmena(), $aktivita->lokace()])));
    $t->parse('onlinePrezence.aktivita.form');

    $t->parse('onlinePrezence.aktivita');
}

$t->parse('onlinePrezence');
$t->out('onlinePrezence');

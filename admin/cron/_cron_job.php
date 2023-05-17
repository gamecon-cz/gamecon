<?php
/** @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$job   ??= null;
$znovu = filter_var(get('znovu'), FILTER_VALIDATE_BOOL)
    && ($systemoveNastaveni->jsmeNaLocale()
        || (defined('TEST_HROMADNE_AKCE_AKTIVIT_CRONEM_PORAD') && TEST_HROMADNE_AKCE_AKTIVIT_CRONEM_PORAD)
    );

// Pozor, pořadí je důležité - úkoly na prvním místě jsou ty, co mají přednost (nikoli časovou, ale významovou) před ostatními
if (in_array($job, ['odhlaseni_neplaticu', 'aktivity_hromadne'])) {
    require __DIR__ . '/jobs/odhlaseni_neplaticu.php';
    if ($job === 'odhlaseni_neplaticu') {
        return;
    }
}

if (in_array($job, ['aktivace_aktivit', 'aktivity_hromadne'])) {
    require __DIR__ . '/jobs/aktivace_aktivit.php';
    if ($job === 'aktivace_aktivit') {
        return;
    }
}

if (in_array($job, ['mail_cfo_brzke_odhlaseni_neplaticu', 'aktivity_hromadne'])) {
    require __DIR__ . '/jobs/mail_cfo_brzke_odhlaseni_neplaticu.php';
    if ($job === 'mail_cfo_brzke_odhlaseni_neplaticu') {
        return;
    }
}

if (in_array($job, ['mail_varovani_neplaticum_o_brzkem_odhlaseni', 'aktivity_hromadne'])) {
    require __DIR__ . '/jobs/mail_varovani_neplaticum_o_brzkem_odhlaseni.php';
    if ($job === 'mail_varovani_neplaticum_o_brzkem_odhlaseni') {
        return;
    }
}

if (in_array($job, ['mail_cfo_nesparovane_platby', 'aktivity_hromadne'])) {
    require __DIR__ . '/jobs/mail_cfo_nesparovane_platby.php';
    if ($job === 'mail_cfo_nesparovane_platby') {
        return;
    }
}

if ($job !== 'aktivity_hromadne') {
    throw new \RuntimeException(sprintf("Invalid job '%s'", $job));
}

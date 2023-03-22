<?php
$job   ??= null;
$znovu = filter_var(get('znovu'), FILTER_VALIDATE_BOOL)
    && defined('TEST_HROMADNE_AKCE_AKTIVIT_CRONEM_PORAD')
    && TEST_HROMADNE_AKCE_AKTIVIT_CRONEM_PORAD;

// Pozor, pořadí je důležité - úkoly na prvním místě jsou ty, co mají být puštěny před ostatními
if (in_array($job, ['odhlaseni_neplaticu', 'aktivity_hromadne'])) {
    require __DIR__ . '/odhlaseni_neplaticu.php';
    if ($job === 'odhlaseni_neplaticu') {
        return;
    }
}
if (in_array($job, ['aktivace_aktivit', 'aktivity_hromadne'])) {
    require __DIR__ . '/aktivace_aktivit.php';
    if ($job === 'aktivace_aktivit') {
        return;
    }
}
if ($job === 'mail_bfgr') {
    require __DIR__ . '/mail_bfgr.php';
    return;
}
if ($job !== 'aktivity_hromadne') {
    throw new \RuntimeException(sprintf("Invalid job '%s'", $job));
}

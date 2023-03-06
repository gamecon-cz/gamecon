<?php
$job ??= null;

if (in_array($job, ['odhlaseni_neplaticu', 'aktivity_hromadne'])) {
    require __DIR__ . '/cron/odhlaseni_neplaticu.php';
    if ($job === 'odhlaseni_neplaticu') {
        return;
    }
}
if (in_array($job, ['aktivace_aktivit', 'aktivity_hromadne'])) {
    require __DIR__ . '/cron/aktivace_aktivit.php';
    if ($job === 'aktivace_aktivit') {
        return;
    }
}
if ($job !== 'aktivity_hromadne') {
    throw new \RuntimeException(sprintf("Invalid job '%s'", $job));
}

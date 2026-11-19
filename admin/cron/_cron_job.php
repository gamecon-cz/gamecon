<?php
/** @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$job ??= null;
$znovu = filter_var(get('znovu'), FILTER_VALIDATE_BOOL)
    && ($systemoveNastaveni->jsmeNaLocale()
        || (defined('TEST_HROMADNE_AKCE_AKTIVIT_CRONEM_PORAD') && TEST_HROMADNE_AKCE_AKTIVIT_CRONEM_PORAD)
    );

try {
    // Pozor, pořadí je důležité - úkoly na prvním místě jsou ty, co mají přednost (nikoli časovou, ale významovou) před ostatními
    if (in_array($job, ['odhlaseni_neplaticu', 'aktivity_hromadne'])) {
        require __DIR__ . '/jobs/odhlaseni_neplaticu.php';
        if ($job === 'odhlaseni_neplaticu') {
            return;
        }
    }

    // Aktivace aktivit musí následovat hned po odhlášení neplatičů, aby pracovala
    // s dokončeným odhlášením. Zároveň zůstává uvnitř try - když odhlášení spadne,
    // catch aktivaci přeskočí (nesmí aktivovat nad polovičatě odhlášeným stavem).
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
} catch (Throwable $e) {
    (new \Gamecon\Cron\CronErrorNotifier($systemoveNastaveni))
        ->ohlas($e, 'odhlašování neplatičů / aktivace aktivit / maily neplatičům');
}

// Každý job zvlášť v try, aby pád jednoho přišel mailem (CronErrorNotifier), ne jen do logu
// cronu, a nezastavil joby za ním. Nový job sem patří taky do try, hlídá to CronJobObsluhaChybTest.
if (in_array($job, ['mazani_nepripravenych_tymu', 'aktivity_hromadne'])) {
    try {
        require __DIR__ . '/jobs/mazani_nepripravenych_tymu.php';
    } catch (Throwable $e) {
        (new \Gamecon\Cron\CronErrorNotifier($systemoveNastaveni))
            ->ohlas($e, 'mazání nepřipravených týmů');
    }

    if ($job === 'mazani_nepripravenych_tymu') {
        return;
    }
}

if ($job === 'archiv_prednasek_po_gc') {
    try {
        require __DIR__ . '/jobs/archiv_prednasek_po_gc.php';
    } catch (Throwable $e) {
        (new \Gamecon\Cron\CronErrorNotifier($systemoveNastaveni))
            ->ohlas($e, 'doplnění archivu přednášek po GC');
    }

    return;
}

if ($job === 'materializace_roli_podle_rocniku') {
    try {
        require __DIR__ . '/jobs/materializace_roli_podle_rocniku.php';
    } catch (Throwable $e) {
        (new \Gamecon\Cron\CronErrorNotifier($systemoveNastaveni))
            ->ohlas($e, 'materializace rolí podle ročníků');
    }

    return;
}

if ($job === 'synchronizace_historickych_plateb') {
    try {
        require __DIR__ . '/jobs/synchronizace_historickych_plateb.php';
    } catch (Throwable $e) {
        (new \Gamecon\Cron\CronErrorNotifier($systemoveNastaveni))
            ->ohlas($e, 'synchronizace historických plateb');
    }

    return;
}

// Měsíční a týdenní upomínka mají každá své okno, pád jedné nesmí zastavit druhou.
if ($job === 'dluznici') {
    try {
        require __DIR__ . '/jobs/mail_dluzniku_mesic.php';
    } catch (Throwable $e) {
        (new \Gamecon\Cron\CronErrorNotifier($systemoveNastaveni))
            ->ohlas($e, 'upomínka dlužníkům měsíc po GC');
    }

    try {
        require __DIR__ . '/jobs/mail_dluzniku_tyden.php';
    } catch (Throwable $e) {
        (new \Gamecon\Cron\CronErrorNotifier($systemoveNastaveni))
            ->ohlas($e, 'upomínka dlužníkům týden po GC');
    }

    return;
}

if ($job === 'promlceni') {
    try {
        require __DIR__ . '/jobs/mail_promlceni_varovani_mesic.php';
    } catch (Throwable $e) {
        (new \Gamecon\Cron\CronErrorNotifier($systemoveNastaveni))
            ->ohlas($e, 'varování o promlčení měsíc před registracemi');
    }

    try {
        require __DIR__ . '/jobs/mail_promlceni_varovani_tyden.php';
    } catch (Throwable $e) {
        (new \Gamecon\Cron\CronErrorNotifier($systemoveNastaveni))
            ->ohlas($e, 'varování o promlčení týden před registracemi');
    }

    try {
        require __DIR__ . '/jobs/promlceni_automaticke.php';
    } catch (Throwable $e) {
        (new \Gamecon\Cron\CronErrorNotifier($systemoveNastaveni))
            ->ohlas($e, 'automatické promlčení zůstatků');
    }

    return;
}

if ($job !== 'aktivity_hromadne') {
    throw new \RuntimeException(sprintf("Invalid job '%s'", $job));
}

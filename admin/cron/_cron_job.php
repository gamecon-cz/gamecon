<?php

use Gamecon\Cron\ZamekJobu;

/** @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$job ??= null;
$znovu = filter_var(get('znovu'), FILTER_VALIDATE_BOOL)
    && ($systemoveNastaveni->jsmeNaLocale()
        || (defined('TEST_HROMADNE_AKCE_AKTIVIT_CRONEM_PORAD') && TEST_HROMADNE_AKCE_AKTIVIT_CRONEM_PORAD)
    );

$jobyNeplaticu = [
    'odhlaseni_neplaticu',
    'aktivace_aktivit',
    'mail_cfo_brzke_odhlaseni_neplaticu',
    'mail_varovani_neplaticum_o_brzkem_odhlaseni',
    'mail_cfo_nesparovane_platby',
    'aktivity_hromadne',
];
if (in_array($job, $jobyNeplaticu, true)) {
    // Tyhle joby se podívají do hromadne_akce_log, jestli už svou práci udělaly, a zapíšou ji tam až na konci,
    // takže druhý běh cronu, který začne dřív, než první skončí, by ji udělal podruhé (viz ZamekJobu).
    // Zámek je jeden pro všechny: aktivace musí pracovat s dokončeným odhlášením, nesmí tedy běžet vedle
    // odhlašování v jiném běhu.
    $zamekNeplaticu = new ZamekJobu('neplatici');
    if ($zamekNeplaticu->zamkni()) {
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
        } finally {
            $zamekNeplaticu->odemkni();
        }
    } else {
        logs('Odhlašování neplatičů, aktivaci aktivit a maily neplatičům a CFO právě zpracovává jiný běh cronu, tento běh je přeskakuje.');
    }

    if ($job !== 'aktivity_hromadne') {
        return;
    }
}

if (in_array($job, ['mazani_nepripravenych_tymu', 'aktivity_hromadne'])) {
    require __DIR__ . '/jobs/mazani_nepripravenych_tymu.php';

    if ($job === 'mazani_nepripravenych_tymu') {
        return;
    }
}


if ($job === 'archiv_prednasek_po_gc') {
    require __DIR__ . '/jobs/archiv_prednasek_po_gc.php';

    return;
}

if ($job === 'materializace_roli_podle_rocniku') {
    require __DIR__ . '/jobs/materializace_roli_podle_rocniku.php';

    return;
}

if ($job === 'synchronizace_historickych_plateb') {
    require __DIR__ . '/jobs/synchronizace_historickych_plateb.php';

    return;
}

if ($job === 'dluznici') {
    require __DIR__ . '/jobs/mail_dluzniku_mesic.php';
    require __DIR__ . '/jobs/mail_dluzniku_tyden.php';

    return;
}

if ($job === 'promlceni') {
    require __DIR__ . '/jobs/mail_promlceni_varovani_mesic.php';
    require __DIR__ . '/jobs/mail_promlceni_varovani_tyden.php';

    // Varování si zamyká PromlceniZustatku samo. Promlčení se taky nejdřív ptá hromadne_akce_log
    // a zapisuje do něj až po rozeslání reportů CFO, takže druhý běh by je rozeslal znovu.
    $zamekPromlceni = new ZamekJobu('promlceni-automaticke');
    if ($zamekPromlceni->zamkni()) {
        try {
            require __DIR__ . '/jobs/promlceni_automaticke.php';
        } finally {
            $zamekPromlceni->odemkni();
        }
    } else {
        logs('Automatické promlčení zůstatků právě provádí jiný běh cronu, tento běh ho přeskakuje.');
    }

    return;
}

if ($job !== 'aktivity_hromadne') {
    throw new \RuntimeException(sprintf("Invalid job '%s'", $job));
}

<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Cron\ZamekJobu;

/**
 * Skutečný běh cronu přes Apache, zatímco první běh (tento test) drží zámek jobu.
 * 17. 7. 2026 spustil cron-job.org `aktivity_hromadne` dvakrát 20 s po sobě a oba běhy poslaly
 * „1. varování“ všem 37 neplatičům.
 *
 * Cron se volá bez parametru `cas`, takže každý job, který se spustí, hned zahlásí, že čas chybí, a skončí.
 * Podle toho se pozná, které joby běžely, aniž by stahovaly platby nebo rozesílaly maily.
 */
class SoubehCronuTest extends AbstractTestWeb
{
    private const string JOB_SPUSTEN = "Chybí čas předaný GET parametrem 'cas'";
    private const string JOBY_PRESKOCENY = 'právě zpracovává jiný běh cronu';
    private const string PROMLCENI_PRESKOCENO = 'právě provádí jiný běh cronu';
    private const int POCET_JOBU_NEPLATICU = 5;
    private const int POCET_JOBU_PROMLCENI = 3;

    /**
     * @test
     */
    public function druhyBehCronuJobNeplaticuNespustiDokudPrvniNeskonci(): void
    {
        $prvniBeh = new ZamekJobu('neplatici');
        self::assertTrue($prvniBeh->zamkni(), 'Test potřebuje zámek, který drží první běh');
        try {
            $druhyBeh = $this->spustCronJob('mail_varovani_neplaticum_o_brzkem_odhlaseni');
        } finally {
            $prvniBeh->odemkni();
        }

        self::assertSame(200, $druhyBeh['status'], $druhyBeh['telo']);
        self::assertStringContainsString(self::JOBY_PRESKOCENY, $druhyBeh['telo']);
        self::assertStringNotContainsString(
            self::JOB_SPUSTEN,
            $druhyBeh['telo'],
            'Druhý běh nesmí job vůbec spustit, jinak by se zeptal na log dřív, než do něj první běh zapíše',
        );

        $behPoSkonceniPrvniho = $this->spustCronJob('mail_varovani_neplaticum_o_brzkem_odhlaseni');

        self::assertSame(200, $behPoSkonceniPrvniho['status'], $behPoSkonceniPrvniho['telo']);
        self::assertStringNotContainsString(self::JOBY_PRESKOCENY, $behPoSkonceniPrvniho['telo']);
        self::assertStringContainsString(
            self::JOB_SPUSTEN,
            $behPoSkonceniPrvniho['telo'],
            'Po skončení prvního běhu se job musí zase spustit',
        );
    }

    /**
     * @test
     */
    public function hromadnyJobPreskociVsechnyJobyNeplaticuAPokracuje(): void
    {
        $prvniBeh = new ZamekJobu('neplatici');
        self::assertTrue($prvniBeh->zamkni(), 'Test potřebuje zámek, který drží první běh');
        try {
            $druhyBeh = $this->spustCronJob('aktivity_hromadne');
        } finally {
            $prvniBeh->odemkni();
        }

        self::assertSame(200, $druhyBeh['status'], $druhyBeh['telo']);
        self::assertStringContainsString(self::JOBY_PRESKOCENY, $druhyBeh['telo']);
        self::assertStringNotContainsString(self::JOB_SPUSTEN, $druhyBeh['telo']);
        self::assertStringNotContainsString(
            'Invalid job',
            $druhyBeh['telo'],
            'Hromadný job musí po přeskočení neplatičů pokračovat dalšími joby, ne skončit chybou',
        );

        $behPoSkonceniPrvniho = $this->spustCronJob('aktivity_hromadne');

        self::assertSame(
            self::POCET_JOBU_NEPLATICU,
            substr_count($behPoSkonceniPrvniho['telo'], self::JOB_SPUSTEN),
            'Bez cizího zámku se musí spustit všechny joby neplatičů, pod zámkem tedy byly všechny: '
            . $behPoSkonceniPrvniho['telo'],
        );
    }

    /**
     * Varování o promlčení si zamyká PromlceniZustatku samo (PromlceniZustatkuTest), tady jde o samotné promlčení.
     *
     * @test
     */
    public function druhyBehCronuPromlceniZustatkuNespustiDokudPrvniNeskonci(): void
    {
        $prvniBeh = new ZamekJobu('promlceni-automaticke');
        self::assertTrue($prvniBeh->zamkni(), 'Test potřebuje zámek, který drží první běh');
        try {
            $druhyBeh = $this->spustCronJob('promlceni');
        } finally {
            $prvniBeh->odemkni();
        }

        self::assertSame(200, $druhyBeh['status'], $druhyBeh['telo']);
        self::assertStringContainsString(self::PROMLCENI_PRESKOCENO, $druhyBeh['telo']);
        self::assertSame(
            self::POCET_JOBU_PROMLCENI - 1,
            substr_count($druhyBeh['telo'], self::JOB_SPUSTEN),
            'Pod cizím zámkem smí běžet jen varování, promlčení ne: ' . $druhyBeh['telo'],
        );

        $behPoSkonceniPrvniho = $this->spustCronJob('promlceni');

        self::assertStringNotContainsString(self::PROMLCENI_PRESKOCENO, $behPoSkonceniPrvniho['telo']);
        self::assertSame(
            self::POCET_JOBU_PROMLCENI,
            substr_count($behPoSkonceniPrvniho['telo'], self::JOB_SPUSTEN),
            'Po skončení prvního běhu se promlčení musí zase spustit: ' . $behPoSkonceniPrvniho['telo'],
        );
    }

    /**
     * @return array{status: int, telo: string}
     */
    private function spustCronJob(string $job): array
    {
        return $this->stahniVerejnouStranku('admin/cron.php?' . http_build_query([
            'job'  => $job,
            'key'  => CRON_KEY,
            'echo' => 1,
        ]));
    }
}

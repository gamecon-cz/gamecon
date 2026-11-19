<?php

declare(strict_types=1);

namespace Gamecon\Tests\Cron;

use Gamecon\Cron\ZamekJobu;
use PHPUnit\Framework\TestCase;

class ZamekJobuTest extends TestCase
{
    /**
     * @test
     */
    public function druhyBehZamekNedostaneDokudHoPrvniNeuvolni()
    {
        $prvniBeh = new ZamekJobu('test-souběh');
        $druhyBeh = new ZamekJobu('test-souběh');

        self::assertTrue($prvniBeh->zamkni(), 'První běh musí zámek dostat');
        try {
            self::assertFalse($druhyBeh->zamkni(), 'Druhý běh nesmí zámek dostat, dokud ho drží první');
        } finally {
            $prvniBeh->odemkni();
        }

        self::assertTrue($druhyBeh->zamkni(), 'Po skončení prvního běhu musí zámek dostat další běh hned');
        $druhyBeh->odemkni();
    }

    /**
     * @test
     */
    public function ruzneJobySeNeblokuji()
    {
        $upominky = new ZamekJobu('test-upominky');
        $promlceni = new ZamekJobu('test-promlceni');

        self::assertTrue($upominky->zamkni());
        try {
            self::assertTrue($promlceni->zamkni(), 'Zámek jednoho jobu nesmí blokovat jiný job');
            $promlceni->odemkni();
        } finally {
            $upominky->odemkni();
        }
    }

    /**
     * @test
     */
    public function zamekSeNedaZamknoutDvakratTymZBehu()
    {
        $zamek = new ZamekJobu('test-dvakrat');
        self::assertTrue($zamek->zamkni());
        try {
            $this->expectException(\LogicException::class);
            $zamek->zamkni();
        } finally {
            $zamek->odemkni();
        }
    }

    /**
     * @test
     */
    public function odemknutiNezamcenehoZamkuNicNedela()
    {
        $zamek = new ZamekJobu('test-nezamceny');

        $zamek->odemkni();

        self::assertTrue($zamek->zamkni(), 'Zámek musí jít zamknout i po odemknutí, které nic nedělalo');
        $zamek->odemkni();
    }
}

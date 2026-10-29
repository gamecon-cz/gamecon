<?php

declare(strict_types=1);

namespace Gamecon\Tests\Web\Motiv;

use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Web\Motiv\MotivWebuEnum;
use Gamecon\Web\Motiv\NaplanovaneMotivyWebuRepository;

class NaplanovaneMotivyWebuRepositoryTest extends AbstractTestDb
{
    public function testMotivPlatiJenVNaplanovanemCase(): void
    {
        $repository = new NaplanovaneMotivyWebuRepository();
        $id = $repository->naplanuj(
            MotivWebuEnum::APRIL,
            new \DateTimeImmutable('2027-04-01 00:00'),
            new \DateTimeImmutable('2027-04-02 00:00'),
            \Uzivatel::SYSTEM,
        );

        self::assertNull($repository->zobrazovany(new \DateTimeImmutable('2027-03-31 23:59')));
        self::assertSame($id, $repository->zobrazovany(new \DateTimeImmutable('2027-04-01 00:00'))?->id);
        self::assertSame(MotivWebuEnum::APRIL, $repository->zobrazovany(new \DateTimeImmutable('2027-04-01 12:00'))?->motiv);
        self::assertNull($repository->zobrazovany(new \DateTimeImmutable('2027-04-02 00:00')));
    }

    public function testPozdejiZacatyMotivPrebijeDrivejsi(): void
    {
        $repository = new NaplanovaneMotivyWebuRepository();
        $repository->naplanuj(
            MotivWebuEnum::APRIL,
            new \DateTimeImmutable('2027-04-01 00:00'),
            new \DateTimeImmutable('2027-04-03 00:00'),
            \Uzivatel::SYSTEM,
        );
        $kratky = $repository->naplanuj(
            MotivWebuEnum::APRIL,
            new \DateTimeImmutable('2027-04-02 10:00'),
            new \DateTimeImmutable('2027-04-02 12:00'),
            \Uzivatel::SYSTEM,
        );

        self::assertSame($kratky, $repository->zobrazovany(new \DateTimeImmutable('2027-04-02 11:00'))?->id);
    }

    public function testUkoncenyMotivZmiziANaplanovanyZustane(): void
    {
        $repository = new NaplanovaneMotivyWebuRepository();
        $zobrazovany = $repository->naplanuj(
            MotivWebuEnum::APRIL,
            new \DateTimeImmutable('2027-04-01 00:00'),
            new \DateTimeImmutable('2027-04-02 00:00'),
            \Uzivatel::SYSTEM,
        );
        $naplanovany = $repository->naplanuj(
            MotivWebuEnum::APRIL,
            new \DateTimeImmutable('2028-04-01 00:00'),
            new \DateTimeImmutable('2028-04-02 00:00'),
            \Uzivatel::SYSTEM,
        );
        $ted = new \DateTimeImmutable('2027-04-01 12:00');

        $repository->ukonci($zobrazovany, $ted, \Uzivatel::SYSTEM);
        $repository->ukonci($naplanovany, $ted, \Uzivatel::SYSTEM);

        self::assertNull($repository->zobrazovany($ted));
        $podleId = [];
        foreach ($repository->vsechny() as $motiv) {
            $podleId[$motiv->id] = $motiv;
        }
        self::assertSame('2027-04-01 12:00:00', $podleId[$zobrazovany]->zobrazitDo->format('Y-m-d H:i:s'));
        self::assertSame('2028-04-02 00:00:00', $podleId[$naplanovany]->zobrazitDo->format('Y-m-d H:i:s'));
    }

    public function testSmazanyMotivNeplati(): void
    {
        $repository = new NaplanovaneMotivyWebuRepository();
        $id = $repository->naplanuj(
            MotivWebuEnum::APRIL,
            new \DateTimeImmutable('2027-04-01 00:00'),
            new \DateTimeImmutable('2027-04-02 00:00'),
            \Uzivatel::SYSTEM,
        );

        $repository->smaz($id);

        self::assertNull($repository->zobrazovany(new \DateTimeImmutable('2027-04-01 12:00')));
    }

    public function testKonecPredZacatkemNejdeNaplanovat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new NaplanovaneMotivyWebuRepository())->naplanuj(
            MotivWebuEnum::APRIL,
            new \DateTimeImmutable('2027-04-02 00:00'),
            new \DateTimeImmutable('2027-04-01 00:00'),
            \Uzivatel::SYSTEM,
        );
    }

    /**
     * A theme removed from code (or added by a newer version) must not break the web, the one under it shows instead.
     */
    public function testNeznamyMotivSePreskoci(): void
    {
        $repository = new NaplanovaneMotivyWebuRepository();
        $april = $repository->naplanuj(
            MotivWebuEnum::APRIL,
            new \DateTimeImmutable('2027-04-01 00:00'),
            new \DateTimeImmutable('2027-04-03 00:00'),
            \Uzivatel::SYSTEM,
        );
        dbQuery(<<<'SQL'
INSERT INTO naplanovany_motiv_webu (motiv, zobrazit_od, zobrazit_do)
VALUES ('z-budoucnosti', '2027-04-02 00:00:00', '2027-04-02 23:00:00')
SQL,
        );

        self::assertSame($april, $repository->zobrazovany(new \DateTimeImmutable('2027-04-02 12:00'))?->id);
    }
}

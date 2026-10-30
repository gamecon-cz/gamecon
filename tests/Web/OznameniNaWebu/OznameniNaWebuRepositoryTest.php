<?php

declare(strict_types=1);

namespace Gamecon\Tests\Web\OznameniNaWebu;

use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Web\OznameniNaWebu\OznameniNaWebu;
use Gamecon\Web\OznameniNaWebu\OznameniNaWebuRepository;
use Gamecon\Web\OznameniNaWebu\TypOznameniNaWebuEnum;

class OznameniNaWebuRepositoryTest extends AbstractTestDb
{
    public function testUlozeneOznameniSeNacteZpet(): void
    {
        $repository = new OznameniNaWebuRepository();
        $id = $repository->uloz(
            null,
            "  Bouřka\r\nvenku se nehraje \n",
            TypOznameniNaWebuEnum::UPOZORNENI,
            new \DateTimeImmutable('2027-07-17 10:00'),
            new \DateTimeImmutable('2027-07-17 18:00'),
            \Uzivatel::SYSTEM,
        );

        $oznameni = (new OznameniNaWebuRepository())->dej($id);

        self::assertNotNull($oznameni);
        self::assertSame("Bouřka\nvenku se nehraje", $oznameni->text);
        self::assertSame(TypOznameniNaWebuEnum::UPOZORNENI, $oznameni->typ);
        self::assertSame('2027-07-17 10:00:00', $oznameni->zobrazitOd?->format('Y-m-d H:i:s'));
        self::assertSame('2027-07-17 18:00:00', $oznameni->zobrazitDo?->format('Y-m-d H:i:s'));
        self::assertSame(\Uzivatel::SYSTEM, $oznameni->zmenil);
        self::assertNotNull($oznameni->zmenenoKdy);
    }

    public function testUpravaPrepiseOznameni(): void
    {
        $repository = new OznameniNaWebuRepository();
        $id = $repository->uloz(null, 'Původní', TypOznameniNaWebuEnum::INFO, null, null, \Uzivatel::SYSTEM);

        self::assertSame($id, $repository->uloz($id, 'Nové', TypOznameniNaWebuEnum::NALEHAVE, null, null, \Uzivatel::SYSTEM));

        $oznameni = $repository->dej($id);
        self::assertNotNull($oznameni);
        self::assertSame('Nové', $oznameni->text);
        self::assertSame(TypOznameniNaWebuEnum::NALEHAVE, $oznameni->typ);
    }

    public function testZobrazujeJenOznameniVJejichCaseNejnalehavejsiPrvni(): void
    {
        $repository = new OznameniNaWebuRepository();
        $bezOmezeni = $repository->uloz(null, 'Bez omezení', TypOznameniNaWebuEnum::INFO, null, null, \Uzivatel::SYSTEM);
        $repository->uloz(null, 'Budoucí', TypOznameniNaWebuEnum::INFO, new \DateTimeImmutable('2027-07-17 13:00'), null, \Uzivatel::SYSTEM);
        $repository->uloz(null, 'Skončené', TypOznameniNaWebuEnum::INFO, null, new \DateTimeImmutable('2027-07-17 11:00'), \Uzivatel::SYSTEM);
        $nalehave = $repository->uloz(
            null,
            'Naléhavé',
            TypOznameniNaWebuEnum::NALEHAVE,
            new \DateTimeImmutable('2027-07-17 11:00'),
            new \DateTimeImmutable('2027-07-17 13:00'),
            \Uzivatel::SYSTEM,
        );

        self::assertSame(
            [$nalehave, $bezOmezeni],
            self::idcka($repository->zobrazovana(new \DateTimeImmutable('2027-07-17 12:00'))),
        );
    }

    public function testUkonceneOznameniZmiziANaplanovaneZustane(): void
    {
        $repository = new OznameniNaWebuRepository();
        $zobrazovane = $repository->uloz(null, 'Na webu', TypOznameniNaWebuEnum::INFO, null, null, \Uzivatel::SYSTEM);
        $naplanovane = $repository->uloz(null, 'Až zítra', TypOznameniNaWebuEnum::INFO, new \DateTimeImmutable('2027-07-18 10:00'), null, \Uzivatel::SYSTEM);
        $ted = new \DateTimeImmutable('2027-07-17 12:00');

        $repository->ukonci($zobrazovane, $ted, \Uzivatel::SYSTEM);
        $repository->ukonci($naplanovane, $ted, \Uzivatel::SYSTEM);

        self::assertSame([], $repository->zobrazovana($ted));
        self::assertSame('2027-07-17 12:00:00', $repository->dej($zobrazovane)?->zobrazitDo?->format('Y-m-d H:i:s'));
        self::assertNull($repository->dej($naplanovane)?->zobrazitDo);
    }

    public function testSmazaneOznameniNeexistuje(): void
    {
        $repository = new OznameniNaWebuRepository();
        $id = $repository->uloz(null, 'Omyl', TypOznameniNaWebuEnum::INFO, null, null, \Uzivatel::SYSTEM);

        $repository->smaz($id);

        self::assertNull($repository->dej($id));
    }

    public function testPrazdnyTextNejdeUlozit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new OznameniNaWebuRepository())->uloz(null, " \n ", TypOznameniNaWebuEnum::INFO, null, null, \Uzivatel::SYSTEM);
    }

    public function testKonecPredZacatkemNejdeUlozit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new OznameniNaWebuRepository())->uloz(
            null,
            'Obráceně',
            TypOznameniNaWebuEnum::INFO,
            new \DateTimeImmutable('2027-07-17 12:00'),
            new \DateTimeImmutable('2027-07-17 11:00'),
            \Uzivatel::SYSTEM,
        );
    }

    public function testNeznamyTypSePreskoci(): void
    {
        dbQuery(<<<'SQL'
INSERT INTO oznameni_na_webu (text, typ) VALUES ('Z budoucnosti', 'z-budoucnosti')
SQL,
        );

        self::assertSame([], (new OznameniNaWebuRepository())->zobrazovana(new \DateTimeImmutable('2027-07-17 12:00')));
    }

    /**
     * @param list<OznameniNaWebu> $oznameni
     *
     * @return list<int>
     */
    private static function idcka(array $oznameni): array
    {
        return array_map(static fn (OznameniNaWebu $jedno): int => $jedno->id, $oznameni);
    }
}

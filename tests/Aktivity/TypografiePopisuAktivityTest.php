<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\JazykAktivity;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as AktivitaSql;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Tests\Db\AbstractTestDb;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Popis aktivity dostane typografii podle jazyka aktivity (Trello 1137): anglická aktivita anglické uvozovky,
 * ostatní české, i na anglické stránce. Smíšený popis (`cs-en`) a jazykově nezávislá zůstávají česky.
 */
class TypografiePopisuAktivityTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    /**
     * @return array<string, array{0: JazykAktivity, 1: string}>
     */
    public static function provideJazykAUvozovky(): array
    {
        return [
            'anglická'         => [JazykAktivity::ANGLICKY, '<p>A “mysterious” letter – then…</p>'],
            'česká'            => [JazykAktivity::CESKY, '<p>A „mysterious“ letter – then…</p>'],
            'česky i anglicky' => [JazykAktivity::CESKY_I_ANGLICKY, '<p>A „mysterious“ letter – then…</p>'],
            'nezávislá'        => [JazykAktivity::NEZAVISLA, '<p>A „mysterious“ letter – then…</p>'],
        ];
    }

    #[DataProvider('provideJazykAUvozovky')]
    public function testPopisMaTypografiiPodleJazykaAktivity(JazykAktivity $jazyk, string $ocekavanyHtml): void
    {
        $aktivita = $this->aktivita($jazyk, 'A "mysterious" letter - then...');

        self::assertSame($ocekavanyHtml, trim((string) $aktivita->popis()));
    }

    public function testAnglickeUvozovkyAnglickePopisyNechaJakJsou(): void
    {
        $aktivita = $this->aktivita(JazykAktivity::ANGLICKY, 'A “mysterious” letter');

        self::assertSame('<p>A “mysterious” letter</p>', trim((string) $aktivita->popis()), 'Česká pravidla by je přepsala na „…“.');
    }

    private function aktivita(JazykAktivity $jazyk, string $popis): Aktivita
    {
        dbInsert(AktivitaSql::AKCE_SEZNAM_TABULKA, [
            AktivitaSql::NAZEV_AKCE => 'Typografie popisu ' . $jazyk->value . ' ' . bin2hex(random_bytes(4)),
            AktivitaSql::TYP        => TypAktivity::RPG,
            AktivitaSql::ROK        => ROCNIK,
            AktivitaSql::STAV       => StavAktivity::AKTIVOVANA,
            AktivitaSql::ZACATEK    => ROCNIK . '-07-16 10:00:00',
            AktivitaSql::KONEC      => ROCNIK . '-07-16 13:00:00',
            AktivitaSql::KAPACITA   => 4,
            AktivitaSql::CENA       => 0,
            AktivitaSql::POPIS      => $popis,
            AktivitaSql::JAZYK      => $jazyk->value,
        ]);

        return Aktivita::zId((int) dbInsertId());
    }
}

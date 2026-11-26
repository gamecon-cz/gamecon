<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\EditorTagu;
use Gamecon\Aktivita\SqlStruktura\KategorieSjednocenychTaguSqlStruktura as KategorieTaguSql;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Anglický název štítku zadaný v adminu, prázdný = na anglickém webu česky (NULL).
 */
class EditorTaguTest extends AbstractTestDb
{
    private string $idKategorie;

    protected function setUp(): void
    {
        parent::setUp();
        dbInsert(KategorieTaguSql::KATEGORIE_SJEDNOCENYCH_TAGU_TABULKA, [
            KategorieTaguSql::NAZEV  => 'Kategorie editoru tagů',
            KategorieTaguSql::PORADI => 999,
        ]);
        $this->idKategorie = (string) dbInsertId();
    }

    protected function tearDown(): void
    {
        $_POST = [];
        parent::tearDown();
    }

    public function testNovyTagSAnglickymNazvem(): void
    {
        $tag = $this->pridej('Nový štítek', ' New tag ');

        self::assertSame('New tag', $tag['nazev_en']);
        self::assertSame('New tag', $this->nazevEnZDb($tag['id']));
    }

    public function testPrazdnyAnglickyNazevSeUloziJakoNull(): void
    {
        $tag = $this->pridej('Štítek jen česky', '  ');

        self::assertNull($this->nazevEnZDb($tag['id']));
        self::assertSame('', $tag['nazev_en'], 'Formulář dostane prázdný text, ne null');
    }

    public function testSmazaniAnglickehoNazvuPriUprave(): void
    {
        $tag = $this->pridej('Upravovaný štítek', 'Edited tag');

        $_POST = $this->post('Upravovaný štítek', '', $tag['id']);
        $vysledek = (new EditorTagu())->editujTag();

        self::assertTrue($vysledek['isEdited'] ?? false, var_export($vysledek, true));
        self::assertNull($this->nazevEnZDb($tag['id']));
        self::assertSame('Upravovaný štítek', $vysledek['tag']['nazev'], 'Český název, podle kterého páruje import, zůstává');
    }

    /**
     * @return array<string, mixed>
     */
    private function pridej(string $nazev, string $nazevEn): array
    {
        $_POST = $this->post($nazev, $nazevEn);
        $vysledek = (new EditorTagu())->pridejNovyTag();
        self::assertTrue($vysledek['isNew'] ?? false, var_export($vysledek, true));

        return $vysledek['tag'];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function post(string $nazev, string $nazevEn, string $idTagu = ''): array
    {
        return [
            'aEditTag' => [
                'aEditTagId'         => $idTagu,
                'aEditNazevTagu'     => $nazev,
                'aEditNazevEnTagu'   => $nazevEn,
                'aEditKategorieTagu' => $this->idKategorie,
                'aEditPoznamkaTagu'  => '',
            ],
        ];
    }

    private function nazevEnZDb(int|string $idTagu): ?string
    {
        $nazevEn = dbOneCol(
            <<<'SQL'
SELECT nazev_en FROM sjednocene_tagy WHERE id = $0
SQL,
            [
                0 => $idTagu,
            ],
        );

        return $nazevEn === null
            ? null
            : (string) $nazevEn;
    }
}

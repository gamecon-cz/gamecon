<?php

namespace Gamecon\Uzivatel;

use FioPlatba;
use DateTimeInterface;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Uzivatel;
use DateTimeImmutable;
use Gamecon\Uzivatel\PlatbySqlStruktura as Sql;

class Platby
{

    public function __construct(private readonly SystemoveNastaveni $systemoveNastaveni) {
    }

    /**
     * Načte a uloží nové platby z FIO, vrátí zaúčtované platby
     * @return \FioPlatba[]
     */
    public function nactiNove(): array {
        return $this->zpracujPlatby(FioPlatba::zPoslednichDni($this->systemoveNastaveni->nacitatPlatbyXDniZpet()));
    }

    /**
     * @param DateTimeInterface $od
     * @param DateTimeInterface $do
     * @return FioPlatba[]
     */
    public function nactiZRozmezi(DateTimeInterface $od, DateTimeInterface $do) {
        return $this->zpracujPlatby(FioPlatba::zRozmezi($od, $do));
    }

    /**
     * @param FioPlatba[] $fioPlatby
     * @return FioPlatba[] Zpracované,nepřeskočené FIO platby
     */
    private function zpracujPlatby(array $fioPlatby): array {
        $vysledek = [];
        foreach ($fioPlatby as $fioPlatba) {
            if ($this->platbuUzMame($fioPlatba->id())) {
                continue;
            }
            $vlastnik = $fioPlatba->idUcastnika()
                ? Uzivatel::zId($fioPlatba->idUcastnika())
                : null;
            dbInsert(
                Sql::PLATBY_TABULKA,
                [
                    Sql::ID_UZIVATELE           => $vlastnik?->id(), // nespárovné platby uložíme a později nahlásíme CFO
                    Sql::FIO_ID                 => $fioPlatba->id(),
                    Sql::CASTKA                 => $fioPlatba->castka(),
                    Sql::ROK                    => ROCNIK,
                    Sql::PRIPSANO_NA_UCET_BANKY => $fioPlatba->datum(),
                    Sql::PROVEDENO              => new DateTimeImmutable(),
                    Sql::PROVEDL                => Uzivatel::SYSTEM,
                    Sql::POZNAMKA               => strlen($fioPlatba->zpravaProPrijemce()) > 4
                        ? $fioPlatba->zpravaProPrijemce()
                        : null,
                ]
            );
            $vysledek[] = $fioPlatba;
        }
        return $vysledek;
    }

    private function platbuUzMame(string $idFioPlatby): bool {
        return (bool)dbOneCol('SELECT 1 FROM platby WHERE fio_id = $1', [$idFioPlatby]);
    }

    /**
     * @param int|null $rok
     * @return array|Platba[]
     */
    public function nesparovanePlatby(int $rok = null): array {
        $ids    = dbFetchColumn(<<<SQL
            SELECT id
            FROM platby
            WHERE id_uzivatele IS NULL
                AND IF ($0, rok = $0, TRUE)
            SQL,
            [0 => $rok]
        );
        $platby = [];
        foreach ($ids as $id) {
            $platby[] = Platba::zId($id);
        }
        return $platby;
    }

    public function nejakeNesparovanePlatby(): bool {
        return dbOneCol(<<<SQL
SELECT 1 WHERE EXISTS(SELECT *FROM platby WHERE id_uzivatele IS NULL)
SQL
        );
    }

}

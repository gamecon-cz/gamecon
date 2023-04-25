<?php

namespace Gamecon\Uzivatel;

use FioPlatba;
use DateTimeInterface;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Logger\LogHomadnychAkciTrait;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Uzivatel;
use DateTimeImmutable;
use Gamecon\Uzivatel\PlatbySqlStruktura as Sql;

class Platby
{

    use LogHomadnychAkciTrait;

    private const SKUPINA = 'patby';

    private ?DateTimeInterface $posledniAktulizacePlatebBehemSessionKdy = null;

    public function __construct(private readonly SystemoveNastaveni $systemoveNastaveni)
    {
    }

    /**
     * Načte a uloží nové platby z FIO, vrátí zaúčtované platby
     * @return \FioPlatba[]
     */
    public function nactiNove(): array
    {
        return $this->zpracujPlatby(FioPlatba::zPoslednichDni($this->systemoveNastaveni->nacitatPlatbyXDniZpet()));
    }

    /**
     * @param DateTimeInterface $od
     * @param DateTimeInterface $do
     * @return FioPlatba[]
     */
    public function nactiZRozmezi(DateTimeInterface $od, DateTimeInterface $do)
    {
        return $this->zpracujPlatby(FioPlatba::zRozmezi($od, $do));
    }

    /**
     * @param FioPlatba[] $fioPlatby
     * @return FioPlatba[] Zpracované,nepřeskočené FIO platby
     */
    private function zpracujPlatby(array $fioPlatby): array
    {
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
                ],
            );
            $vysledek[] = $fioPlatba;
        }
        return $vysledek;
    }

    private function platbuUzMame(string $idFioPlatby): bool
    {
        return (bool)dbOneCol('SELECT 1 FROM platby WHERE fio_id = $1', [$idFioPlatby]);
    }

    /**
     * @param int|null $rok
     * @return \Generator|Platba[]
     */
    public function nesparovanePlatby(?int $rok = ROCNIK): \Generator
    {
        $result = dbQuery(<<<SQL
            SELECT id
            FROM platby
            WHERE id_uzivatele IS NULL
                AND IF ($0, rok = $0, TRUE)
            SQL,
            [0 => $rok],
            dbConnectTemporary(),
        );
        while ($id = mysqli_fetch_column($result)) {
            yield Platba::zId($id, true);
        }
    }

    public function nejakeNesparovanePlatby(?int $rok = ROCNIK): bool
    {
        return dbOneCol(<<<SQL
            SELECT EXISTS(SELECT * FROM platby WHERE id_uzivatele IS NULL AND IF ($0, rok = $0, TRUE)) AS existuji_nesparovane_platby
            SQL,
            [0 => $rok],
        );
    }

    public function cfoNotifikovanONesparovanychPlatbachKdy(int $rocnik, int $poradiOznameni): ?DateTimeImmutableStrict
    {
        return $this->posledniHromadnaAkceKdy(
            self::SKUPINA,
            $this->sestavNazevAkceEmailuONesparovanychPlatbach($rocnik, $poradiOznameni),
        );
    }

    private function sestavNazevAkceEmailuONesparovanychPlatbach(int $rocnik, int $poradiOznameni): string
    {
        return "email-cfo-nesparovane-platby-$rocnik-$poradiOznameni";
    }

    public function zalogujCfoNotifikovanONesparovanychPlatbach(
        int      $rocnik,
        int      $poradiOznameni,
        int      $nesparovanychPlateb,
        Uzivatel $provedl,
    )
    {
        $this->zalogujHromadnouAkci(
            self::SKUPINA,
            $this->sestavNazevAkceEmailuONesparovanychPlatbach($rocnik, $poradiOznameni),
            $nesparovanychPlateb,
            $provedl,
        );
    }

    public function platbyBylyAktualizovanyPredChvili(): bool
    {
        return $this->posledniAktulizacePlatebBehemSessionKdy !== null
            && ($this->posledniAktulizacePlatebBehemSessionKdy->getTimestamp() + 30) > time();
    }

    public function nastavPosledniAktulizaciPlatebBehemSessionKdy(
        DateTimeInterface $posledniAktulizacePlatebBehemSessionKdy,
    )
    {
        $this->posledniAktulizacePlatebBehemSessionKdy = $posledniAktulizacePlatebBehemSessionKdy;
    }

}

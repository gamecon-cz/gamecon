<?php

namespace Gamecon\Uzivatel;

use DateTimeImmutable;
use DateTimeInterface;
use FioPlatba;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Logger\LogHomadnychAkciTrait;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\SqlStruktura\PlatbySqlStruktura as Sql;
use Uzivatel;

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
    public function nactiZPoslednichDni(
        int  $pocetDni = null,
        bool $vratPouzeNoveSparovane = true,
    ): array {
        return $this->zpracujPlatby(
            FioPlatba::zPoslednichDni(
                $pocetDni ?? $this->systemoveNastaveni->nacitatPlatbyXDniZpet(),
            ),
            $vratPouzeNoveSparovane,
        );
    }

    /**
     * @param DateTimeInterface $od
     * @param DateTimeInterface $do
     * @return FioPlatba[]
     */
    public function nactiZRozmezi(
        DateTimeInterface $od,
        DateTimeInterface $do,
        bool              $vratPouzeNoveSparovane = true,
    ) {
        return $this->zpracujPlatby(FioPlatba::zRozmezi($od, $do), $vratPouzeNoveSparovane);
    }

    public function dejFioPlatbuPodleGcPlatby(Platba $platba): ?FioPlatba
    {
        $fioId = $platba->fioId();
        if (!$fioId) {
            return null;
        }
        $sqlite       = $this->tabulkaProLogovani();
        $pdoStatement = $sqlite->query(<<<SQLITE3
            SELECT * FROM fio_platby_log WHERE fio_id = {$platba->fioId()}
            SQLITE3,
        );
        $data         = $pdoStatement->fetchAll();
        if (!$data) {
            return null;
        }
        if (count($data) > 1) {
            throw new \RuntimeException(
                "FIO platba s ID '$fioId' má více záznamů v logu. Mělo by to být 1:1",
            );
        }
        $platbaData = reset($data);

        return FioPlatba::zeZaznamu($platbaData);
    }

    public function platbyNaposledyAktualizovanyKdy(): ?DateTimeImmutable
    {
        $sqlite                 = $this->tabulkaProLogovani();
        $naposledyAktualizovano = $sqlite->fetchSingleValue(<<<SQLITE3
            SELECT MAX(zalogovano_kdy) FROM fio_platby_log WHERE typ = "prichozi"
            SQLITE3,
        );

        return $naposledyAktualizovano
            ? new DateTimeImmutable($naposledyAktualizovano)
            : null;
    }

    /**
     * @param int|null $rocnik
     * @return iterable<Platba>
     */
    public function nesparovanePlatby(
        ?int   $rocnik = ROCNIK,
        string $orderByDesc = Sql::ID,
    ): iterable {
        $result = dbQuery(<<<SQL
            SELECT id
            FROM platby
            WHERE id_uzivatele IS NULL
                AND IF ($0 IS NOT NULL, rok = $0, TRUE)
            ORDER BY {$orderByDesc} DESC
            SQL,
            [0 => $rocnik],
            dbConnectTemporary(),
        );
        while ($id = mysqli_fetch_column($result)) {
            yield Platba::zId($id, true);
        }
    }

    public function nejakeNesparovanePlatby(?int $rok = ROCNIK): bool
    {
        return (bool)dbOneCol(<<<SQL
            SELECT EXISTS(SELECT * FROM platby WHERE id_uzivatele IS NULL AND IF ($0, rok = $0, TRUE)) AS existuji_nesparovane_platby
            SQL,
            [0 => $rok],
        );
    }

    public function cfoNotifikovanONesparovanychPlatbachKdy(
        int $rocnik,
        int $poradiOznameni,
    ): ?DateTimeImmutableStrict {
        return $this->posledniHromadnaAkceKdy(
            self::SKUPINA,
            self::sestavNazevAkceEmailuONesparovanychPlatbach($rocnik, $poradiOznameni),
        );
    }

    public function zalogujCfoNotifikovanONesparovanychPlatbach(
        int      $rocnik,
        int      $poradiOznameni,
        int      $nesparovanychPlateb,
        Uzivatel $provedl,
    ) {
        $this->zalogujHromadnouAkci(
            self::SKUPINA,
            self::sestavNazevAkceEmailuONesparovanychPlatbach($rocnik, $poradiOznameni),
            $nesparovanychPlateb,
            $provedl,
        );
    }

    public function platbyBylyAktualizovanyPredChvili(): bool
    {
        return (
                   $this->posledniAktulizacePlatebBehemSessionKdy !== null
                   && ($this->posledniAktulizacePlatebBehemSessionKdy->getTimestamp() + 30) > time()
               )
               || (
                   ($naposledyKdy = $this->platbyNaposledyAktualizovanyKdy()) !== null
                   && ($naposledyKdy->getTimestamp() + 30) > time()
               );
    }

    public function nastavPosledniAktulizaciPlatebBehemSessionKdy(
        DateTimeInterface $posledniAktulizacePlatebBehemSessionKdy,
    ) {
        $this->posledniAktulizacePlatebBehemSessionKdy = $posledniAktulizacePlatebBehemSessionKdy;
    }

    private static function sestavNazevAkceEmailuONesparovanychPlatbach(
        int $rocnik,
        int $poradiOznameni,
    ): string {
        return "email-cfo-nesparovane-platby-$rocnik-$poradiOznameni";
    }

    /**
     * @param FioPlatba[] $fioPlatby
     * @return FioPlatba[] Zpracované,nepřeskočené FIO platby
     */
    private function zpracujPlatby(
        array $fioPlatby,
        bool  $vratPouzeNoveSparovane,
    ): array {
        $this->zalogujPrichoziPlatby($fioPlatby);

        $sparovani          = [];
        $sparovaneFioPlatby = [];
        foreach ($fioPlatby as $fioPlatba) {
            if ($this->platbuUzMame($fioPlatba->id())) {
                // TODO jen dočasně než postahujeme minuté KOD_BANKY_PROTIUCTU atd
                $gcPlatba = $this->dejGcPlatbuPodleFioPlatby($fioPlatba);
                if ($gcPlatba) {
                    $this->doplnPlatbu($gcPlatba, $fioPlatba);
                }
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
                    Sql::VS                     => $fioPlatba->variabilniSymbol(),
                    Sql::CASTKA                 => $fioPlatba->castka(),
                    Sql::ROK                    => ROCNIK,
                    Sql::PRIPSANO_NA_UCET_BANKY => $fioPlatba->datum(),
                    Sql::PROVEDENO              => new DateTimeImmutable(),
                    Sql::PROVEDL                => Uzivatel::SYSTEM,
                    Sql::NAZEV_PROTIUCTU        => $fioPlatba->nazevProtiuctu(),
                    Sql::CISLO_PROTIUCTU        => $fioPlatba->protiucet(),
                    Sql::KOD_BANKY_PROTIUCTU    => $fioPlatba->kodBanky(),
                    Sql::POZNAMKA               => $fioPlatba->zpravaProPrijemce(),
                    Sql::SKRYTA_POZNAMKA        => $fioPlatba->skrytaPoznamka(),
                ],
            );
            $idQcPlatby           = dbInsertId();
            $sparovani[]          = ['gcId' => $idQcPlatby, 'fioId' => $fioPlatba->id()];
            $sparovaneFioPlatby[] = $fioPlatba;
        }

        $this->zalogujSparovani($sparovani);

        return $vratPouzeNoveSparovane
            ? $sparovaneFioPlatby
            : $fioPlatby;
    }

    /**
     * @param array<FioPlatba> $fioPlatby
     */
    private function zalogujPrichoziPlatby(array $fioPlatby): void
    {
        $this->zalogujPlatby($fioPlatby, 'prichozi');
    }

    /**
     * @param array<array{gcId: int, fioId: string|int}> $fioPlatby
     */
    private function zalogujSparovani(array $fioPlatby): void
    {
        $sqlite = $this->tabulkaProLogovani();

        foreach ($fioPlatby as ['gcId' => $gcId, 'fioId' => $fioId]) {
            $sqlite->query(<<<SQLITE3
                UPDATE fio_platby_log SET gc_id = {$gcId} WHERE fio_id = '{$fioId}'
                SQLITE3,
            );
        }
    }

    /**
     * @param array<FioPlatba> $fioPlatby
     */
    private function zalogujPlatby(
        array  $fioPlatby,
        string $typZaznamu,
    ) {
        $sqlite = $this->tabulkaProLogovani();
        foreach ($fioPlatby as $fioPlatba) {
            $sqlite->insertOrReplace(
                'fio_platby_log',
                [
                    'fio_id'         => $fioPlatba->id(),
                    'typ'            => $typZaznamu,
                    'data'           => json_encode(
                        $fioPlatba->data(),
                        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                    ),
                    'vysledny_vs'    => $fioPlatba->variabilniSymbol(),
                    'zalogovano_kdy' => date('c'),
                ],
            );
        }
    }

    private function tabulkaProLogovani(): \EPDO
    {
        $sqlite = new \EPDO('sqlite:' . SPEC . '/platby.sqlite');

        $sqlite->query(<<<SQLITE3
            CREATE TABLE IF NOT EXISTS fio_platby_log(
                typ                 VARCHAR(255) NOT NULL,
                fio_id              VARCHAR(255) NOT NULL UNIQUE,
                gc_id               INT NULL UNIQUE,
                data                JSON,
                vysledny_vs         VARCHAR(255),
                zalogovano_kdy      DATETIME
            )
            SQLITE3,
        );

        $sqlite->query(<<<SQLITE3
            CREATE INDEX IF NOT EXISTS idx_zalogovano_kdy ON fio_platby_log (zalogovano_kdy)
            SQLITE3,
        );

        return $sqlite;
    }

    public function dejGcPlatbuPodleFioPlatby(FioPlatba $fioPlatba): ?Platba
    {
        return Platba::zFioId($fioPlatba->id());
    }

    public function doplnPlatbu(
        Platba    $gcPlatba,
        FioPlatba $fioPlatba,
    ): bool {
        if ($gcPlatba->fioId() !== $fioPlatba->id()) {
            throw new \LogicException(
                sprintf(
                    "Fio platba %s není pro GC platbu %d",
                    $fioPlatba->id(),
                    $gcPlatba->id(),
                ),
            );
        }
        $zmeny = [];
        // doplnění částky je schválně ignorováno - ta by neměla chybět nikdy
        if ($gcPlatba->variabilniSymbol() === null && $fioPlatba->variabilniSymbol() !== null) {
            $zmeny[Sql::VS] = $fioPlatba->variabilniSymbol();
        }
        if ($gcPlatba->nazevProtiuctu() === null && $fioPlatba->nazevProtiuctu() !== null) {
            $zmeny[Sql::NAZEV_PROTIUCTU] = $fioPlatba->nazevProtiuctu();
        }
        if ($gcPlatba->cisloProtiuctu() === null && $fioPlatba->protiucet() !== null) {
            $zmeny[Sql::CISLO_PROTIUCTU] = $fioPlatba->protiucet();
        }
        if ($gcPlatba->kodBankyProtiuctu() === null && $fioPlatba->kodBanky() !== null) {
            $zmeny[Sql::KOD_BANKY_PROTIUCTU] = $fioPlatba->kodBanky();
        }
        if ($gcPlatba->nazevBankyProtiuctu() === null && $fioPlatba->nazevBanky() !== null) {
            $zmeny[Sql::NAZEV_BANKY_PROTIUCTU] = $fioPlatba->nazevBanky();
        }
        if ($gcPlatba->poznamka() === null && $fioPlatba->zpravaProPrijemce() !== null) {
            $zmeny[Sql::POZNAMKA] = $fioPlatba->zpravaProPrijemce();
        }
        if ($gcPlatba->skrytaPoznamka() === null && $fioPlatba->zpravaProPrijemce() !== null) {
            $zmeny[Sql::SKRYTA_POZNAMKA] = $fioPlatba->skrytaPoznamka();
        }
        if ($gcPlatba->pripsanoNaUcetBanky() === null) {
            $zmeny[Sql::PRIPSANO_NA_UCET_BANKY] = $fioPlatba->datum();
        }
        if ($zmeny === []) {
            return false;
        }
        dbUpdate(Sql::PLATBY_TABULKA, $zmeny, [Sql::ID => $gcPlatba->id()]);

        return true;
    }

    private function platbuUzMame(string $idFioPlatby): bool
    {
        return (bool)dbOneCol('SELECT 1 FROM platby WHERE fio_id = $1', [$idFioPlatby]);
    }
}

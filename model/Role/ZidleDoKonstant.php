<?php declare(strict_types=1);

namespace Gamecon\Role;

class ZidleDoKonstant
{

    private \mysqli $connection;

    public static function vytvorZGlobals(): self {
        return new static(dbConnect());
    }

    public function __construct(\mysqli $connection) {
        $this->connection = $connection;
    }

    // TODO asi smazat
    public function zaznamyDoKonstant() {
        try {
            $zaznamy = dbFetchAll(<<<SQL
SELECT id_zidle, kod_zidle
FROM r_zidle_soupis
SQL,
                [],
                $this->connection
            );
        } catch (\mysqli_sql_exception $exception) {
            if ($exception->getCode() === 1049) { // Unknown database
                return;
            }
            throw $exception;
        } catch (\ConnectionException $connectionException) {
            // testy nebo úplně prázdný Gamecon na začátku nemají ještě databázi
            return;
        } catch (\DbException $dbException) {
            if (in_array($dbException->getCode(), [1146 /* table does not exist */, 1054 /* new column does not exist */])) {
                return; // tabulka či sloupec musí vzniknout SQL migrací
            }
            throw $dbException;
        }
        foreach ($zaznamy as $zaznam) {
            $nazevKonstanty = trim(strtoupper($zaznam['kod_zidle']));
            if (!defined($nazevKonstanty)) {
                define($nazevKonstanty, (int)$zaznam['id_zidle']);
            }
        }
    }
}

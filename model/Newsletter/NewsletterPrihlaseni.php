<?php

declare(strict_types=1);

namespace Gamecon\Newsletter;

use Gamecon\Newsletter\SqlStruktura\NewsletterPrihlaseniSqlStruktura as Sql;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

class NewsletterPrihlaseni extends \DbObject
{
    protected static $tabulka = Sql::NEWSLETTER_PRIHLASENI_TABULKA;
    protected static $pk      = Sql::ID_NEWSLETTER_PRIHLASENI;

    public static function prihlas(
        string             $email,
        SystemoveNastaveni $systemoveNastaveni,
    ): void {
        dbBegin();
        $query = dbQuery(<<<SQL
            INSERT IGNORE INTO newsletter_prihlaseni (email, kdy) VALUES ($0, $1)
            SQL
            , [0 => $email, 1 => $systemoveNastaveni->ted()],
        );
        if (dbAffectedOrNumRows($query) > 0) {
            dbQuery(<<<SQL
            INSERT INTO newsletter_prihlaseni_log (email, kdy, stav)
            SELECT $0, $1, $2
            SQL
                , [0 => $email, 1 => $systemoveNastaveni->ted(), 2 => NewsletterPrihlaseniStavEnum::PRIHLASEN],
            );
        }
        dbCommit();
    }

    public static function odhlasit(
        string             $email,
        SystemoveNastaveni $systemoveNastaveni,
    ): void {
        dbBegin();
        $query = dbQuery(<<<SQL
            DELETE FROM newsletter_prihlaseni WHERE email = $0
            SQL
            , [0 => $email],
        );
        if (dbAffectedOrNumRows($query) > 0) {
            dbQuery(<<<SQL
            INSERT INTO newsletter_prihlaseni_log (email, kdy, stav)
            SELECT $0, $1, $2
            SQL
                , [0 => $email, 1 => $systemoveNastaveni->ted(), 2 => NewsletterPrihlaseniStavEnum::ODHLASEN],
            );
        }
        dbCommit();
    }
}

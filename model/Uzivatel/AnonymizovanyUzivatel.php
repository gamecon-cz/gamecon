<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as UzivatelSql;
use Gamecon\Uzivatel\SqlStruktura\MedailonekSqlStruktura as MedailonekSql;

class AnonymizovanyUzivatel
{
    public static function vytvorAnonymniUdajeProId(int $idUzivatele): array
    {
        return [
            UzivatelSql::LOGIN_UZIVATELE                     => "Login$idUzivatele",
            UzivatelSql::JMENO_UZIVATELE                     => '',
            UzivatelSql::PRIJMENI_UZIVATELE                  => '',
            UzivatelSql::ULICE_A_CP_UZIVATELE                => '',
            UzivatelSql::MESTO_UZIVATELE                     => '',
            UzivatelSql::STAT_UZIVATELE                      => -1,
            UzivatelSql::PSC_UZIVATELE                       => '',
            UzivatelSql::TELEFON_UZIVATELE                   => '',
            UzivatelSql::DATUM_NAROZENI                      => '0000-01-01',
            UzivatelSql::HESLO_MD5                           => '',
            UzivatelSql::EMAIL1_UZIVATELE                    => "email$idUzivatele@gamecon.cz",
            UzivatelSql::NECHCE_MAILY                        => null,
            UzivatelSql::MRTVY_MAIL                          => 0,
            UzivatelSql::FORUM_RAZENI                        => '',
            UzivatelSql::RANDOM                              => '',
            UzivatelSql::ZUSTATEK                            => 0,
            UzivatelSql::REGISTROVAN                         => 'NOW()',
            UzivatelSql::UBYTOVAN_S                          => '',
            UzivatelSql::POZNAMKA                            => '',
            UzivatelSql::POMOC_TYP                           => '',
            UzivatelSql::POMOC_VICE                          => '',
            UzivatelSql::OP                                  => '',
            UzivatelSql::POTVRZENI_ZAKONNEHO_ZASTUPCE        => null,
            UzivatelSql::INFOPULT_POZNAMKA                   => '',
            UzivatelSql::TYP_DOKLADU_TOTOZNOSTI              => '',
            UzivatelSql::STATNI_OBCANSTVI                    => null,
        ];
    }

    public static function sqlSetProAnonymizaci(): string
    {
        $anonymniData = self::vytvorAnonymniUdajeProId(0); // 0 je placeholder, bude nahrazeno v SQL

        $setParts = [];
        foreach ($anonymniData as $sloupec => $hodnota) {
            if ($sloupec === UzivatelSql::LOGIN_UZIVATELE) {
                $setParts[] = UzivatelSql::LOGIN_UZIVATELE . " = CONCAT('Login', " . UzivatelSql::ID_UZIVATELE . ")";
            } elseif ($sloupec === UzivatelSql::EMAIL1_UZIVATELE) {
                $setParts[] = UzivatelSql::EMAIL1_UZIVATELE . " = CONCAT('email', " . UzivatelSql::ID_UZIVATELE . ", '@gamecon.cz')";
            } elseif ($sloupec === UzivatelSql::REGISTROVAN) {
                $setParts[] = UzivatelSql::REGISTROVAN . " = NOW()";
            } elseif ($hodnota === null) {
                $setParts[] = "$sloupec = null";
            } elseif (is_string($hodnota)) {
                $setParts[] = "$sloupec = '$hodnota'";
            } else {
                $setParts[] = "$sloupec = $hodnota";
            }
        }

        return implode(",\n                    ", $setParts);
    }

    public static function vytvorAnonymniMedailonkoveDaje(): array
    {
        return [
            MedailonekSql::O_SOBE => '',
            MedailonekSql::DRD    => '',
        ];
    }

    public static function anonymizujUzivatele(
        \Uzivatel $uzivatel,
        ?\mysqli $mysqli = null,
    ): void {
        $idUzivatele = $uzivatel->id();
        $anonymniData = self::vytvorAnonymniUdajeProId($idUzivatele);

        $setParts   = [];
        $values     = [];
        $valueIndex = 1;

        foreach ($anonymniData as $sloupec => $hodnota) {
            if ($sloupec === UzivatelSql::LOGIN_UZIVATELE) {
                $setParts[] = UzivatelSql::LOGIN_UZIVATELE . " = CONCAT('Login', " . UzivatelSql::ID_UZIVATELE . ")";
            } elseif ($sloupec === UzivatelSql::EMAIL1_UZIVATELE) {
                $setParts[] = UzivatelSql::EMAIL1_UZIVATELE . " = CONCAT('email', " . UzivatelSql::ID_UZIVATELE . ", '@gamecon.cz')";
            } elseif ($sloupec === UzivatelSql::REGISTROVAN) {
                $setParts[] = UzivatelSql::REGISTROVAN . " = NOW()";
            } elseif ($hodnota === null) {
                $setParts[] = "$sloupec = null";
            } else {
                $setParts[] = "$sloupec = \$$valueIndex";
                $values[]   = $hodnota;
                $valueIndex++;
            }
        }

        $setClause = implode(', ', $setParts);

        dbQuery(
            "UPDATE uzivatele_hodnoty SET $setClause WHERE id_uzivatele = $idUzivatele",
            $values,
            $mysqli,
        );

        // Anonymizuj medailonek
        $medailonkyData       = self::vytvorAnonymniMedailonkoveDaje();
        $medailonkyValues     = [];
        $medailonkyValueIndex = 1;
        $medailonkySetParts   = [];

        foreach ($medailonkyData as $key => $value) {
            $medailonkySetParts[] = "$key = \$$medailonkyValueIndex";
            $medailonkyValues[]   = $value;
            $medailonkyValueIndex++;
        }

        $medailonkySet = implode(', ', $medailonkySetParts);

        dbQuery(
            "UPDATE medailonky SET $medailonkySet WHERE id_uzivatele = $idUzivatele",
            $medailonkyValues,
            $mysqli,
        );

        // Anonymizuj časy rolí
        dbQuery(
            q: "UPDATE uzivatele_role SET posazen = '1970-01-01 01:01:01' WHERE id_uzivatele = $idUzivatele",
            mysqli: $mysqli,
        );
    }
}

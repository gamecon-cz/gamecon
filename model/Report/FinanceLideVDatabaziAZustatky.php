<?php

declare(strict_types=1);

namespace Gamecon\Report;

use Gamecon\Role\Role;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\SqlStruktura\UzivatelSqlStruktura as Sql;

class FinanceLideVDatabaziAZustatky
{

    public function __construct(private readonly SystemoveNastaveni $systemoveNastaveni)
    {
    }

    public function exportuj(
        ?string $format,
        string  $doSouboru = null,
    )
    {
        $result = dbFetchAll(<<<SQL
SELECT
  uzivatele_hodnoty.id_uzivatele,
  uzivatele_hodnoty.jmeno_uzivatele,
  uzivatele_hodnoty.prijmeni_uzivatele,
  uzivatele_hodnoty.mesto_uzivatele,
  uzivatele_hodnoty.ulice_a_cp_uzivatele,
  uzivatele_hodnoty.psc_uzivatele,
  uzivatele_hodnoty.email1_uzivatele,
  uzivatele_hodnoty.telefon_uzivatele,
  uzivatele_hodnoty.zustatek,
  kladny_pohyb.datum AS "poslední kladný pohyb na účtu",
  zaporny_pohyb.datum AS "poslední záporný pohyb na účtu",
  GROUP_CONCAT(platne_role_uzivatelu.id_role) AS ids_roli
FROM uzivatele_hodnoty
LEFT JOIN ( -- poslední kladný pohyb na účtu
  SELECT
    id_uzivatele,
    MAX(provedeno) AS datum
  FROM platby
  WHERE castka > 0
  GROUP BY id_uzivatele
) AS kladny_pohyb ON kladny_pohyb.id_uzivatele = uzivatele_hodnoty.id_uzivatele
LEFT JOIN ( -- poslední záporný pohyb na účtu
  SELECT
    id_uzivatele,
    MAX(provedeno) AS datum
  FROM platby
  WHERE castka < 0
  GROUP BY id_uzivatele
) AS zaporny_pohyb ON zaporny_pohyb.id_uzivatele = uzivatele_hodnoty.id_uzivatele
LEFT JOIN platne_role_uzivatelu
    ON uzivatele_hodnoty.id_uzivatele = platne_role_uzivatelu.id_uzivatele
GROUP BY uzivatele_hodnoty.id_uzivatele
SQL,
        );

        if (count($result) === 0) {
            if ($doSouboru) {
                file_put_contents($doSouboru, '');
                return;
            }
            exit('V tabulce nejsou žádná data.');
        }

        $ucastPodleRoku = [];
        $maxRok         = $this->systemoveNastaveni->poRegistraciUcastniku()
            ? $this->systemoveNastaveni->rocnik()
            : $this->systemoveNastaveni->rocnik() - 1;
        for ($rokUcasti = 2009; $rokUcasti <= $maxRok; $rokUcasti++) {
            $ucastPodleRoku[Role::pritomenNaRocniku($rokUcasti)] = 'účast ' . $rokUcasti;
        }

        $obsah = [];
        foreach ($result as $r) {
            $ucastiHistorie   = [];
            $idsRoliUcastnika = explode(',', $r['ids_roli'] ?? '');
            foreach ($ucastPodleRoku as $idRolePritomenNaRocniku => $nazevUcasti) {
                $ucastiHistorie[$nazevUcasti] = in_array($idRolePritomenNaRocniku, $idsRoliUcastnika, false)
                    ? 'ano'
                    : 'ne';
            }
            unset($r['ids_roli']); // nechceme to v reportu
            $obsah[] = [
                ...$r,
                ...$ucastiHistorie,
            ];
        }

        $konfiguraceReportu = (new KonfiguraceReportu())
            ->setRowToFreeze(KonfiguraceReportu::NO_ROW_TO_FREEZE)
            ->setMaxGenericColumnWidth(50);

        if ($doSouboru) {
            $konfiguraceReportu->setDestinationFile($doSouboru);
        }

        \Report::zPole($obsah)
            ->tFormat($format, null, $konfiguraceReportu);
    }
}

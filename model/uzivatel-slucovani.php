<?php

class UzivatelSlucovani
{
    /**
     * @return array{0:string,1:string}[]  pole dvojic [tabulka, sloupec] odkazující na id_uzivatele
     */
    private function odkazujiciTabulky(): array
    {
        $odkazy = dbQuery('
            SELECT table_name, column_name
            FROM information_schema.key_column_usage
            WHERE
                table_schema = "' . DB_NAME . '" AND
                referenced_table_name = "uzivatele_hodnoty" AND
                referenced_column_name = "id_uzivatele"
        ')->fetch_all();

        // doplnění tabulek bez FK (whitelist)
        $odkazy[] = ['akce_prihlaseni_log', 'id_uzivatele'];
        $odkazy[] = ['platby', 'provedl'];
        $odkazy[] = ['akce_seznam', 'zamcel'];

        return $odkazy;
    }

    /**
     * @param array $zmeny páry sloupec => hodnota, které se mají upravit v
     * novém uživateli
     *
     * @return array<int, string[]>
     */
    private function unikatniIndexyObsahujiciSloupec(string $tabulka, string $sloupec): array
    {
        // POZOR: žádné "?" – vaše dbQuery je neparametrizuje.
        $sql = '
            SELECT index_name, seq_in_index, column_name
            FROM information_schema.statistics
            WHERE table_schema = "' . DB_NAME . '"
              AND table_name   = "' . $tabulka . '"
              AND non_unique   = 0
            ORDER BY index_name, seq_in_index
        ';
        $rows = dbQuery($sql)->fetch_all();

        $byIndex = [];
        foreach ($rows as $r) {
            $idx = $r['index_name'] ?? $r[0];
            $col = $r['column_name'] ?? $r[2];
            $byIndex[$idx][] = $col;
        }

        $res = [];
        foreach ($byIndex as $cols) {
            if (in_array($sloupec, $cols, true)) {
                $res[] = $cols;
            }
        }
        return $res;
    }

    /**
     * Smaže kolidující řádky starého uživatele vůči novému pro všechny unikátní indexy obsahující $sloupec.
     */
    private function predeleteKoliziProTabulku(string $tabulka, string $sloupec, int $novyId, int $staryId): void
    {
        $indexy = $this->unikatniIndexyObsahujiciSloupec($tabulka, $sloupec);
        if (!$indexy) {
            return;
        }

        $novyId = (int) $novyId;
        $staryId = (int) $staryId;

        foreach ($indexy as $cols) {
            $ostatni = array_values(array_filter($cols, fn($c) => $c !== $sloupec));

            // t_new.`$sloupec` = $novyId AND t_old.`$sloupec` = $staryId
            $join = ["t_new.`$sloupec` = $novyId", "t_old.`$sloupec` = $staryId"];
            // NULL-safe porovnání ostatních sloupců indexu
            foreach ($ostatni as $c) {
                $join[] = "t_new.`$c` <=> t_old.`$c`";
            }
            $on = implode(' AND ', $join);

            $sql = "DELETE t_old
                      FROM `{$tabulka}` t_old
                      JOIN `{$tabulka}` t_new
                        ON $on";
            dbQuery($sql);
        }
    }

    /**
     * @param array $zmeny páry sloupec => hodnota, které se mají upravit v novém uživateli
     */
    public function sluc(Uzivatel $stary, Uzivatel $novy, array $zmeny): void
    {
        $staryId = (int) $stary->id();
        $novyId = (int) $novy->id();

        if ($staryId === $novyId) {
            $this->zaloguj("slučování přeskočeno – stejné ID ($staryId)");
            return;
        }

        dbBegin();
        try {
            // 1) Pro každou odkazující tabulku nejprve odstranit kolize na unikátních indexech
            foreach ($this->odkazujiciTabulky() as [$tabulka, $sloupec]) {
                $this->predeleteKoliziProTabulku($tabulka, $sloupec, $novyId, $staryId);
            }

            // 2) Převod referencí na nového uživatele
            foreach ($this->odkazujiciTabulky() as [$tabulka, $sloupec]) {
                dbQuery("UPDATE `{$tabulka}` SET `{$sloupec}` = $novyId WHERE `{$sloupec}` = $staryId");
            }

            // 3) Smazání starých hodnot uživatele
            dbQuery("DELETE FROM `uzivatele_hodnoty` WHERE `id_uzivatele` = $staryId");

            // 4) Update nového uživatele
            dbUpdate('uzivatele_hodnoty', $zmeny, ['id_uzivatele' => $novyId]);

            dbCommit();
        } catch (Exception $e) {
            // catch a rollback nutný, jinak chyba způsobí visící perzist. spojení a deadlocky
            dbRollback();
            throw $e;
        }

        // DIAGNOSTIKA – výstupní stav + log
        $this->zaloguj("=== Diagnostika sloučení ($staryId -> $novyId) ===");
        foreach ($this->odkazujiciTabulky() as [$tabulka, $sloupec]) {
            $afterOld = $this->countById($tabulka, $sloupec, $staryId);
            $afterNew = $this->countById($tabulka, $sloupec, $novyId);

            $diag[$tabulka]['after_old'] = $afterOld;
            $diag[$tabulka]['after_new'] = $afterNew;
            $diag[$tabulka]['moved_to_new'] = max(0, $afterNew - $diag[$tabulka]['before_new']);

            $this->zaloguj(sprintf(
                "  [%s.%s] before_old=%d, predelete=%d, moved_to_new=%d, after_old=%d",
                $tabulka,
                $sloupec,
                $diag[$tabulka]['before_old'],
                $diag[$tabulka]['predelete'],
                $diag[$tabulka]['moved_to_new'],
                $diag[$tabulka]['after_old']
            ));

            if ($afterOld > 0) {
                $this->zaloguj("  !!! POZOR: Ve {$tabulka}.{$sloupec} zůstalo {$afterOld} řádků se starým ID {$staryId}");
            }
        }
        $this->zaloguj("=== Konec diagnostiky ===");

        // logování po úspěšném commitu
        $this->zaloguj("do id $novyId sloučeno a smazáno id $staryId");
        $this->zaloguj("  původní zůstatek smazaného účtu:  " . $stary->finance()->zustatekZPredchozichRocniku());
        $this->zaloguj("  původní zůstatek nového účtu:     " . $novy->finance()->zustatekZPredchozichRocniku());
        $this->zaloguj("  email smazaného účtu:             " . $stary->mail());
        $this->zaloguj("  email nového účtu:                " . $novy->mail());
        $novy = Uzivatel::zId($novy->id()); // přenačtení uživatele, aby se aktualizovaly finance
        $this->zaloguj("  aktuální nový zůstatek:           " . $novy->finance()->zustatekZPredchozichRocniku());
        $this->zaloguj("  aktuální nový email:              " . $novy->mail() . "\n");
    }

    /**
     * Zapíše zprávu do logu slučování uživatelů.
     */
    private function zaloguj(string $zprava): void
    {
        $soubor = LOGY . '/slucovani.log';
        $cas = date('Y-m-d H:i:s');
        file_put_contents($soubor, "$cas $zprava\n", FILE_APPEND);
    }
}

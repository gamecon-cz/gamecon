<?php

class Platby
{

    public const DNI_ZPET = 14; // kolik dní zpět se mají načítat platby při kontrole nově došlých plateb

    /**
     * Načte a uloží nové platby z FIO, vrátí zaúčtované platby
     * @return FioPlatba[]
     */
    static function nactiNove(): array {
        $vysledek = [];
        foreach (FioPlatba::zPoslednichDni(self::DNI_ZPET) as $fioPlatba) {
            if (!is_numeric($fioPlatba->vs()) || self::platbuUzMame($fioPlatba->id())) {
                continue;
            }
            $u = Uzivatel::zId($fioPlatba->vs());
            if (!$u) {
                continue;
            }
            // TODO umožnit nebo zakázat záporné platby (vs. není přihlášen na GC vs. automatický odečet vrácením na účet)
            dbInsert('platby', [
                'id_uzivatele' => $u->id(),
                'fio_id' => $fioPlatba->id(),
                'castka' => $fioPlatba->castka(),
                'rok' => ROK,
                'provedeno' => $fioPlatba->datum(),
                'provedl' => Uzivatel::SYSTEM,
                'poznamka' => strlen($fioPlatba->zprava()) > 4
                    ? $fioPlatba->zprava()
                    : null,
            ]);
            $vysledek[] = $fioPlatba;
        }
        return $vysledek;
    }

    private static function platbuUzMame(string $idFioPlatby): bool {
        return (bool)dbOneCol('SELECT 1 FROM platby WHERE fio_id = $1', [$idFioPlatby]);
    }

}

<?php

class Platby
{

    public const DNI_ZPET = 7; // kolik dní zpět se mají načítat platby při kontrole nově došlých plateb

    /**
     * Načte a uloží nové platby z FIO, vrátí zaúčtované platby
     */
    static function nactiNove() {
        $vysledek = [];
        foreach (FioPlatba::zPoslednichDni(self::DNI_ZPET) as $fioPlatba) {
            if ($fioPlatba->castka() > 0 // TODO umožnit nebo zakázat záporné platby (vs. není přihlášen na GC vs. automatický odečet vrácením na účet)
                && is_numeric($fioPlatba->vs())
                && self::jePlatbaNova($fioPlatba->id())
                && (($u = Uzivatel::zId($fioPlatba->vs())) && $u->gcPrihlasen())
            ) {
                dbInsert('platby', [
                    'id_uzivatele' => $u->id(),
                    'fio_id' => $fioPlatba->id(),
                    'castka' => $fioPlatba->castka(),
                    'rok' => ROK,
                    'provedl' => Uzivatel::SYSTEM,
                    'poznamka' => strlen($fioPlatba->zprava()) > 4 ? $fioPlatba->zprava() : null,
                ]);
                $vysledek[] = $fioPlatba;
            }
        }
        return $vysledek;
    }

    private static function jePlatbaNova(string $idFioPlatby): bool {
        return !dbOneCol('SELECT 1 FROM platby WHERE fio_id = $1', [$idFioPlatby]);
    }

}

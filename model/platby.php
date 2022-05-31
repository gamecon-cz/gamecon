<?php

class Platby
{

    public const DNI_ZPET = 14; // kolik dní zpět se mají načítat platby při kontrole nově došlých plateb

    /**
     * Načte a uloží nové platby z FIO, vrátí zaúčtované platby
     * @return FioPlatba[]
     */
    static function nactiNove(): array {
        return self::zpracujPlatby(FioPlatba::zPoslednichDni(self::DNI_ZPET));
    }

    /**
     * @param DateTimeInterface $od
     * @param DateTimeInterface $do
     * @return FioPlatba[]
     */
    public static function nactiZRozmezi(DateTimeInterface $od, DateTimeInterface $do) {
        return self::zpracujPlatby(FioPlatba::zRozmezi($od, $do));
    }

    /**
     * @param FioPlatba[] $fioPlatby
     * @return FioPlatba[] Zpracované,nepřeskočené FIO platby
     */
    private static function zpracujPlatby(array $fioPlatby): array {
        $vysledek = [];
        foreach ($fioPlatby as $fioPlatba) {
            if (!is_numeric($fioPlatba->vs()) || self::platbuUzMame($fioPlatba->id())) {
                continue;
            }
            $u = Uzivatel::zId($fioPlatba->vs());
            if (!$u) {
                continue;
            }
            dbInsert('platby', [
                'id_uzivatele' => $u->id(),
                'fio_id' => $fioPlatba->id(),
                'castka' => $fioPlatba->castka(),
                'rok' => ROK,
                'pripsano' => $fioPlatba->datum(),
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

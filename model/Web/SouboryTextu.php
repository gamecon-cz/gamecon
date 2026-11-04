<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Soubory, které patří k textu stránky nebo novinky: ty, na které text odkazuje, a ty nahrané do jeho adresáře.
 * Pracuje jen se soubory typů, které služba zná.
 */
final class SouboryTextu
{
    public function __construct(
        private readonly NahravaniObrazku $nahravani,
        private readonly string $text,
        private readonly string $adresarProNove,
    ) {
    }

    /**
     * Soubory, na které text odkazuje, ať leží kdekoli v `soubory/obsah`, jako cesty vůči němu. Adresáře log a e-shopu
     * vynechává, ty mají vlastní modul s jiným právem (@see ZpracovaneObrazky::jeAdresarJinehoModulu).
     *
     * @return list<string>
     */
    public function getOdkazovane(): array
    {
        return array_values(array_filter(
            OdkazyVTextu::getCesty($this->text, NahravaniObrazku::getPriponyFormatu($this->nahravani->formaty())),
            static fn (string $cesta): bool => !ZpracovaneObrazky::jeAdresarJinehoModulu($cesta),
        ));
    }

    /**
     * Služba pro dokumenty zná i obrázky, ale ty se k textu nahrávají zvlášť (@see ObrazkyObsahu), takže se tu nevypíšou.
     *
     * @return list<string> cesty vůči `soubory/obsah`
     */
    public function getVlastni(): array
    {
        return array_values(array_filter(
            array_map(
                fn (string $soubor): string => $this->adresarProNove . '/' . $soubor,
                $this->nahravani->obrazkyVAdresari($this->adresarProNove),
            ),
            fn (string $cesta): bool => in_array(NahravaniObrazku::mimePodlePripony($cesta), $this->nahravani->formaty(), true),
        ));
    }

    /**
     * @return list<string>
     */
    public function getVlastniProVypis(): array
    {
        try {
            return $this->getVlastni();
        } catch (NahravaniObrazkuException) {
            // adresář, který symlinkem vede mimo kořen, jen nevypíšeme; smazání a nahrazení ať řeknou skutečnou chybu
            return [];
        }
    }

    /**
     * Soubor jde nahradit, když na něj text odkazuje nebo leží v adresáři textu.
     */
    public function jeNahraditelny(string $cesta): bool
    {
        return in_array($cesta, $this->getOdkazovane(), true) || in_array($cesta, $this->getVlastni(), true);
    }

    /**
     * @return string|null absolutní cesta, pokud soubor existuje
     */
    public function getAbsolutniCesta(string $cesta): ?string
    {
        try {
            $absolutniCesta = $this->nahravani->cestaKSouboru($cesta);
        } catch (NahravaniObrazkuException) {
            return null;
        }

        return is_file($absolutniCesta)
            ? $absolutniCesta
            : null;
    }
}

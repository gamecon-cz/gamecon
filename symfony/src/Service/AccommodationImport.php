<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProductVariant;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Zápis jednoho řádku importu ubytování z Excelu do nové vrstvy.
 *
 * Import posílá **id předmětů** (legacy `shop_predmety`), kdežto `AccommodationWriter` chce
 * **id variant**. U ubytování to není totéž: varianta nese týž `kod_predmetu` jako řádek své
 * noci, ale rodičem variant je jedna konkrétní noc (neděle), takže přes `product_id` by se
 * dohledala cizí noc.
 */
readonly class AccommodationImport
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccommodationWriter $accommodationWriter,
    ) {
    }

    /**
     * @param int[] $idsPredmetuUbytovani id z `shop_predmety`, tak jak je dohledal import
     *
     * @return bool jestli se něco opravdu změnilo — import podle toho počítá dotčené řádky
     *
     * @throws \RuntimeException když noci neprojdou validací; import si to překládá na `Chyba`
     */
    public function ulozNociUcastnika(
        int $idUzivatele,
        array $idsPredmetuUbytovani,
        int $rok,
        bool $povolitJednuNoc,
        ?string $spolubydlici = null,
    ): bool {
        $zakaznik = $this->entityManager->find(User::class, $idUzivatele);
        if ($zakaznik === null) {
            throw new \RuntimeException(sprintf('Účastník %d neexistuje.', $idUzivatele));
        }

        $pred = $this->drzeneNoci($idUzivatele, $rok);
        $spolubydliciPred = $this->spolubydlici($idUzivatele);

        $this->accommodationWriter->save(
            $zakaznik,
            $this->idsVariant($idsPredmetuUbytovani),
            $rok,
            $povolitJednuNoc,
            $spolubydlici,
            // Import ubytování neřeší „nechci ubytování"; tu volbu si účastník drží sám a
            // prázdný řádek v souboru znamená „nic nemá", ne „odmítl".
            declined: false,
            // Pult smí posadit i na plnou noc — import je jeho nástroj a data v souboru už
            // jsou rozhodnutá, jen se zapisují.
            mayOverbook: true,
        );

        return $pred !== $this->drzeneNoci($idUzivatele, $rok)
            || ($spolubydlici !== null && $spolubydliciPred !== trim($spolubydlici));
    }

    /**
     * @return int[] seřazená id variant, aby šla dvě volání porovnat
     */
    private function drzeneNoci(int $idUzivatele, int $rok): array
    {
        $noci = $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT shop_nakupy.variant_id
             FROM shop_nakupy
             JOIN product_variant ON product_variant.id = shop_nakupy.variant_id
             WHERE shop_nakupy.id_uzivatele = :u
               AND shop_nakupy.rok = :rok
               AND product_variant.accommodation_day IS NOT NULL',
            [
                'u'   => $idUzivatele,
                'rok' => $rok,
            ],
        );
        $noci = array_map('intval', $noci);
        sort($noci);

        return $noci;
    }

    private function spolubydlici(int $idUzivatele): string
    {
        return (string) $this->entityManager->getConnection()->fetchOne(
            'SELECT ubytovan_s FROM uzivatele_hodnoty WHERE id_uzivatele = :u',
            [
                'u' => $idUzivatele,
            ],
        );
    }

    /**
     * @param int[] $idsPredmetu
     *
     * @return int[]
     */
    private function idsVariant(array $idsPredmetu): array
    {
        $idsPredmetu = array_values(array_filter(array_map('intval', $idsPredmetu)));
        if ($idsPredmetu === []) {
            return [];
        }

        $kody = $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT kod_predmetu FROM shop_predmety WHERE id_predmetu IN (:ids)',
            [
                'ids' => $idsPredmetu,
            ],
            [
                'ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER,
            ],
        );

        $varianty = $this->entityManager->getRepository(ProductVariant::class)
            ->findBy([
                'code' => $kody,
            ]);

        $ids = [];
        foreach ($varianty as $varianta) {
            if ($varianta->getId() !== null) {
                $ids[] = $varianta->getId();
            }
        }

        if (count($ids) !== count($idsPredmetu)) {
            throw new \RuntimeException('Některou noc se nepodařilo dohledat mezi variantami ubytování.');
        }

        return $ids;
    }
}

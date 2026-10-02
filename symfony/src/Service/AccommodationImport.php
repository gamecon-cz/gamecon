<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Exception\InvalidRequestException;
use App\Exception\UserFacingException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Zápis jednoho řádku importu ubytování z Excelu do nové vrstvy.
 *
 * Import posílá **id předmětů** (legacy `shop_predmety`), kdežto `AccommodationWriter` chce
 * **id variant**. U ubytování to není totéž: varianta nese týž `kod_predmetu` jako řádek své
 * noci, ale rodičem variant je jedna konkrétní noc (neděle), takže přes `product_id` by se
 * dohledala cizí noc.
 *
 * Celý řádek importu musí jet po spojení Doctrine, čtení i zápisy. `ubytovani` i `shop_nakupy`
 * mají cizí klíč na `uzivatele_hodnoty`, takže si každý zápis bere sdílený zámek na řádku
 * účastníka; kdyby část řádku psalo legacy ve vlastní transakci na vlastním spojení, druhá
 * půlka by na tom zámku uvízla až do timeoutu.
 *
 * Stojí to na `use_savepoints: true` v `doctrine.yaml`: `AccommodationWriter` si otvírá vlastní
 * transakci a bez savepointů by jeho rollback označil celou vnější za rollback-only, takže by
 * na ní uvázl i další řádek importu.
 */
readonly class AccommodationImport
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccommodationWriter $accommodationWriter,
        private CapacityManager $capacityManager,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Noci jednoho typu pokoje podle „typu" z reportu, což je kód noci bez třípísmenné
     * přípony dne (`3L_ct` → `3L`).
     *
     * @param int[] $dny
     *
     * @return int[] id variant, jedno pro každý žádaný den
     *
     * @throws InvalidRequestException když se pro některý den noc nenajde
     */
    public function dejIdsNociPodleTypu(string $kodTypu, array $dny, int $rok): array
    {
        $dny = array_values(array_unique(array_map('intval', $dny)));
        if ($dny === []) {
            return [];
        }

        $ids = $this->entityManager->getConnection()->fetchFirstColumn(
            // Porovnání drží české řazení, jak to dělalo legacy — bez něj by se rozešla
            // shoda kódů s diakritikou.
            'SELECT product_variant.id
             FROM product_variant
             INNER JOIN shop_predmety_s_typem AS typ_pokoje ON typ_pokoje.id_predmetu = product_variant.product_id
             WHERE LEFT(product_variant.code, CHAR_LENGTH(product_variant.code) - 3) = :kodTypu COLLATE utf8mb4_czech_ci
               AND typ_pokoje.typ = :typ
               AND typ_pokoje.model_rok = :rok
               AND product_variant.accommodation_day IN (:dny)
             ORDER BY product_variant.accommodation_day',
            [
                'kodTypu' => $kodTypu,
                'typ'     => \Gamecon\Shop\TypPredmetu::UBYTOVANI,
                'rok'     => $rok,
                'dny'     => $dny,
            ],
            [
                'dny' => \Doctrine\DBAL\ArrayParameterType::INTEGER,
            ],
        );

        $ids = array_map('intval', $ids);
        if (count($ids) !== count($dny)) {
            throw new InvalidRequestException($this->translator->trans('accommodation_import.type_not_unique', [
                '%type%' => $kodTypu, '%days%' => $this->translator->trans('accommodation_import.days', [
                    '%count%' => count($dny),
                ], 'errors'), '%found%' => count($ids),
            ], 'errors'));
        }

        return $ids;
    }

    public function zacniTransakci(): void
    {
        $this->capacityManager->beginSaleTransaction();
    }

    public function potvrdTransakci(): void
    {
        $this->entityManager->getConnection()->commit();
    }

    /**
     * Na rozdíl od potvrzení se tu na běžící transakci ptáme: rollback volá `catch`, který
     * chytá i výjimku ze samotného `zacniTransakci()`, kdy ještě žádná neběží.
     */
    public function vratTransakci(): void
    {
        $connection = $this->entityManager->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
    }

    /**
     * Pokoj na každou noc rozsahu; prázdný pokoj nebo prázdný rozsah přiřazení ruší.
     *
     * @return int kolik datových řádků se změnilo
     *
     * @throws InvalidRequestException když je zadaná jen jedna mez rozsahu
     */
    public function ulozPokoj(
        int $idUzivatele,
        string $pokoj,
        ?int $prvniNoc,
        ?int $posledniNoc,
        int $rok,
    ): int {
        $connection = $this->entityManager->getConnection();

        if ($pokoj === '' || ($prvniNoc === null && $posledniNoc === null)) {
            return (int) $connection->executeStatement(
                'DELETE FROM ubytovani WHERE id_uzivatele = :idUzivatele AND rok = :rok',
                [
                    'idUzivatele' => $idUzivatele,
                    'rok'         => $rok,
                ],
            );
        }

        if ($prvniNoc === null || $posledniNoc === null) {
            throw new InvalidRequestException($this->translator->trans('accommodation_import.incomplete_night_range', [
                '%first%' => $prvniNoc ?? '', '%last%' => $posledniNoc ?? '',
            ], 'errors'));
        }

        $dny = range($prvniNoc, $posledniNoc);
        $zmenenychRadku = 0;
        foreach ($dny as $den) {
            $zmenenychRadku += (int) $connection->executeStatement(
                'INSERT INTO ubytovani (id_uzivatele, den, pokoj, rok)
                 VALUES (:idUzivatele, :den, :pokoj, :rok)
                 ON DUPLICATE KEY UPDATE pokoj = :pokoj',
                [
                    'idUzivatele' => $idUzivatele,
                    'den'         => $den,
                    'pokoj'       => $pokoj,
                    'rok'         => $rok,
                ],
            );
        }

        $zmenenychRadku += (int) $connection->executeStatement(
            'DELETE FROM ubytovani WHERE id_uzivatele = :idUzivatele AND den NOT IN (:dny) AND rok = :rok',
            [
                'idUzivatele' => $idUzivatele,
                'dny'         => $dny,
                'rok'         => $rok,
            ],
            [
                'dny' => \Doctrine\DBAL\ArrayParameterType::INTEGER,
            ],
        );

        return $zmenenychRadku;
    }

    /**
     * Osobní údaje, které report veze vedle ubytování. Zapisují se tu, a ne přes legacy
     * `Uzivatel`, protože jedou uvnitř téže transakce jako noci.
     *
     * @param string|null $statniObcanstvi `null` = sloupec v reportu není, nech ho být
     * @param string|null $cisloDokladu    plaintext; `null` = sloupec v reportu není
     *
     * @return int kolik datových řádků se změnilo
     */
    public function ulozOsobniUdaje(
        int $idUzivatele,
        ?string $statniObcanstvi = null,
        ?string $cisloDokladu = null,
        ?string $typDokladuTotoznosti = null,
    ): int {
        $connection = $this->entityManager->getConnection();
        $puvodni = $connection->fetchAssociative(
            'SELECT statni_obcanstvi, op, typ_dokladu_totoznosti
             FROM uzivatele_hodnoty WHERE id_uzivatele = :idUzivatele',
            [
                'idUzivatele' => $idUzivatele,
            ],
        ) ?: [];

        $noveHodnoty = [];
        if ($statniObcanstvi !== null) {
            $noveHodnoty['statni_obcanstvi'] = trim($statniObcanstvi);
        }
        if ($typDokladuTotoznosti !== null) {
            $noveHodnoty['typ_dokladu_totoznosti'] = $typDokladuTotoznosti;
        }
        if ($cisloDokladu !== null) {
            // `op` se šifruje nedeterministicky, takže porovnat jde jen plaintexty — dvě
            // zašifrování téhož čísla se liší a vypadala by jako změna.
            $puvodniZasifrovane = (string) ($puvodni['op'] ?? '');
            $puvodniCislo = $puvodniZasifrovane !== ''
                ? \Sifrovatko::desifruj($puvodniZasifrovane)
                : '';
            if ($puvodniCislo !== $cisloDokladu) {
                $noveHodnoty['op'] = $cisloDokladu !== ''
                    ? \Sifrovatko::zasifruj($cisloDokladu)
                    : '';
            }
        }

        $zmeneneSloupce = [];
        foreach ($noveHodnoty as $sloupec => $nova) {
            if ($sloupec !== 'op' && (string) ($puvodni[$sloupec] ?? '') === (string) $nova) {
                continue;
            }
            $zmeneneSloupce[$sloupec] = $nova;
        }
        if ($zmeneneSloupce === []) {
            return 0;
        }

        $nastaveni = implode(', ', array_map(
            static fn (string $sloupec): string => $sloupec . ' = :' . $sloupec,
            array_keys($zmeneneSloupce),
        ));
        $zmenenychRadku = (int) $connection->executeStatement(
            'UPDATE uzivatele_hodnoty SET ' . $nastaveni . ' WHERE id_uzivatele = :idUzivatele',
            $zmeneneSloupce + [
                'idUzivatele' => $idUzivatele,
            ],
        );

        $this->zalogujOsobniUdaje($idUzivatele, $zmeneneSloupce, $puvodni);

        return $zmenenychRadku;
    }

    /**
     * Zdroj změny se schválně nevyplňuje — legacy settery ho nechávaly prázdný, takže by se
     * jinak audit po převodu rozešel.
     *
     * @param array<string, string> $zmeneneSloupce
     * @param array<string, mixed>  $puvodni
     */
    private function zalogujOsobniUdaje(int $idUzivatele, array $zmeneneSloupce, array $puvodni): void
    {
        // Číslo dokladu má vlastní logovací cestu, protože se do logu ukládá zašifrované,
        // ale rozhoduje se podle plaintextu.
        if (isset($zmeneneSloupce['op'])) {
            \Uzivatel::zalogujZmenuOp(
                $idUzivatele,
                (string) ($puvodni['op'] ?? ''),
                $zmeneneSloupce['op'] !== '' ? \Sifrovatko::desifruj($zmeneneSloupce['op']) : '',
            );
        }

        $ostatni = array_diff_key($zmeneneSloupce, [
            'op' => true,
        ]);
        if ($ostatni !== []) {
            \Uzivatel::zalogujZmenuOsobnichUdaju($idUzivatele, $ostatni, $puvodni);
        }
    }

    /**
     * @param int[] $idsVariantUbytovani noci, jak je dohledal `dejIdsNociPodleTypu()`
     *
     * @return int kolik datových řádků se změnilo — tutéž veličinu hlásily legacy metody
     *             přes `dbAffectedOrNumRows()`, takže import počítá dál stejně
     *
     * @throws UserFacingException když noci neprojdou validací; import si to překládá na `Chyba`
     */
    public function ulozNociUcastnika(
        int $idUzivatele,
        array $idsVariantUbytovani,
        int $rok,
        bool $povolitJednuNoc,
        ?string $spolubydlici = null,
    ): int {
        $zakaznik = $this->entityManager->find(User::class, $idUzivatele);
        if ($zakaznik === null) {
            throw new InvalidRequestException($this->translator->trans('accommodation_import.participant_not_found', [
                '%id%' => $idUzivatele,
            ], 'errors'));
        }

        return $this->accommodationWriter->save(
            $zakaznik,
            $idsVariantUbytovani,
            $rok,
            $povolitJednuNoc,
            $spolubydlici,
            // Import ubytování neřeší „nechci ubytování"; tu volbu si účastník drží sám a
            // prázdný řádek v souboru znamená „nic nemá", ne „odmítl". `null` proto sloupec
            // nechá být, jak ho nechávalo legacy.
            declined: null,
            // Pult smí posadit i na plnou noc — import je jeho nástroj a data v souboru už
            // jsou rozhodnutá, jen se zapisují.
            mayOverbook: true,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\State\Kfc;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Kfc\KfcSaleInputDto;
use App\Dto\Kfc\KfcSaleOutputDto;
use App\Entity\Payment;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Service\CartService;
use App\Service\CurrentYearProviderInterface;
use App\Service\OperatorOverride;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Processes KFC point-of-sale purchases.
 *
 * @implements ProcessorInterface<KfcSaleInputDto, KfcSaleOutputDto>
 */
readonly class KfcSaleProcessor implements ProcessorInterface
{
    /**
     * A walk-up buyer has no account, so the purchase is booked on the SYSTEM user. NULL was
     * tried and caused problems; see docs/generated/prodej-na-pultu-kfc.md.
     */
    private const ID_SYSTEMOVEHO_UZIVATELE = 1;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CartService $cartService,
        private CurrentYearProviderInterface $yearProvider,
        private Security $security,
        private ClockInterface $clock,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): KfcSaleOutputDto
    {
        $operator = $this->security->getUser();
        if (! $operator instanceof User) {
            throw new \RuntimeException('Prodej na pultu musí provádět přihlášený uživatel.');
        }

        $kupujici = $this->entityManager->find(User::class, self::ID_SYSTEMOVEHO_UZIVATELE);
        if ($kupujici === null) {
            throw new \RuntimeException('Systémový uživatel neexistuje, nelze zaúčtovat anonymní prodej.');
        }

        $rok = $this->yearProvider->getCurrentYear();
        $override = OperatorOverride::deskSale($operator);
        $prodanoKusu = 0;
        $celkem = '0.00';

        // Předměty se hledají před transakcí: výjimka uvnitř wrapInTransaction zavře
        // EntityManager, takže by se z „neznámý produkt" stala nesrozumitelná chyba 500.
        $kZaplaceni = [];
        foreach ($data->items as $saleItem) {
            $kZaplaceni[] = [$this->dejVariantu($saleItem->productId), $saleItem->quantity];
        }

        $this->entityManager->wrapInTransaction(function () use ($kZaplaceni, $kupujici, $operator, $override, $rok, &$prodanoKusu, &$celkem): void {
            $kosik = $this->cartService->getOrCreateCart($kupujici);

            foreach ($kZaplaceni as [$varianta, $pocetKusu]) {
                // Řádek na kus, stejně jako legacy prodej — každý kus je vlastní nákup.
                for ($kus = 0; $kus < $pocetKusu; ++$kus) {
                    $polozka = $this->cartService->addItem($kosik, $varianta, override: $override);
                    $celkem = bcadd($celkem, $polozka->getPurchasePrice(), 2);
                    ++$prodanoKusu;
                }
            }

            // Bez připsání zůstane v shop_nakupy pohledávka za SYSTEM, kterou nikdo nezaplatil,
            // a jeho dluh roste s každým dalším prodejem na pultu.
            if ($prodanoKusu > 0) {
                $this->entityManager->persist($this->zaplaceno($kupujici, $operator, $this->kZaplaceni($celkem), $rok));
            }
        });

        return new KfcSaleOutputDto(
            soldItems: $prodanoKusu,
            totalPrice: $this->kZaplaceni($celkem),
        );
    }

    /**
     * Na pultu se platí v celých korunách — drobné se tam nevydávají. Připsaná platba musí
     * sedět na tutéž částku, jinak by se pokladna a účetnictví rozešly o haléře, jakmile
     * se objeví procentní sleva. Zaokrouhluje se nahoru od poloviny, ne ořezává: ořez by
     * u 42,99 účtoval 42.
     */
    private function kZaplaceni(string $celkem): string
    {
        return bcadd($celkem, '0.5', 0);
    }

    /**
     * Prodejní jednotkou je v novém schématu varianta; běžný předmět z pultu má právě jednu.
     */
    private function dejVariantu(int $idPredmetu): ProductVariant
    {
        $predmet = $this->entityManager->find(Product::class, $idPredmetu);
        if ($predmet === null) {
            throw new \RuntimeException(sprintf('Produkt s ID %d nebyl nalezen.', $idPredmetu));
        }

        $varianty = $predmet->getVariants();
        if ($varianty->isEmpty()) {
            throw new \RuntimeException(sprintf('Produkt "%s" nemá žádnou variantu k prodeji.', $predmet->getName()));
        }
        // Pult umí prodat jen jednoznačný předmět. U víc variant (velikosti, noci) by výběr
        // té první znamenal tiše prodat něco jiného, než si zákazník vzal z pultu.
        if ($varianty->count() > 1) {
            throw new \RuntimeException(sprintf('Produkt "%s" má víc variant, vyber konkrétní.', $predmet->getName()));
        }

        return $varianty->first();
    }

    private function zaplaceno(User $kupujici, User $operator, string $castka, int $rok): Payment
    {
        $platba = new Payment();
        $platba->setBeneficiary($kupujici);
        $platba->setMadeBy($operator);
        $platba->setCastka($castka);
        $platba->setRok($rok);
        $platba->setProvedeno(\DateTime::createFromImmutable($this->clock->now()));
        // Stejný text jako legacy prodej, aby obě cesty psaly srovnatelný řádek.
        $platba->setPoznamka('anonymní prodej');

        return $platba;
    }
}

<?php

declare(strict_types=1);

namespace App\State\Kfc;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Kfc\KfcSaleInputDto;
use App\Dto\Kfc\KfcSaleOutputDto;
use App\Entity\Order;
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
     * A walk-up buyer has no account of their own, so the sale is booked on a shared account
     * that deliberately carries no roles — see docs/generated/prodej-na-pultu-kfc.md.
     */
    private const LOGIN_ANONYMNIHO_KUPUJICIHO = 'ANONYM';

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

        $kupujici = $this->entityManager->getRepository(User::class)
            ->findOneBy([
                'login' => self::LOGIN_ANONYMNIHO_KUPUJICIHO,
            ]);
        if ($kupujici === null) {
            throw new \RuntimeException('Anonymní kupující neexistuje, nelze zaúčtovat prodej na pultu.');
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
            // Vlastní objednávka na každý prodej, hned uzavřená. getOrCreateCart() vrací
            // *otevřený* košík, takže by se do jednoho nasčítal celý festival: každá platba
            // by pak ukazovala na tutéž objednávku a nešlo by poznat, ke kterému prodeji patří.
            //
            // Zakládá se až u prvního kusu: prázdný prodej by jinak po sobě nechal otevřenou
            // objednávku bez řádků, a stačí dvě takové, aby hledání košíku skončilo výjimkou.
            $kosik = null;

            foreach ($kZaplaceni as [$varianta, $pocetKusu]) {
                // Řádek na kus, stejně jako legacy prodej — každý kus je vlastní nákup.
                for ($kus = 0; $kus < $pocetKusu; ++$kus) {
                    if ($kosik === null) {
                        $kosik = new Order();
                        $kosik->setCustomer($kupujici);
                        $kosik->setYear($rok);
                        $this->entityManager->persist($kosik);
                    }

                    $polozka = $this->cartService->addItem($kosik, $varianta, override: $override);

                    // Zaokrouhlí se rovnou účtovaná cena, ne až součet: pult bere celé
                    // koruny, a kdyby si nákup nechal haléře, zůstal by po každém prodeji
                    // na účtu nedoplatek, který nikdo nikdy nezaplatí.
                    $polozka->setPurchasePrice($this->naCeleKoruny($polozka->getPurchasePrice()));
                    $celkem = bcadd($celkem, $polozka->getPurchasePrice(), 2);
                    ++$prodanoKusu;
                }
            }

            // Bez připsání zůstane v shop_nakupy pohledávka, kterou nikdo nezaplatil, a dluh
            // anonymního účtu roste s každým dalším prodejem na pultu.
            if ($kosik !== null) {
                // Až po zaokrouhlení: addItem() si součet spočítal z původní ceny s haléři,
                // takže bez tohohle by objednávka nesouhlasila s vlastními řádky ani s platbou.
                $kosik->recalculateTotal();
                $kosik->complete();
                $this->entityManager->persist(
                    $this->zaplaceno($kupujici, $operator, $celkem, $rok, $kosik),
                );
            }
        });

        return new KfcSaleOutputDto(
            soldItems: $prodanoKusu,
            // bcadd(…, 0) ořezává, ne zaokrouhluje — spoléhá na to, že každá položka už je
            // v celých korunách. Kdyby sem někdy přitekla nezaokrouhlená částka, pokladna
            // ukáže až o korunu míň, než kolik se připsalo.
            totalPrice: bcadd($celkem, '0', 0),
        );
    }

    /**
     * Zaokrouhluje nahoru od poloviny, ne ořezává: ořez by u 42,99 účtoval 42.
     */
    private function naCeleKoruny(string $castka): string
    {
        return bcadd($castka, '0.5', 0);
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
        // isEmpty() se na nenačtené kolekci zodpoví COUNTem, first() by ji celou zhydratoval.
        if ($varianty->isEmpty()) {
            throw new \RuntimeException(sprintf('Produkt "%s" nemá žádnou variantu k prodeji.', $predmet->getName()));
        }
        // Pult umí prodat jen jednoznačný předmět. U víc variant (velikosti, noci) by výběr
        // té první znamenal tiše prodat něco jiného, než si zákazník vzal z pultu.
        if ($varianty->count() > 1) {
            throw new \RuntimeException(sprintf('Produkt "%s" má víc variant, vyber konkrétní.', $predmet->getName()));
        }

        // PHPStan z isEmpty() výše odvodí, že tady už false přijít nemůže.
        return $varianty->first();
    }

    private function zaplaceno(User $kupujici, User $operator, string $castka, int $rok, Order $objednavka): Payment
    {
        $platba = new Payment();
        // Vazba na objednávku: bez ní po nedokončeném prodeji zůstane platba viset a nikdo
        // se to nedozví, protože ji s prodejem nic nespojuje.
        $platba->setOrder($objednavka);
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

<?php

declare(strict_types=1);

namespace App\State\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\Cart\MealProductOutputDto;
use App\Entity\User;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Repository\ProductRepository;
use App\Service\CurrentYearProviderInterface;
use App\Service\CustomerDeskRights;
use App\Service\DiscountCalculator;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<MealProductOutputDto>
 */
readonly class MealProductsProvider implements ProviderInterface
{
    public function __construct(
        private ProductRepository $productRepository,
        private DiscountCalculator $discountCalculator,
        private CurrentYearProviderInterface $currentYearProvider,
        private Security $security,
        private CustomerDeskRights $deskRights,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return MealProductOutputDto[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        // Katalog je pro všechny stejný, takže pult chodí na týž endpoint. Liší se jen tím,
        // že smí objednávat po termínu — pozná se podle `?customerId`, které posílá jen
        // matice v adminu. Samotný parametr nestačí, jinak by si ho účastník dopsal do URL.
        $zPultu = ($context['filters']['customerId'] ?? null) !== null
            && $this->deskRights->jeObsluhaPultu();

        return $this->proZakaznika($zPultu);
    }

    /**
     * @param bool $zPultu volá to obsluha za účastníka — pak termín prodeje neplatí
     *
     * @return MealProductOutputDto[]
     */
    private function proZakaznika(bool $zPultu): array
    {
        $products = $this->productRepository->findByTag(ProductTagCode::JIDLO);
        $user = $this->security->getUser();
        $year = $this->currentYearProvider->getCurrentYear();
        // Zamyká se jen účastníkovi; pult po termínu doobjednat smí a chodí mimo košík,
        // přes `MealWriter`. Zámek je jen nápověda pro matici — vynucuje ho `CartService`.
        $poTerminuKategorie = ! $zPultu && SystemoveNastaveni::zGlobals()->prodejJidlaUkoncen();
        $meals = [];

        foreach ($products as $product) {
            $variants = $product->getVariants();
            if ($variants->isEmpty()) {
                continue;
            }

            $variant = $variants->first();
            if ($variant->getId() === null) {
                continue;
            }

            $dto = MealProductOutputDto::fromProductAndVariant($product, $variant);

            // Sleva orga na jídlo visí na právu, takže ceníková cena nestačí — v matici
            // musí být, co účastník reálně zaplatí, stejně jako u merche a ubytování.
            //
            // Sleva se počítá z ceny produktu, kdežto DTO vychází z ceny varianty. Dnes
            // se shodují (žádná varianta jídla vlastní cenu nemá), ale kdyby ji dostala,
            // odečetlo by se od jiného základu — proto se přepisuje jen tehdy, když jsou
            // stejné, a jinak zůstane cena varianty.
            if ($user instanceof User && $variant->getEffectivePrice() === $product->getCurrentPrice()) {
                $dto->price = $this->discountCalculator->calculateDiscount($product, $user, $year)['finalPrice'];
                $dto->priceSteps = $this->discountCalculator->priceSteps($product, $user, $year);
            }

            // Stažený produkt neprodá nikdo, ani pult. Propadlé `nabizet_do` je proti tomu
            // jen konec samoobsluhy — legacy ho pultu povoluje přes `jidloBezZamku`, které
            // si obě admin obrazovky zapínají, takže se tady chová stejně jako termín
            // kategorie. Stav RESTRICTED/SUSPENDED řeší varianty, ne tenhle zámek.
            $stazeno = $product->isArchived() || $product->getState() === ProductStateEnum::RETIRED;
            $dto->locked = $stazeno
                || $poTerminuKategorie
                || (! $zPultu && ! $product->isAvailable($this->clock->now()));
            $meals[] = $dto;
        }

        return $meals;
    }
}

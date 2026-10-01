<?php

declare(strict_types=1);

namespace App\State\Cart;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Cart\AddToCartInputDto;
use App\Dto\Cart\CartOutputDto;
use App\Entity\ProductBundle;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @implements ProcessorInterface<AddToCartInputDto, CartOutputDto>
 */
readonly class AddToCartProcessor implements ProcessorInterface
{
    public function __construct(
        private CartService $cartService,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private TranslatorInterface $translator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CartOutputDto
    {
        $user = $this->security->getUser();
        assert($user instanceof User);

        $roleMeanings = $user->getRoleMeanings();
        $cart = $this->cartService->getOrCreateCart($user);

        if ($data->bundleId !== null) {
            $bundle = $this->entityManager->find(ProductBundle::class, $data->bundleId);
            if ($bundle === null) {
                throw new NotFoundHttpException($this->translator->trans('cart.bundle_not_found', [
                    '%id%' => $data->bundleId,
                ], 'errors'));
            }

            $this->cartService->addBundle($cart, $bundle, $roleMeanings);
        } else {
            if ($data->variantId === null) {
                throw new BadRequestHttpException($this->translator->trans('cart.variant_or_bundle_required', [], 'errors'));
            }

            $variant = $this->entityManager->find(ProductVariant::class, $data->variantId);
            if ($variant === null) {
                throw new NotFoundHttpException($this->translator->trans('cart.variant_not_found', [
                    '%id%' => $data->variantId,
                ], 'errors'));
            }

            $this->cartService->addItem($cart, $variant, $roleMeanings);
        }

        return CartOutputDto::fromOrder($cart);
    }
}

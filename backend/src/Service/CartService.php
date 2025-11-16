<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\CatalogItem;
use App\Entity\User;
use App\Repository\CartRepository;
use App\Repository\CatalogItemRepository;
use Doctrine\ORM\EntityManagerInterface;

class CartService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CartRepository $cartRepository,
        private CatalogItemRepository $catalogItemRepository
    ) {
    }

    public function getOrCreateCart(User $user): Cart
    {
        return $this->cartRepository->getOrCreateCart($user);
    }

    public function addItem(User $user, int $catalogItemId, int $quantity = 1): CartItem
    {
        $cart = $this->getOrCreateCart($user);
        $catalogItem = $this->catalogItemRepository->find($catalogItemId);

        if (!$catalogItem) {
            throw new \RuntimeException('Catalog item not found');
        }

        if (!$catalogItem->isAvailable()) {
            throw new \RuntimeException('Item is not available');
        }

        // Check if item already exists in cart
        foreach ($cart->getItems() as $existingItem) {
            if ($existingItem->getCatalogItem()->getId() === $catalogItemId) {
                $existingItem->setQuantity($existingItem->getQuantity() + $quantity);
                $this->entityManager->flush();
                return $existingItem;
            }
        }

        // Create new cart item
        $cartItem = new CartItem();
        $cartItem->setCart($cart);
        $cartItem->setCatalogItem($catalogItem);
        $cartItem->setQuantity($quantity);

        $this->entityManager->persist($cartItem);
        $this->entityManager->flush();

        return $cartItem;
    }

    public function updateItemQuantity(User $user, int $cartItemId, int $quantity): CartItem
    {
        $cart = $this->getOrCreateCart($user);
        $cartItem = null;

        foreach ($cart->getItems() as $item) {
            if ($item->getId() === $cartItemId) {
                $cartItem = $item;
                break;
            }
        }

        if (!$cartItem) {
            throw new \RuntimeException('Cart item not found');
        }

        if ($quantity <= 0) {
            $cart->removeItem($cartItem);
            $this->entityManager->remove($cartItem);
        } else {
            $cartItem->setQuantity($quantity);
        }

        $this->entityManager->flush();
        return $cartItem;
    }

    public function removeItem(User $user, int $cartItemId): void
    {
        $cart = $this->getOrCreateCart($user);

        foreach ($cart->getItems() as $item) {
            if ($item->getId() === $cartItemId) {
                $cart->removeItem($item);
                $this->entityManager->remove($item);
                $this->entityManager->flush();
                return;
            }
        }

        throw new \RuntimeException('Cart item not found');
    }

    public function clearCart(User $user): void
    {
        $cart = $this->getOrCreateCart($user);

        foreach ($cart->getItems() as $item) {
            $this->entityManager->remove($item);
        }

        $cart->getItems()->clear();
        $this->entityManager->flush();
    }

    public function updateCartDetails(User $user, array $data): Cart
    {
        $cart = $this->getOrCreateCart($user);

        if (isset($data['internalNote'])) {
            $cart->setInternalNote($data['internalNote']);
        }

        if (isset($data['noteToSupplier'])) {
            $cart->setNoteToSupplier($data['noteToSupplier']);
        }

        if (isset($data['hidePrice'])) {
            $cart->setHidePrice($data['hidePrice']);
        }

        if (isset($data['deliveryAddress'])) {
            $cart->setDeliveryAddress($data['deliveryAddress']);
        }

        if (isset($data['locationCode'])) {
            $cart->setLocationCode($data['locationCode']);
        }

        if (isset($data['phone'])) {
            $cart->setPhone($data['phone']);
        }

        if (isset($data['attentionTo'])) {
            $cart->setAttentionTo($data['attentionTo']);
        }

        if (isset($data['specialDeliveryInstructions'])) {
            $cart->setSpecialDeliveryInstructions($data['specialDeliveryInstructions']);
        }

        if (isset($data['requiresCommodityApproval'])) {
            $cart->setRequiresCommodityApproval($data['requiresCommodityApproval']);
        }

        if (isset($data['prescribedCommodity'])) {
            $cart->setPrescribedCommodity($data['prescribedCommodity']);
        }

        if (isset($data['hedgedRate'])) {
            $cart->setHedgedRate($data['hedgedRate']);
        }

        if (isset($data['attachments'])) {
            $cart->setAttachments($data['attachments']);
        }

        $cart->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $cart;
    }
}

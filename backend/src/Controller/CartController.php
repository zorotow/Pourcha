<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/cart')]
#[IsGranted('ROLE_USER')]
class CartController extends AbstractController
{
    public function __construct(
        private CartService $cartService
    ) {
    }

    #[Route('', name: 'api_cart_get', methods: ['GET'])]
    public function getCart(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $cart = $this->cartService->getOrCreateCart($user);

        $items = array_map(fn($item) => [
            'id' => $item->getId(),
            'catalogItem' => [
                'id' => $item->getCatalogItem()->getId(),
                'name' => $item->getCatalogItem()->getName(),
                'supplierPartNumber' => $item->getCatalogItem()->getSupplierPartNumber(),
                'supplier' => [
                    'id' => $item->getCatalogItem()->getSupplier()->getId(),
                    'name' => $item->getCatalogItem()->getSupplier()->getName(),
                ],
                'unit' => $item->getCatalogItem()->getUnit(),
                'imageUrl' => $item->getCatalogItem()->getImageUrl(),
            ],
            'quantity' => $item->getQuantity(),
            'unitPrice' => $item->getUnitPrice(),
            'subtotal' => $item->getSubtotal(),
            'chartOfAccounts' => $item->getChartOfAccounts() ? [
                'id' => $item->getChartOfAccounts()->getId(),
                'fullCode' => $item->getChartOfAccounts()->getFullCode(),
                'fullName' => $item->getChartOfAccounts()->getFullName(),
            ] : null,
            'paymentTerms' => $item->getPaymentTerms(),
        ], $cart->getItems()->toArray());

        return $this->json([
            'id' => $cart->getId(),
            'status' => $cart->getStatus(),
            'items' => $items,
            'total' => $cart->getTotal(),
            'internalNote' => $cart->getInternalNote(),
            'noteToSupplier' => $cart->getNoteToSupplier(),
            'hidePrice' => $cart->isHidePrice(),
            'deliveryAddress' => $cart->getDeliveryAddress(),
            'locationCode' => $cart->getLocationCode(),
            'phone' => $cart->getPhone(),
            'attentionTo' => $cart->getAttentionTo(),
            'specialDeliveryInstructions' => $cart->getSpecialDeliveryInstructions(),
            'requiresCommodityApproval' => $cart->isRequiresCommodityApproval(),
            'prescribedCommodity' => $cart->getPrescribedCommodity(),
            'hedgedRate' => $cart->getHedgedRate(),
            'attachments' => $cart->getAttachments(),
        ]);
    }

    #[Route('/items', name: 'api_cart_add_item', methods: ['POST'])]
    public function addItem(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $catalogItemId = $data['catalogItemId'] ?? null;
        $quantity = $data['quantity'] ?? 1;

        if (!$catalogItemId) {
            return $this->json(['error' => 'catalogItemId is required'], 400);
        }

        try {
            $cartItem = $this->cartService->addItem($user, $catalogItemId, $quantity);

            return $this->json([
                'message' => 'Item added to cart',
                'cartItem' => [
                    'id' => $cartItem->getId(),
                    'quantity' => $cartItem->getQuantity(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/items/{id}', name: 'api_cart_update_item', methods: ['PUT'])]
    public function updateItem(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $quantity = $data['quantity'] ?? null;

        if ($quantity === null) {
            return $this->json(['error' => 'quantity is required'], 400);
        }

        try {
            $cartItem = $this->cartService->updateItemQuantity($user, $id, $quantity);

            return $this->json([
                'message' => 'Cart item updated',
                'cartItem' => [
                    'id' => $cartItem->getId(),
                    'quantity' => $cartItem->getQuantity(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/items/{id}', name: 'api_cart_remove_item', methods: ['DELETE'])]
    public function removeItem(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->cartService->removeItem($user, $id);

            return $this->json(['message' => 'Item removed from cart']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/clear', name: 'api_cart_clear', methods: ['POST'])]
    public function clearCart(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->cartService->clearCart($user);

        return $this->json(['message' => 'Cart cleared']);
    }

    #[Route('/details', name: 'api_cart_update_details', methods: ['PUT'])]
    public function updateDetails(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        try {
            $cart = $this->cartService->updateCartDetails($user, $data);

            return $this->json([
                'message' => 'Cart details updated',
                'cart' => [
                    'id' => $cart->getId(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}

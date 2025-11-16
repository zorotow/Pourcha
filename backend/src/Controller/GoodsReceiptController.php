<?php

namespace App\Controller;

use App\Service\GoodsReceiptService;
use App\Service\ThreeWayMatchingService;
use App\Repository\RequisitionRepository;
use App\Repository\GoodsReceiptRepository;
use App\Repository\InvoiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/goods-receipts')]
class GoodsReceiptController extends AbstractController
{
    public function __construct(
        private GoodsReceiptService $goodsReceiptService,
        private ThreeWayMatchingService $threeWayMatchingService,
        private GoodsReceiptRepository $goodsReceiptRepository,
        private RequisitionRepository $requisitionRepository,
        private InvoiceRepository $invoiceRepository,
    ) {
    }

    /**
     * List all goods receipts
     */
    #[Route('', name: 'api_goods_receipts_list', methods: ['GET'])]
    public function listReceipts(): JsonResponse
    {
        $user = $this->getUser();
        $receipts = $this->goodsReceiptRepository->findPendingReceipts($user->getTenant());

        $data = array_map(function ($receipt) {
            return [
                'id' => $receipt->getId(),
                'receipt_number' => $receipt->getReceiptNumber(),
                'requisition_number' => $receipt->getRequisition()->getRequisitionNumber(),
                'supplier' => [
                    'id' => $receipt->getSupplier()->getId(),
                    'name' => $receipt->getSupplier()->getName(),
                ],
                'status' => $receipt->getStatus(),
                'receipt_date' => $receipt->getReceiptDate()?->format('c'),
                'has_discrepancies' => $receipt->hasDiscrepancies(),
                'created_at' => $receipt->getCreatedAt()->format('c'),
            ];
        }, $receipts);

        return $this->json(['receipts' => $data]);
    }

    /**
     * Get goods receipt details
     */
    #[Route('/{id}', name: 'api_goods_receipts_get', methods: ['GET'])]
    public function getReceipt(int $id): JsonResponse
    {
        $receipt = $this->goodsReceiptRepository->find($id);

        if (!$receipt) {
            return $this->json(['error' => 'Goods receipt not found'], Response::HTTP_NOT_FOUND);
        }

        $items = [];
        foreach ($receipt->getItems() as $item) {
            $items[] = [
                'id' => $item->getId(),
                'requisition_item_id' => $item->getRequisitionItem()->getId(),
                'description' => $item->getRequisitionItem()->getDescription(),
                'ordered_quantity' => $item->getOrderedQuantity(),
                'received_quantity' => $item->getReceivedQuantity(),
                'accepted_quantity' => $item->getAcceptedQuantity(),
                'rejected_quantity' => $item->getRejectedQuantity(),
                'condition' => $item->getCondition(),
                'notes' => $item->getNotes(),
                'quality_checks' => $item->getQualityChecks(),
            ];
        }

        return $this->json([
            'id' => $receipt->getId(),
            'receipt_number' => $receipt->getReceiptNumber(),
            'requisition' => [
                'id' => $receipt->getRequisition()->getId(),
                'number' => $receipt->getRequisition()->getRequisitionNumber(),
            ],
            'supplier' => [
                'id' => $receipt->getSupplier()->getId(),
                'name' => $receipt->getSupplier()->getName(),
            ],
            'received_by' => [
                'id' => $receipt->getReceivedBy()->getId(),
                'name' => $receipt->getReceivedBy()->getFirstName() . ' ' . $receipt->getReceivedBy()->getLastName(),
            ],
            'status' => $receipt->getStatus(),
            'receipt_date' => $receipt->getReceiptDate()?->format('c'),
            'delivery_note_number' => $receipt->getDeliveryNoteNumber(),
            'delivery_note' => $receipt->getDeliveryNote(),
            'receiving_location' => $receipt->getReceivingLocation(),
            'notes' => $receipt->getNotes(),
            'has_discrepancies' => $receipt->hasDiscrepancies(),
            'discrepancies' => $receipt->getDiscrepancies(),
            'items' => $items,
            'confirmed_at' => $receipt->getConfirmedAt()?->format('c'),
            'created_at' => $receipt->getCreatedAt()->format('c'),
        ]);
    }

    /**
     * Create goods receipt from requisition
     */
    #[Route('/from-requisition/{requisitionId}', name: 'api_goods_receipts_create', methods: ['POST'])]
    public function createReceipt(int $requisitionId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $requisition = $this->requisitionRepository->find($requisitionId);

        if (!$requisition) {
            return $this->json(['error' => 'Requisition not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $data = json_decode($request->getContent(), true);
            $receipt = $this->goodsReceiptService->createFromRequisition($requisition, $user, $data);

            return $this->json([
                'message' => 'Goods receipt created successfully',
                'receipt_id' => $receipt->getId(),
                'receipt_number' => $receipt->getReceiptNumber(),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update goods receipt items
     */
    #[Route('/{id}/items', name: 'api_goods_receipts_update_items', methods: ['PUT'])]
    public function updateItems(int $id, Request $request): JsonResponse
    {
        $receipt = $this->goodsReceiptRepository->find($id);

        if (!$receipt) {
            return $this->json(['error' => 'Goods receipt not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $data = json_decode($request->getContent(), true);
            $this->goodsReceiptService->updateReceiptItems($receipt, $data['items'] ?? []);

            return $this->json(['message' => 'Items updated successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Confirm goods receipt
     */
    #[Route('/{id}/confirm', name: 'api_goods_receipts_confirm', methods: ['POST'])]
    public function confirmReceipt(int $id): JsonResponse
    {
        $receipt = $this->goodsReceiptRepository->find($id);

        if (!$receipt) {
            return $this->json(['error' => 'Goods receipt not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->goodsReceiptService->confirmReceipt($receipt);

            return $this->json(['message' => 'Goods receipt confirmed successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Perform 3-way matching
     */
    #[Route('/{id}/three-way-match', name: 'api_goods_receipts_three_way_match', methods: ['POST'])]
    public function performThreeWayMatch(int $id, Request $request): JsonResponse
    {
        $receipt = $this->goodsReceiptRepository->find($id);

        if (!$receipt) {
            return $this->json(['error' => 'Goods receipt not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $invoiceId = $data['invoice_id'] ?? null;

        if (!$invoiceId) {
            return $this->json(['error' => 'Invoice ID is required'], Response::HTTP_BAD_REQUEST);
        }

        $invoice = $this->invoiceRepository->find($invoiceId);

        if (!$invoice) {
            return $this->json(['error' => 'Invoice not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $result = $this->threeWayMatchingService->performMatch(
                $receipt->getRequisition(),
                $invoice,
                $receipt
            );

            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

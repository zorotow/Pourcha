<?php

namespace App\Service;

use App\Entity\GoodsReceipt;
use App\Entity\GoodsReceiptItem;
use App\Entity\Requisition;
use App\Entity\User;
use App\Repository\GoodsReceiptRepository;
use App\Repository\RequisitionRepository;
use Doctrine\ORM\EntityManagerInterface;

class GoodsReceiptService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GoodsReceiptRepository $goodsReceiptRepository,
        private RequisitionRepository $requisitionRepository,
        private NotificationService $notificationService,
    ) {
    }

    /**
     * Create goods receipt from requisition
     */
    public function createFromRequisition(Requisition $requisition, User $receivedBy, array $data = []): GoodsReceipt
    {
        if ($requisition->getStatus() !== Requisition::STATUS_APPROVED) {
            throw new \RuntimeException('Can only create goods receipt from approved requisitions');
        }

        $receipt = new GoodsReceipt();
        $receipt->setReceiptNumber($this->goodsReceiptRepository->generateReceiptNumber());
        $receipt->setRequisition($requisition);
        $receipt->setSupplier($requisition->getItems()->first()->getSupplier());
        $receipt->setReceivedBy($receivedBy);

        // Set optional fields from data
        if (isset($data['receipt_date'])) {
            $receipt->setReceiptDate(new \DateTimeImmutable($data['receipt_date']));
        }

        if (isset($data['delivery_note_number'])) {
            $receipt->setDeliveryNoteNumber($data['delivery_note_number']);
        }

        if (isset($data['delivery_note'])) {
            $receipt->setDeliveryNote($data['delivery_note']);
        }

        if (isset($data['receiving_location'])) {
            $receipt->setReceivingLocation($data['receiving_location']);
        }

        if (isset($data['notes'])) {
            $receipt->setNotes($data['notes']);
        }

        // Create receipt items from requisition items
        foreach ($requisition->getItems() as $reqItem) {
            $receiptItem = new GoodsReceiptItem();
            $receiptItem->setRequisitionItem($reqItem);
            $receiptItem->setOrderedQuantity((string) $reqItem->getQuantity());

            // If received quantities are provided, use them
            if (isset($data['items'][$reqItem->getId()])) {
                $itemData = $data['items'][$reqItem->getId()];

                if (isset($itemData['received_quantity'])) {
                    $receiptItem->setReceivedQuantity((string) $itemData['received_quantity']);
                }

                if (isset($itemData['accepted_quantity'])) {
                    $receiptItem->setAcceptedQuantity((string) $itemData['accepted_quantity']);
                }

                if (isset($itemData['rejected_quantity'])) {
                    $receiptItem->setRejectedQuantity((string) $itemData['rejected_quantity']);
                }

                if (isset($itemData['condition'])) {
                    $receiptItem->setCondition($itemData['condition']);
                }

                if (isset($itemData['notes'])) {
                    $receiptItem->setNotes($itemData['notes']);
                }
            }

            $receipt->addItem($receiptItem);
            $this->entityManager->persist($receiptItem);
        }

        $this->entityManager->persist($receipt);
        $this->entityManager->flush();

        return $receipt;
    }

    /**
     * Update goods receipt items
     */
    public function updateReceiptItems(GoodsReceipt $receipt, array $items): GoodsReceipt
    {
        if ($receipt->getStatus() !== GoodsReceipt::STATUS_DRAFT) {
            throw new \RuntimeException('Can only update draft goods receipts');
        }

        foreach ($receipt->getItems() as $receiptItem) {
            $reqItemId = $receiptItem->getRequisitionItem()->getId();

            if (isset($items[$reqItemId])) {
                $itemData = $items[$reqItemId];

                if (isset($itemData['received_quantity'])) {
                    $receiptItem->setReceivedQuantity((string) $itemData['received_quantity']);
                }

                if (isset($itemData['accepted_quantity'])) {
                    $receiptItem->setAcceptedQuantity((string) $itemData['accepted_quantity']);
                }

                if (isset($itemData['rejected_quantity'])) {
                    $receiptItem->setRejectedQuantity((string) $itemData['rejected_quantity']);
                }

                if (isset($itemData['condition'])) {
                    $receiptItem->setCondition($itemData['condition']);
                }

                if (isset($itemData['notes'])) {
                    $receiptItem->setNotes($itemData['notes']);
                }

                if (isset($itemData['quality_checks'])) {
                    $receiptItem->setQualityChecks($itemData['quality_checks']);
                }
            }
        }

        // Check for discrepancies
        $hasDiscrepancies = false;
        $discrepancies = [];

        foreach ($receipt->getItems() as $receiptItem) {
            if ($receiptItem->hasDiscrepancy()) {
                $hasDiscrepancies = true;
                $discrepancies[] = [
                    'item_id' => $receiptItem->getRequisitionItem()->getId(),
                    'description' => $receiptItem->getRequisitionItem()->getDescription(),
                    'ordered' => $receiptItem->getOrderedQuantity(),
                    'received' => $receiptItem->getReceivedQuantity(),
                ];
            }

            if ((float) $receiptItem->getRejectedQuantity() > 0) {
                $hasDiscrepancies = true;
            }
        }

        $receipt->setHasDiscrepancies($hasDiscrepancies);
        if ($hasDiscrepancies) {
            $receipt->setDiscrepancies($discrepancies);
        }

        $this->entityManager->flush();

        return $receipt;
    }

    /**
     * Confirm goods receipt
     */
    public function confirmReceipt(GoodsReceipt $receipt): GoodsReceipt
    {
        if ($receipt->getStatus() !== GoodsReceipt::STATUS_DRAFT) {
            throw new \RuntimeException('Goods receipt is not in draft status');
        }

        $receipt->setStatus(GoodsReceipt::STATUS_CONFIRMED);
        $receipt->setConfirmedAt(new \DateTimeImmutable());

        // Update requisition status to received
        $requisition = $receipt->getRequisition();
        $requisition->setStatus(Requisition::STATUS_RECEIVED);

        $this->entityManager->flush();

        // Notify requester
        $this->notificationService->notifyGoodsReceived(
            $requisition->getCreatedBy(),
            $receipt
        );

        return $receipt;
    }

    /**
     * Cancel goods receipt
     */
    public function cancelReceipt(GoodsReceipt $receipt, string $reason = null): GoodsReceipt
    {
        $receipt->setStatus(GoodsReceipt::STATUS_CANCELLED);

        if ($reason) {
            $notes = $receipt->getNotes() ?? '';
            $notes .= "\n\nCancellation reason: " . $reason;
            $receipt->setNotes($notes);
        }

        $this->entityManager->flush();

        return $receipt;
    }

    /**
     * Get pending receipts for a user's tenant
     */
    public function getPendingReceipts(User $user): array
    {
        return $this->goodsReceiptRepository->findPendingReceipts($user->getTenant());
    }
}

<?php

namespace App\Service;

use App\Entity\Requisition;
use App\Entity\Invoice;
use App\Entity\GoodsReceipt;
use App\Repository\GoodsReceiptRepository;

/**
 * Three-Way Matching Service
 *
 * Performs matching between:
 * 1. Purchase Order (Requisition)
 * 2. Invoice
 * 3. Goods Receipt
 *
 * Validates quantities and amounts match within acceptable tolerance.
 */
class ThreeWayMatchingService
{
    private const DEFAULT_QUANTITY_TOLERANCE = 0.05; // 5%
    private const DEFAULT_AMOUNT_TOLERANCE = 0.02; // 2%

    public function __construct(
        private GoodsReceiptRepository $goodsReceiptRepository,
    ) {
    }

    /**
     * Perform three-way matching
     *
     * @return array Match result with status and discrepancies
     */
    public function performMatch(
        Requisition $requisition,
        Invoice $invoice,
        GoodsReceipt $goodsReceipt,
        float $quantityTolerance = self::DEFAULT_QUANTITY_TOLERANCE,
        float $amountTolerance = self::DEFAULT_AMOUNT_TOLERANCE
    ): array {
        $discrepancies = [];
        $warnings = [];

        // 1. Verify all documents are for the same supplier
        $poSupplier = $requisition->getItems()->first()?->getSupplier();
        $invoiceSupplier = $invoice->getSupplier();
        $receiptSupplier = $goodsReceipt->getSupplier();

        if (!$poSupplier || $poSupplier->getId() !== $invoiceSupplier->getId()) {
            $discrepancies[] = [
                'type' => 'supplier_mismatch',
                'message' => 'Supplier on PO does not match Invoice supplier',
                'severity' => 'error',
            ];
        }

        if ($poSupplier && $poSupplier->getId() !== $receiptSupplier->getId()) {
            $discrepancies[] = [
                'type' => 'supplier_mismatch',
                'message' => 'Supplier on PO does not match Receipt supplier',
                'severity' => 'error',
            ];
        }

        // 2. Verify invoice amount matches PO amount (within tolerance)
        $poAmount = (float) $requisition->getTotalAmount();
        $invoiceAmount = (float) $invoice->getAmount();

        $amountDifference = abs($poAmount - $invoiceAmount);
        $amountDifferencePercent = $poAmount > 0 ? ($amountDifference / $poAmount) : 0;

        if ($amountDifferencePercent > $amountTolerance) {
            $discrepancies[] = [
                'type' => 'amount_mismatch',
                'message' => sprintf(
                    'Invoice amount ($%s) differs from PO amount ($%s) by %.2f%%',
                    number_format($invoiceAmount, 2),
                    number_format($poAmount, 2),
                    $amountDifferencePercent * 100
                ),
                'severity' => 'error',
                'po_amount' => $poAmount,
                'invoice_amount' => $invoiceAmount,
                'difference' => $amountDifference,
                'difference_percent' => $amountDifferencePercent * 100,
            ];
        } elseif ($amountDifferencePercent > 0) {
            $warnings[] = [
                'type' => 'amount_difference',
                'message' => sprintf(
                    'Invoice amount differs from PO by %.2f%% (within tolerance)',
                    $amountDifferencePercent * 100
                ),
                'severity' => 'warning',
            ];
        }

        // 3. Verify quantities received match quantities ordered (within tolerance)
        foreach ($goodsReceipt->getItems() as $receiptItem) {
            $requisitionItem = $receiptItem->getRequisitionItem();

            $orderedQty = (float) $requisitionItem->getQuantity();
            $receivedQty = (float) $receiptItem->getReceivedQuantity();
            $acceptedQty = (float) $receiptItem->getAcceptedQuantity();

            $qtyDifference = abs($orderedQty - $receivedQty);
            $qtyDifferencePercent = $orderedQty > 0 ? ($qtyDifference / $orderedQty) : 0;

            if ($qtyDifferencePercent > $quantityTolerance) {
                $discrepancies[] = [
                    'type' => 'quantity_mismatch',
                    'message' => sprintf(
                        'Item "%s": Received qty (%s) differs from ordered qty (%s) by %.2f%%',
                        $requisitionItem->getDescription(),
                        $receivedQty,
                        $orderedQty,
                        $qtyDifferencePercent * 100
                    ),
                    'severity' => 'error',
                    'item_description' => $requisitionItem->getDescription(),
                    'ordered_quantity' => $orderedQty,
                    'received_quantity' => $receivedQty,
                    'accepted_quantity' => $acceptedQty,
                    'difference' => $qtyDifference,
                    'difference_percent' => $qtyDifferencePercent * 100,
                ];
            }

            // Check for rejected items
            $rejectedQty = (float) $receiptItem->getRejectedQuantity();
            if ($rejectedQty > 0) {
                $warnings[] = [
                    'type' => 'rejected_items',
                    'message' => sprintf(
                        'Item "%s": %s units rejected',
                        $requisitionItem->getDescription(),
                        $rejectedQty
                    ),
                    'severity' => 'warning',
                    'rejected_quantity' => $rejectedQty,
                ];
            }
        }

        // 4. Check if goods receipt has discrepancies flagged
        if ($goodsReceipt->hasDiscrepancies()) {
            $warnings[] = [
                'type' => 'receipt_has_discrepancies',
                'message' => 'Goods receipt has been flagged with discrepancies',
                'severity' => 'warning',
                'details' => $goodsReceipt->getDiscrepancies(),
            ];
        }

        // Determine match status
        $status = empty($discrepancies) ? 'matched' : 'discrepancies_found';

        if (!empty($discrepancies)) {
            // Check if all discrepancies are low severity
            $hasErrors = false;
            foreach ($discrepancies as $discrepancy) {
                if ($discrepancy['severity'] === 'error') {
                    $hasErrors = true;
                    break;
                }
            }
            $status = $hasErrors ? 'failed' : 'partial_match';
        }

        return [
            'status' => $status,
            'matched' => $status === 'matched',
            'discrepancies' => $discrepancies,
            'warnings' => $warnings,
            'summary' => [
                'po_number' => $requisition->getRequisitionNumber(),
                'invoice_number' => $invoice->getInvoiceNumber(),
                'receipt_number' => $goodsReceipt->getReceiptNumber(),
                'po_amount' => $poAmount,
                'invoice_amount' => $invoiceAmount,
                'amount_difference' => $amountDifference,
                'discrepancy_count' => count($discrepancies),
                'warning_count' => count($warnings),
            ],
        ];
    }

    /**
     * Check if invoice can be paid (all matching criteria met)
     */
    public function canPayInvoice(Requisition $requisition, Invoice $invoice, GoodsReceipt $goodsReceipt): bool
    {
        $matchResult = $this->performMatch($requisition, $invoice, $goodsReceipt);
        return $matchResult['matched'];
    }

    /**
     * Get recommended action based on matching result
     */
    public function getRecommendedAction(array $matchResult): string
    {
        if ($matchResult['matched']) {
            return 'approve_payment';
        }

        if ($matchResult['status'] === 'partial_match') {
            return 'review_and_approve';
        }

        return 'reject_and_investigate';
    }

    /**
     * Find goods receipts for a requisition
     */
    public function findGoodsReceiptsForRequisition(Requisition $requisition): array
    {
        return $this->goodsReceiptRepository->findByRequisition($requisition);
    }

    /**
     * Validate if all required documents exist for matching
     */
    public function validateDocumentsExist(Requisition $requisition, Invoice $invoice): array
    {
        $errors = [];

        if (!$requisition || $requisition->getStatus() !== Requisition::STATUS_APPROVED) {
            $errors[] = 'Purchase order must be approved';
        }

        if (!$invoice || !in_array($invoice->getStatus(), [Invoice::STATUS_PENDING_APPROVAL, Invoice::STATUS_APPROVED])) {
            $errors[] = 'Invoice must be submitted';
        }

        $receipts = $this->goodsReceiptRepository->findByRequisition($requisition);
        if (empty($receipts)) {
            $errors[] = 'No goods receipt found for this purchase order';
        }

        return $errors;
    }
}

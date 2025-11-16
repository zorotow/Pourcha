<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\Requisition;
use App\Entity\RequisitionItem;
use App\Entity\User;
use App\Entity\Approval;
use App\Repository\RequisitionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class RequisitionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RequisitionRepository $requisitionRepository,
        private UserRepository $userRepository,
        private NotificationService $notificationService
    ) {
    }

    public function createFromCart(Cart $cart, User $user, array $approvers = []): Requisition
    {
        if ($cart->getItems()->isEmpty()) {
            throw new \RuntimeException('Cart is empty');
        }

        $requisition = new Requisition();
        $requisition->setRequisitionNumber($this->requisitionRepository->generateRequisitionNumber());
        $requisition->setCreatedBy($user);
        $requisition->setOnBehalfOf($cart->getUser());

        // Copy cart details to requisition
        $requisition->setInternalNote($cart->getInternalNote());
        $requisition->setNoteToSupplier($cart->getNoteToSupplier());
        $requisition->setHidePrice($cart->isHidePrice());
        $requisition->setDeliveryAddress($cart->getDeliveryAddress());
        $requisition->setLocationCode($cart->getLocationCode());
        $requisition->setPhone($cart->getPhone());
        $requisition->setAttentionTo($cart->getAttentionTo());
        $requisition->setSpecialDeliveryInstructions($cart->getSpecialDeliveryInstructions());
        $requisition->setRequiresCommodityApproval($cart->isRequiresCommodityApproval());
        $requisition->setPrescribedCommodity($cart->getPrescribedCommodity());
        $requisition->setHedgedRate($cart->getHedgedRate());
        $requisition->setAttachments($cart->getAttachments());

        // Convert cart items to requisition items
        foreach ($cart->getItems() as $cartItem) {
            $reqItem = new RequisitionItem();
            $reqItem->setRequisition($requisition);
            $reqItem->setDescription($cartItem->getCatalogItem()->getName());
            $reqItem->setSupplier($cartItem->getCatalogItem()->getSupplier());
            $reqItem->setSupplierPartNumber($cartItem->getCatalogItem()->getSupplierPartNumber());
            $reqItem->setQuantity($cartItem->getQuantity());
            $reqItem->setUnitPrice($cartItem->getUnitPrice());
            $reqItem->setUnit($cartItem->getCatalogItem()->getUnit());
            $reqItem->setChartOfAccounts($cartItem->getChartOfAccounts());
            $reqItem->setPaymentTerms($cartItem->getPaymentTerms());
            $reqItem->setCommodity($cartItem->getCatalogItem()->getCommodity());

            $requisition->addItem($reqItem);
            $this->entityManager->persist($reqItem);
        }

        $requisition->calculateTotal();

        // Create approvals if approvers are provided
        if (!empty($approvers)) {
            foreach ($approvers as $approverId) {
                $approver = $this->userRepository->find($approverId);
                if ($approver) {
                    $approval = new Approval();
                    $approval->setRequisition($requisition);
                    $approval->setApprover($approver);
                    $requisition->addApproval($approval);
                    $this->entityManager->persist($approval);

                    // Send notification to approver
                    $this->notificationService->notifyApprovalRequired($approver, $requisition);
                }
            }
        }

        $this->entityManager->persist($requisition);

        // Clear the cart
        $cart->setStatus('submitted');
        $cart->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $requisition;
    }

    public function submitRequisition(Requisition $requisition): void
    {
        if ($requisition->getStatus() !== Requisition::STATUS_DRAFT) {
            throw new \RuntimeException('Only draft requisitions can be submitted');
        }

        if ($requisition->getApprovals()->isEmpty()) {
            // No approvals required, mark as approved
            $requisition->setStatus(Requisition::STATUS_APPROVED);
            $requisition->setApprovedAt(new \DateTimeImmutable());
        } else {
            $requisition->setStatus(Requisition::STATUS_PENDING_APPROVAL);
        }

        $requisition->setSubmittedAt(new \DateTimeImmutable());
        $requisition->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    public function approveRequisition(Requisition $requisition, User $approver, ?string $comments = null): void
    {
        $approval = null;
        foreach ($requisition->getApprovals() as $appr) {
            if ($appr->getApprover()->getId() === $approver->getId()) {
                $approval = $appr;
                break;
            }
        }

        if (!$approval) {
            throw new \RuntimeException('Approval not found for this user');
        }

        if ($approval->getStatus() !== Approval::STATUS_PENDING) {
            throw new \RuntimeException('Approval has already been processed');
        }

        $approval->setStatus(Approval::STATUS_APPROVED);
        $approval->setComments($comments);
        $approval->setApprovedAt(new \DateTimeImmutable());

        // Check if all approvals are complete
        $allApproved = true;
        foreach ($requisition->getApprovals() as $appr) {
            if ($appr->getStatus() === Approval::STATUS_PENDING) {
                $allApproved = false;
                break;
            }
        }

        if ($allApproved) {
            $requisition->setStatus(Requisition::STATUS_APPROVED);
            $requisition->setApprovedAt(new \DateTimeImmutable());

            // Notify requester
            $this->notificationService->notifyRequisitionApproved($requisition->getCreatedBy(), $requisition);
        }

        $requisition->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    public function rejectRequisition(Requisition $requisition, User $approver, string $comments): void
    {
        $approval = null;
        foreach ($requisition->getApprovals() as $appr) {
            if ($appr->getApprover()->getId() === $approver->getId()) {
                $approval = $appr;
                break;
            }
        }

        if (!$approval) {
            throw new \RuntimeException('Approval not found for this user');
        }

        if ($approval->getStatus() !== Approval::STATUS_PENDING) {
            throw new \RuntimeException('Approval has already been processed');
        }

        $approval->setStatus(Approval::STATUS_REJECTED);
        $approval->setComments($comments);
        $approval->setRejectedAt(new \DateTimeImmutable());

        $requisition->setStatus(Requisition::STATUS_REJECTED);
        $requisition->setUpdatedAt(new \DateTimeImmutable());

        // Notify requester
        $this->notificationService->notifyRequisitionRejected($requisition->getCreatedBy(), $requisition, $comments);

        $this->entityManager->flush();
    }

    public function cancelRequisition(Requisition $requisition): void
    {
        if (!in_array($requisition->getStatus(), [Requisition::STATUS_DRAFT, Requisition::STATUS_SUBMITTED, Requisition::STATUS_PENDING_APPROVAL])) {
            throw new \RuntimeException('Cannot cancel requisition in current status');
        }

        $requisition->setStatus(Requisition::STATUS_CANCELLED);
        $requisition->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }
}

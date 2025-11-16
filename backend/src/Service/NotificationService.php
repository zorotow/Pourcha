<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Entity\Requisition;
use App\Entity\Invoice;
use App\Entity\GoodsReceipt;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function notifyApprovalRequired(User $user, Requisition|Invoice $item): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType(Notification::TYPE_APPROVAL_REQUIRED);

        if ($item instanceof Requisition) {
            $notification->setTitle('Requisition Approval Required');
            $notification->setMessage(sprintf(
                'Requisition %s requires your approval. Amount: %s %s',
                $item->getRequisitionNumber(),
                $item->getTotalAmount(),
                $item->getCurrency()
            ));
            $notification->setData([
                'type' => 'requisition',
                'id' => $item->getId(),
                'number' => $item->getRequisitionNumber(),
                'amount' => $item->getTotalAmount(),
            ]);
        } else {
            $notification->setTitle('Invoice Approval Required');
            $notification->setMessage(sprintf(
                'Invoice %s requires your approval. Amount: %s %s',
                $item->getInvoiceNumber(),
                $item->getAmount(),
                $item->getCurrency()
            ));
            $notification->setData([
                'type' => 'invoice',
                'id' => $item->getId(),
                'number' => $item->getInvoiceNumber(),
                'amount' => $item->getAmount(),
            ]);
        }

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    public function notifyRequisitionApproved(User $user, Requisition $requisition): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType(Notification::TYPE_REQUISITION_APPROVED);
        $notification->setTitle('Requisition Approved');
        $notification->setMessage(sprintf(
            'Your requisition %s has been approved.',
            $requisition->getRequisitionNumber()
        ));
        $notification->setData([
            'type' => 'requisition',
            'id' => $requisition->getId(),
            'number' => $requisition->getRequisitionNumber(),
        ]);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    public function notifyRequisitionRejected(User $user, Requisition $requisition, string $reason): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType(Notification::TYPE_REQUISITION_REJECTED);
        $notification->setTitle('Requisition Rejected');
        $notification->setMessage(sprintf(
            'Your requisition %s has been rejected. Reason: %s',
            $requisition->getRequisitionNumber(),
            $reason
        ));
        $notification->setData([
            'type' => 'requisition',
            'id' => $requisition->getId(),
            'number' => $requisition->getRequisitionNumber(),
            'reason' => $reason,
        ]);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    public function notifyInvoiceApproved(User $user, Invoice $invoice): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType(Notification::TYPE_INVOICE_APPROVED);
        $notification->setTitle('Invoice Approved');
        $notification->setMessage(sprintf(
            'Your invoice %s has been approved.',
            $invoice->getInvoiceNumber()
        ));
        $notification->setData([
            'type' => 'invoice',
            'id' => $invoice->getId(),
            'number' => $invoice->getInvoiceNumber(),
        ]);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    public function notifyInvoiceRejected(User $user, Invoice $invoice, string $reason): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType(Notification::TYPE_INVOICE_REJECTED);
        $notification->setTitle('Invoice Rejected');
        $notification->setMessage(sprintf(
            'Your invoice %s has been rejected. Reason: %s',
            $invoice->getInvoiceNumber(),
            $reason
        ));
        $notification->setData([
            'type' => 'invoice',
            'id' => $invoice->getId(),
            'number' => $invoice->getInvoiceNumber(),
            'reason' => $reason,
        ]);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    public function notifyDelegateAssigned(User $user, User $delegateTo): void
    {
        $notification = new Notification();
        $notification->setUser($delegateTo);
        $notification->setType(Notification::TYPE_DELEGATE_ASSIGNED);
        $notification->setTitle('Delegate Assigned');
        $notification->setMessage(sprintf(
            '%s %s has assigned you as their delegate.',
            $user->getFirstName(),
            $user->getLastName()
        ));
        $notification->setData([
            'user_id' => $user->getId(),
            'user_name' => $user->getFirstName() . ' ' . $user->getLastName(),
        ]);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    public function notifyGoodsReceived(User $user, GoodsReceipt $goodsReceipt): void
    {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType(Notification::TYPE_GENERAL);
        $notification->setTitle('Goods Received');
        $notification->setMessage(sprintf(
            'Goods receipt %s for requisition %s has been confirmed.',
            $goodsReceipt->getReceiptNumber(),
            $goodsReceipt->getRequisition()->getRequisitionNumber()
        ));
        $notification->setData([
            'type' => 'goods_receipt',
            'id' => $goodsReceipt->getId(),
            'receipt_number' => $goodsReceipt->getReceiptNumber(),
            'requisition_number' => $goodsReceipt->getRequisition()->getRequisitionNumber(),
        ]);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }
}

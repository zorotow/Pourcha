<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\User;
use App\Entity\Approval;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class InvoiceService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private NotificationService $notificationService
    ) {
    }

    public function createInvoice(User $user, array $data, array $approvers = []): Invoice
    {
        $invoice = new Invoice();
        $invoice->setSubmittedBy($user);
        $invoice->setInvoiceNumber($data['invoiceNumber']);
        $invoice->setAmount($data['amount']);

        if (isset($data['supplier'])) {
            $invoice->setSupplier($data['supplier']);
        }

        if (isset($data['requisition'])) {
            $invoice->setRequisition($data['requisition']);
        }

        if (isset($data['currency'])) {
            $invoice->setCurrency($data['currency']);
        }

        if (isset($data['invoiceDate'])) {
            $invoice->setInvoiceDate($data['invoiceDate']);
        }

        if (isset($data['dueDate'])) {
            $invoice->setDueDate($data['dueDate']);
        }

        if (isset($data['description'])) {
            $invoice->setDescription($data['description']);
        }

        if (isset($data['attachments'])) {
            $invoice->setAttachments($data['attachments']);
        }

        if (isset($data['chartOfAccounts'])) {
            $invoice->setChartOfAccounts($data['chartOfAccounts']);
        }

        // Create approvals if approvers are provided
        if (!empty($approvers)) {
            foreach ($approvers as $approverId) {
                $approver = $this->userRepository->find($approverId);
                if ($approver) {
                    $approval = new Approval();
                    $approval->setInvoice($invoice);
                    $approval->setApprover($approver);
                    $invoice->addApproval($approval);
                    $this->entityManager->persist($approval);

                    // Send notification to approver
                    $this->notificationService->notifyApprovalRequired($approver, $invoice);
                }
            }
        }

        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $invoice;
    }

    public function submitInvoice(Invoice $invoice): void
    {
        if ($invoice->getStatus() !== Invoice::STATUS_DRAFT) {
            throw new \RuntimeException('Only draft invoices can be submitted');
        }

        if ($invoice->getApprovals()->isEmpty()) {
            $invoice->setStatus(Invoice::STATUS_APPROVED);
            $invoice->setApprovedAt(new \DateTimeImmutable());
        } else {
            $invoice->setStatus(Invoice::STATUS_PENDING_APPROVAL);
        }

        $invoice->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    public function approveInvoice(Invoice $invoice, User $approver, ?string $comments = null): void
    {
        $approval = null;
        foreach ($invoice->getApprovals() as $appr) {
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
        foreach ($invoice->getApprovals() as $appr) {
            if ($appr->getStatus() === Approval::STATUS_PENDING) {
                $allApproved = false;
                break;
            }
        }

        if ($allApproved) {
            $invoice->setStatus(Invoice::STATUS_APPROVED);
            $invoice->setApprovedAt(new \DateTimeImmutable());

            // Notify submitter
            $this->notificationService->notifyInvoiceApproved($invoice->getSubmittedBy(), $invoice);
        }

        $invoice->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    public function rejectInvoice(Invoice $invoice, User $approver, string $comments): void
    {
        $approval = null;
        foreach ($invoice->getApprovals() as $appr) {
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

        $invoice->setStatus(Invoice::STATUS_REJECTED);
        $invoice->setUpdatedAt(new \DateTimeImmutable());

        // Notify submitter
        $this->notificationService->notifyInvoiceRejected($invoice->getSubmittedBy(), $invoice, $comments);

        $this->entityManager->flush();
    }

    public function markAsPaid(Invoice $invoice): void
    {
        if ($invoice->getStatus() !== Invoice::STATUS_APPROVED) {
            throw new \RuntimeException('Only approved invoices can be marked as paid');
        }

        $invoice->setStatus(Invoice::STATUS_PAID);
        $invoice->setPaidAt(new \DateTimeImmutable());
        $invoice->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }
}

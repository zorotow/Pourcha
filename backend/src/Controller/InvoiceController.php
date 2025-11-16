<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\InvoiceRepository;
use App\Repository\SupplierRepository;
use App\Service\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/invoices')]
#[IsGranted('ROLE_USER')]
class InvoiceController extends AbstractController
{
    public function __construct(
        private InvoiceService $invoiceService,
        private InvoiceRepository $invoiceRepository,
        private SupplierRepository $supplierRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'api_invoices_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $status = $request->query->get('status');
        $statuses = $status ? [$status] : [];

        $invoices = $this->invoiceRepository->findByUser($user, $statuses);

        $data = array_map(fn($inv) => [
            'id' => $inv->getId(),
            'invoiceNumber' => $inv->getInvoiceNumber(),
            'supplier' => [
                'id' => $inv->getSupplier()->getId(),
                'name' => $inv->getSupplier()->getName(),
            ],
            'amount' => $inv->getAmount(),
            'currency' => $inv->getCurrency(),
            'status' => $inv->getStatus(),
            'invoiceDate' => $inv->getInvoiceDate()?->format('Y-m-d'),
            'dueDate' => $inv->getDueDate()?->format('Y-m-d'),
            'createdAt' => $inv->getCreatedAt()->format('c'),
        ], $invoices);

        return $this->json(['invoices' => $data]);
    }

    #[Route('', name: 'api_invoices_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (empty($data['invoiceNumber']) || empty($data['amount']) || empty($data['supplierId'])) {
            return $this->json(['error' => 'invoiceNumber, amount, and supplierId are required'], 400);
        }

        $supplier = $this->supplierRepository->find($data['supplierId']);
        if (!$supplier) {
            return $this->json(['error' => 'Supplier not found'], 404);
        }

        $invoiceData = [
            'invoiceNumber' => $data['invoiceNumber'],
            'amount' => $data['amount'],
            'supplier' => $supplier,
            'currency' => $data['currency'] ?? 'AUD',
            'description' => $data['description'] ?? null,
        ];

        if (isset($data['invoiceDate'])) {
            $invoiceData['invoiceDate'] = new \DateTimeImmutable($data['invoiceDate']);
        }

        if (isset($data['dueDate'])) {
            $invoiceData['dueDate'] = new \DateTimeImmutable($data['dueDate']);
        }

        $approvers = $data['approvers'] ?? [];

        try {
            $invoice = $this->invoiceService->createInvoice($user, $invoiceData, $approvers);

            return $this->json([
                'message' => 'Invoice created successfully',
                'invoice' => [
                    'id' => $invoice->getId(),
                    'invoiceNumber' => $invoice->getInvoiceNumber(),
                    'status' => $invoice->getStatus(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/approve', name: 'api_invoices_approve', methods: ['POST'])]
    public function approve(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $invoice = $this->invoiceRepository->find($id);

        if (!$invoice) {
            return $this->json(['error' => 'Invoice not found'], 404);
        }

        try {
            $comments = $data['comments'] ?? null;
            $this->invoiceService->approveInvoice($invoice, $user, $comments);

            return $this->json(['message' => 'Invoice approved successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/reject', name: 'api_invoices_reject', methods: ['POST'])]
    public function reject(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $invoice = $this->invoiceRepository->find($id);

        if (!$invoice) {
            return $this->json(['error' => 'Invoice not found'], 404);
        }

        $comments = $data['comments'] ?? '';

        if (empty($comments)) {
            return $this->json(['error' => 'Comments are required for rejection'], 400);
        }

        try {
            $this->invoiceService->rejectInvoice($invoice, $user, $comments);

            return $this->json(['message' => 'Invoice rejected']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/pending-approvals', name: 'api_invoices_pending_approvals', methods: ['GET'])]
    public function pendingApprovals(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $invoices = $this->invoiceRepository->findPendingApprovals($user);

        $data = array_map(fn($inv) => [
            'id' => $inv->getId(),
            'invoiceNumber' => $inv->getInvoiceNumber(),
            'supplier' => [
                'id' => $inv->getSupplier()->getId(),
                'name' => $inv->getSupplier()->getName(),
            ],
            'amount' => $inv->getAmount(),
            'currency' => $inv->getCurrency(),
            'description' => $inv->getDescription(),
            'invoiceDate' => $inv->getInvoiceDate()?->format('Y-m-d'),
            'createdAt' => $inv->getCreatedAt()->format('c'),
        ], $invoices);

        return $this->json(['invoices' => $data]);
    }
}

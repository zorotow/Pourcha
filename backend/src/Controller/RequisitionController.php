<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CartRepository;
use App\Repository\RequisitionRepository;
use App\Service\RequisitionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/requisitions')]
#[IsGranted('ROLE_USER')]
class RequisitionController extends AbstractController
{
    public function __construct(
        private RequisitionService $requisitionService,
        private RequisitionRepository $requisitionRepository,
        private CartRepository $cartRepository
    ) {
    }

    #[Route('', name: 'api_requisitions_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $status = $request->query->get('status');
        $statuses = $status ? [$status] : [];

        $requisitions = $this->requisitionRepository->findByUser($user, $statuses);

        $data = array_map(fn($req) => [
            'id' => $req->getId(),
            'requisitionNumber' => $req->getRequisitionNumber(),
            'status' => $req->getStatus(),
            'totalAmount' => $req->getTotalAmount(),
            'currency' => $req->getCurrency(),
            'itemsCount' => $req->getItems()->count(),
            'approvalsCount' => $req->getApprovals()->count(),
            'submittedAt' => $req->getSubmittedAt()?->format('c'),
            'approvedAt' => $req->getApprovedAt()?->format('c'),
            'createdAt' => $req->getCreatedAt()->format('c'),
        ], $requisitions);

        return $this->json(['requisitions' => $data]);
    }

    #[Route('/{id}', name: 'api_requisitions_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $requisition = $this->requisitionRepository->find($id);

        if (!$requisition) {
            return $this->json(['error' => 'Requisition not found'], 404);
        }

        $items = array_map(fn($item) => [
            'id' => $item->getId(),
            'description' => $item->getDescription(),
            'supplier' => $item->getSupplier() ? [
                'id' => $item->getSupplier()->getId(),
                'name' => $item->getSupplier()->getName(),
            ] : null,
            'supplierPartNumber' => $item->getSupplierPartNumber(),
            'quantity' => $item->getQuantity(),
            'unitPrice' => $item->getUnitPrice(),
            'unit' => $item->getUnit(),
            'subtotal' => $item->getSubtotal(),
            'commodity' => $item->getCommodity(),
            'chartOfAccounts' => $item->getChartOfAccounts() ? [
                'fullCode' => $item->getChartOfAccounts()->getFullCode(),
                'fullName' => $item->getChartOfAccounts()->getFullName(),
            ] : null,
        ], $requisition->getItems()->toArray());

        $approvals = array_map(fn($approval) => [
            'id' => $approval->getId(),
            'approver' => [
                'id' => $approval->getApprover()->getId(),
                'name' => $approval->getApprover()->getFirstName() . ' ' . $approval->getApprover()->getLastName(),
                'email' => $approval->getApprover()->getEmail(),
            ],
            'status' => $approval->getStatus(),
            'comments' => $approval->getComments(),
            'approvedAt' => $approval->getApprovedAt()?->format('c'),
            'rejectedAt' => $approval->getRejectedAt()?->format('c'),
        ], $requisition->getApprovals()->toArray());

        return $this->json([
            'id' => $requisition->getId(),
            'requisitionNumber' => $requisition->getRequisitionNumber(),
            'status' => $requisition->getStatus(),
            'createdBy' => [
                'id' => $requisition->getCreatedBy()->getId(),
                'name' => $requisition->getCreatedBy()->getFirstName() . ' ' . $requisition->getCreatedBy()->getLastName(),
            ],
            'onBehalfOf' => $requisition->getOnBehalfOf() ? [
                'id' => $requisition->getOnBehalfOf()->getId(),
                'name' => $requisition->getOnBehalfOf()->getFirstName() . ' ' . $requisition->getOnBehalfOf()->getLastName(),
            ] : null,
            'items' => $items,
            'approvals' => $approvals,
            'totalAmount' => $requisition->getTotalAmount(),
            'currency' => $requisition->getCurrency(),
            'internalNote' => $requisition->getInternalNote(),
            'noteToSupplier' => $requisition->getNoteToSupplier(),
            'deliveryAddress' => $requisition->getDeliveryAddress(),
            'locationCode' => $requisition->getLocationCode(),
            'phone' => $requisition->getPhone(),
            'attentionTo' => $requisition->getAttentionTo(),
            'specialDeliveryInstructions' => $requisition->getSpecialDeliveryInstructions(),
            'attachments' => $requisition->getAttachments(),
            'submittedAt' => $requisition->getSubmittedAt()?->format('c'),
            'approvedAt' => $requisition->getApprovedAt()?->format('c'),
            'createdAt' => $requisition->getCreatedAt()->format('c'),
        ]);
    }

    #[Route('/from-cart', name: 'api_requisitions_from_cart', methods: ['POST'])]
    public function createFromCart(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $cartId = $data['cartId'] ?? null;
        $approvers = $data['approvers'] ?? [];

        if (!$cartId) {
            return $this->json(['error' => 'cartId is required'], 400);
        }

        $cart = $this->cartRepository->find($cartId);

        if (!$cart || $cart->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Cart not found'], 404);
        }

        try {
            $requisition = $this->requisitionService->createFromCart($cart, $user, $approvers);

            return $this->json([
                'message' => 'Requisition created successfully',
                'requisition' => [
                    'id' => $requisition->getId(),
                    'requisitionNumber' => $requisition->getRequisitionNumber(),
                    'status' => $requisition->getStatus(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/submit', name: 'api_requisitions_submit', methods: ['POST'])]
    public function submit(int $id): JsonResponse
    {
        $requisition = $this->requisitionRepository->find($id);

        if (!$requisition) {
            return $this->json(['error' => 'Requisition not found'], 404);
        }

        try {
            $this->requisitionService->submitRequisition($requisition);

            return $this->json([
                'message' => 'Requisition submitted successfully',
                'requisition' => [
                    'id' => $requisition->getId(),
                    'status' => $requisition->getStatus(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/approve', name: 'api_requisitions_approve', methods: ['POST'])]
    public function approve(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $requisition = $this->requisitionRepository->find($id);

        if (!$requisition) {
            return $this->json(['error' => 'Requisition not found'], 404);
        }

        try {
            $comments = $data['comments'] ?? null;
            $this->requisitionService->approveRequisition($requisition, $user, $comments);

            return $this->json(['message' => 'Requisition approved successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/reject', name: 'api_requisitions_reject', methods: ['POST'])]
    public function reject(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $requisition = $this->requisitionRepository->find($id);

        if (!$requisition) {
            return $this->json(['error' => 'Requisition not found'], 404);
        }

        $comments = $data['comments'] ?? '';

        if (empty($comments)) {
            return $this->json(['error' => 'Comments are required for rejection'], 400);
        }

        try {
            $this->requisitionService->rejectRequisition($requisition, $user, $comments);

            return $this->json(['message' => 'Requisition rejected']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}

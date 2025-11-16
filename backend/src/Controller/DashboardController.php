<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ApprovalRepository;
use App\Repository\NotificationRepository;
use App\Repository\RequisitionRepository;
use App\Repository\InvoiceRepository;
use App\Repository\SupplierRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/dashboard')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private ApprovalRepository $approvalRepository,
        private NotificationRepository $notificationRepository,
        private RequisitionRepository $requisitionRepository,
        private InvoiceRepository $invoiceRepository,
        private SupplierRepository $supplierRepository
    ) {
    }

    #[Route('', name: 'api_dashboard', methods: ['GET'])]
    public function getDashboard(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $tenant = $user->getTenant();

        // Get pending approvals
        $pendingApprovals = $this->approvalRepository->findPendingByApprover($user);

        // Get notifications
        $notifications = $this->notificationRepository->findByUser($user, true);
        $unreadCount = $this->notificationRepository->countUnread($user);

        // Get recent requisitions
        $recentRequisitions = $this->requisitionRepository->findByUser($user, []);

        // Get suppliers for "Additional Stores"
        $suppliers = $this->supplierRepository->findByTenant($tenant);

        $tasks = [
            ['id' => 'guided_request', 'title' => 'Guided Request', 'icon' => 'help_outline'],
            ['id' => 'approve_invoices', 'title' => 'Approve Invoices', 'icon' => 'check_circle', 'count' => count($this->invoiceRepository->findPendingApprovals($user))],
            ['id' => 'view_requisitions', 'title' => 'View Requisitions', 'icon' => 'receipt'],
            ['id' => 'receive_goods', 'title' => 'Receive Goods', 'icon' => 'inventory'],
            ['id' => 'set_delegate', 'title' => 'Set a Delegate', 'icon' => 'person_add'],
            ['id' => 'view_suppliers', 'title' => 'View Suppliers', 'icon' => 'store'],
            ['id' => 'goods_request', 'title' => 'Goods Request', 'icon' => 'shopping_cart'],
            ['id' => 'services_request', 'title' => 'Services Request', 'icon' => 'handyman'],
            ['id' => 'submit_invoice', 'title' => 'Submit an Invoice', 'icon' => 'description'],
            ['id' => 'card_request', 'title' => 'Credit/Virtual Card Request', 'icon' => 'credit_card'],
            ['id' => 'gift_card', 'title' => 'Gift Card Request', 'icon' => 'card_giftcard'],
            ['id' => 'catering', 'title' => 'Catering & Event Request', 'icon' => 'restaurant'],
        ];

        return $this->json([
            'user' => [
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'email' => $user->getEmail(),
            ],
            'pending_approvals' => count($pendingApprovals),
            'unread_notifications' => $unreadCount,
            'popular_tasks' => $tasks,
            'suppliers' => array_map(fn($s) => [
                'id' => $s->getId(),
                'name' => $s->getName(),
                'logoUrl' => $s->getLogoUrl(),
                'type' => $s->getType(),
            ], array_slice($suppliers, 0, 10)),
            'recent_requisitions' => array_slice(array_map(fn($r) => [
                'id' => $r->getId(),
                'requisitionNumber' => $r->getRequisitionNumber(),
                'status' => $r->getStatus(),
                'totalAmount' => $r->getTotalAmount(),
                'createdAt' => $r->getCreatedAt()->format('c'),
            ], $recentRequisitions), 0, 5),
            'todos' => [], // To-do items would be fetched from a separate entity
            'announcements' => [], // Announcements would be from a separate entity
        ]);
    }

    #[Route('/notifications', name: 'api_dashboard_notifications', methods: ['GET'])]
    public function getNotifications(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $notifications = $this->notificationRepository->findByUser($user);

        $data = array_map(fn($n) => [
            'id' => $n->getId(),
            'type' => $n->getType(),
            'title' => $n->getTitle(),
            'message' => $n->getMessage(),
            'isRead' => $n->isRead(),
            'data' => $n->getData(),
            'createdAt' => $n->getCreatedAt()->format('c'),
        ], $notifications);

        return $this->json(['notifications' => $data]);
    }

    #[Route('/notifications/{id}/read', name: 'api_dashboard_notification_read', methods: ['POST'])]
    public function markNotificationAsRead(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $notification = $this->notificationRepository->find($id);

        if (!$notification || $notification->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Notification not found'], 404);
        }

        $notification->markAsRead();
        $this->notificationRepository->getEntityManager()->flush();

        return $this->json(['message' => 'Notification marked as read']);
    }
}

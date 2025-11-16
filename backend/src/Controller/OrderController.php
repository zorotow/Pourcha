<?php

namespace App\Controller;

use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\User;
use App\Service\Payment\CryptoPaymentService;
use App\Service\SubscriptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/orders')]
#[IsGranted('ROLE_USER')]
class OrderController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SubscriptionService $subscriptionService,
        private CryptoPaymentService $cryptoService
    ) {
    }

    #[Route('', name: 'api_orders_create', methods: ['POST'])]
    public function createOrder(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $plan = $data['plan'] ?? Subscription::PLAN_PRO;
        $gateway = $data['gateway'] ?? 'stripe';
        $amount = $data['amount'] ?? 0;

        if (!in_array($plan, [Subscription::PLAN_PRO, Subscription::PLAN_ENTERPRISE])) {
            return $this->json([
                'error' => 'Invalid plan.',
            ], 400);
        }

        try {
            // Create payment record
            $subscription = $user->getSubscription();
            if (!$subscription) {
                $subscription = new Subscription();
                $subscription->setUser($user);
                $user->setSubscription($subscription);
                $this->entityManager->persist($subscription);
            }

            $payment = new Payment();
            $payment->setSubscription($subscription);
            $payment->setGateway($gateway);
            $payment->setAmount($amount);
            $payment->setCurrency('USD');
            $payment->setStatus(Payment::STATUS_PENDING);
            $payment->setMetadata([
                'plan' => $plan,
            ]);

            $this->entityManager->persist($payment);
            $this->entityManager->flush();

            return $this->json([
                'order_id' => $payment->getId(),
                'status' => 'pending',
                'amount' => $amount,
                'plan' => $plan,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to create order: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/simulate-payment', name: 'api_orders_simulate', methods: ['POST'])]
    public function simulatePayment(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $orderId = $data['order_id'] ?? null;
        $success = $data['success'] ?? true;

        if (!$orderId) {
            return $this->json([
                'error' => 'Missing order_id.',
            ], 400);
        }

        $payment = $this->entityManager->getRepository(Payment::class)->find($orderId);

        if (!$payment) {
            return $this->json([
                'error' => 'Payment not found.',
            ], 404);
        }

        if ($payment->getSubscription()->getUser()->getId() !== $user->getId()) {
            return $this->json([
                'error' => 'Unauthorized.',
            ], 403);
        }

        try {
            if ($success) {
                // Simulate successful payment
                $payment->setStatus(Payment::STATUS_COMPLETED);
                $payment->setCompletedAt(new \DateTimeImmutable());
                $payment->setTransactionId('sim_' . uniqid());

                // Upgrade subscription
                $subscription = $payment->getSubscription();
                $metadata = $payment->getMetadata();
                $plan = $metadata['plan'] ?? Subscription::PLAN_PRO;

                $subscription->setPlan($plan);
                $subscription->setStatus(Subscription::STATUS_ACTIVE);
                $subscription->setGateway($payment->getGateway());
                $subscription->setRenewalDate(new \DateTimeImmutable('+1 month'));
                $subscription->setUpdatedAt(new \DateTimeImmutable());

                $this->entityManager->persist($payment);
                $this->entityManager->persist($subscription);
                $this->entityManager->flush();

                return $this->json([
                    'status' => 'success',
                    'message' => 'Payment simulated successfully.',
                    'subscription' => [
                        'plan' => $subscription->getPlan(),
                        'status' => $subscription->getStatus(),
                        'renewal_date' => $subscription->getRenewalDate()->format('c'),
                    ],
                ]);
            } else {
                // Simulate failed payment
                $payment->setStatus(Payment::STATUS_FAILED);
                $this->entityManager->persist($payment);
                $this->entityManager->flush();

                return $this->json([
                    'status' => 'failed',
                    'message' => 'Payment simulation failed.',
                ]);
            }
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to simulate payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/crypto-verify', name: 'api_orders_crypto_verify', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function verifyCryptoPayment(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $paymentId = $data['payment_id'] ?? null;
        $transactionHash = $data['transaction_hash'] ?? null;

        if (!$paymentId || !$transactionHash) {
            return $this->json([
                'error' => 'Missing payment_id or transaction_hash.',
            ], 400);
        }

        try {
            $this->cryptoService->verifyPayment($paymentId, $transactionHash);

            return $this->json([
                'status' => 'success',
                'message' => 'Crypto payment verified successfully.',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}

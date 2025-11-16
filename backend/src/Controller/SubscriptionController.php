<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\Payment\CryptoPaymentService;
use App\Service\Payment\PayPalService;
use App\Service\Payment\StripeService;
use App\Service\SubscriptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/subscription')]
#[IsGranted('ROLE_USER')]
class SubscriptionController extends AbstractController
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private StripeService $stripeService,
        private PayPalService $payPalService
    ) {
    }

    #[Route('', name: 'api_subscription_get', methods: ['GET'])]
    public function getSubscription(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $summary = $this->subscriptionService->getSubscriptionSummary($user);

        return $this->json($summary);
    }

    #[Route('/cancel', name: 'api_subscription_cancel', methods: ['POST'])]
    public function cancelSubscription(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $subscription = $user->getSubscription();

        if (!$subscription) {
            return $this->json([
                'error' => 'No active subscription found.',
            ], 404);
        }

        if ($subscription->isFree()) {
            return $this->json([
                'error' => 'Cannot cancel free plan.',
            ], 400);
        }

        try {
            // Cancel on payment gateway
            $gateway = $subscription->getGateway();
            switch ($gateway) {
                case 'stripe':
                    $this->stripeService->cancelSubscription($subscription);
                    break;

                case 'paypal':
                    $this->payPalService->cancelSubscription($subscription);
                    break;

                case 'crypto':
                    // For crypto, just update the subscription status
                    $this->subscriptionService->cancelSubscription($subscription);
                    break;

                default:
                    return $this->json([
                        'error' => 'Unknown payment gateway.',
                    ], 500);
            }

            return $this->json([
                'message' => 'Subscription cancelled successfully.',
                'subscription' => [
                    'plan' => $subscription->getPlan(),
                    'status' => $subscription->getStatus(),
                    'cancelled_at' => $subscription->getCancelledAt()?->format('c'),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to cancel subscription: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/history', name: 'api_subscription_history', methods: ['GET'])]
    public function getPaymentHistory(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $subscription = $user->getSubscription();

        if (!$subscription) {
            return $this->json([
                'payments' => [],
            ]);
        }

        $payments = $subscription->getPayments()->toArray();
        $paymentData = array_map(function ($payment) {
            return [
                'id' => $payment->getId(),
                'amount' => $payment->getAmount(),
                'currency' => $payment->getCurrency(),
                'status' => $payment->getStatus(),
                'gateway' => $payment->getGateway(),
                'transaction_id' => $payment->getTransactionId(),
                'created_at' => $payment->getCreatedAt()->format('c'),
                'completed_at' => $payment->getCompletedAt()?->format('c'),
            ];
        }, $payments);

        return $this->json([
            'payments' => $paymentData,
        ]);
    }
}

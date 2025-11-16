<?php

namespace App\Service\Payment;

use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PayPalService
{
    private const SANDBOX_API = 'https://api-m.sandbox.paypal.com';
    private const PRODUCTION_API = 'https://api-m.paypal.com';

    private string $baseUrl;
    private ?string $accessToken = null;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient,
        private string $mode,
        private string $clientId,
        private string $clientSecret
    ) {
        $this->baseUrl = $mode === 'production' ? self::PRODUCTION_API : self::SANDBOX_API;
    }

    /**
     * Create a subscription for a user
     */
    public function createSubscription(
        User $user,
        string $plan,
        string $billingInterval = 'monthly'
    ): array {
        $planId = $this->getPlanIdForPlan($plan, $billingInterval);
        $accessToken = $this->getAccessToken();

        $response = $this->httpClient->request('POST', $this->baseUrl . '/v1/billing/subscriptions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'plan_id' => $planId,
                'subscriber' => [
                    'email_address' => $user->getEmail(),
                    'name' => [
                        'given_name' => $user->getFirstName(),
                        'surname' => $user->getLastName(),
                    ],
                ],
                'application_context' => [
                    'brand_name' => 'Pourcha',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'SUBSCRIBE_NOW',
                    'return_url' => 'https://pourcha.app/billing/success',
                    'cancel_url' => 'https://pourcha.app/billing/cancel',
                ],
                'custom_id' => (string) $user->getId(),
            ],
        ]);

        return $response->toArray();
    }

    /**
     * Create a one-time payment order
     */
    public function createOrder(
        User $user,
        int $amount,
        string $currency = 'USD',
        string $description = 'Pourcha Payment'
    ): array {
        $accessToken = $this->getAccessToken();

        $response = $this->httpClient->request('POST', $this->baseUrl . '/v2/checkout/orders', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'description' => $description,
                    'custom_id' => (string) $user->getId(),
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amount / 100, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'brand_name' => 'Pourcha',
                    'shipping_preference' => 'NO_SHIPPING',
                    'return_url' => 'https://pourcha.app/billing/success',
                    'cancel_url' => 'https://pourcha.app/billing/cancel',
                ],
            ],
        ]);

        return $response->toArray();
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(Subscription $subscription): void
    {
        if (!$subscription->getGatewaySubscriptionId()) {
            throw new \RuntimeException('No PayPal subscription ID found');
        }

        $accessToken = $this->getAccessToken();

        $this->httpClient->request('POST',
            $this->baseUrl . '/v1/billing/subscriptions/' . $subscription->getGatewaySubscriptionId() . '/cancel',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'reason' => 'User requested cancellation',
                ],
            ]
        );

        $subscription->setStatus(Subscription::STATUS_CANCELLED);
        $subscription->setCancelledAt(new \DateTimeImmutable());
        $subscription->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
    }

    /**
     * Handle PayPal webhook events
     */
    public function handleWebhook(array $payload): void
    {
        $eventType = $payload['event_type'] ?? null;

        switch ($eventType) {
            case 'BILLING.SUBSCRIPTION.ACTIVATED':
                $this->handleSubscriptionActivated($payload);
                break;

            case 'BILLING.SUBSCRIPTION.CANCELLED':
            case 'BILLING.SUBSCRIPTION.SUSPENDED':
            case 'BILLING.SUBSCRIPTION.EXPIRED':
                $this->handleSubscriptionCancelled($payload);
                break;

            case 'PAYMENT.SALE.COMPLETED':
                $this->handlePaymentCompleted($payload);
                break;

            case 'PAYMENT.SALE.REFUNDED':
                $this->handlePaymentRefunded($payload);
                break;
        }
    }

    private function handleSubscriptionActivated(array $payload): void
    {
        $subscriptionId = $payload['resource']['id'] ?? null;
        $customId = $payload['resource']['custom_id'] ?? null;

        if (!$customId) {
            return;
        }

        $user = $this->entityManager->getRepository(User::class)->find($customId);
        if (!$user) {
            return;
        }

        $subscription = $user->getSubscription();
        if (!$subscription) {
            $subscription = new Subscription();
            $subscription->setUser($user);
            $user->setSubscription($subscription);
        }

        $subscription->setPlan(Subscription::PLAN_PRO);
        $subscription->setStatus(Subscription::STATUS_ACTIVE);
        $subscription->setGateway(Subscription::GATEWAY_PAYPAL);
        $subscription->setGatewaySubscriptionId($subscriptionId);
        $subscription->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
    }

    private function handleSubscriptionCancelled(array $payload): void
    {
        $subscriptionId = $payload['resource']['id'] ?? null;

        $subscription = $this->entityManager
            ->getRepository(Subscription::class)
            ->findByGatewaySubscriptionId($subscriptionId);

        if (!$subscription) {
            return;
        }

        $subscription->setStatus(Subscription::STATUS_CANCELLED);
        $subscription->setCancelledAt(new \DateTimeImmutable());
        $subscription->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
    }

    private function handlePaymentCompleted(array $payload): void
    {
        $saleId = $payload['resource']['id'] ?? null;
        $amount = $payload['resource']['amount']['total'] ?? 0;
        $currency = $payload['resource']['amount']['currency'] ?? 'USD';

        // Try to find subscription by billing agreement ID
        $billingAgreementId = $payload['resource']['billing_agreement_id'] ?? null;
        if (!$billingAgreementId) {
            return;
        }

        $subscription = $this->entityManager
            ->getRepository(Subscription::class)
            ->findByGatewaySubscriptionId($billingAgreementId);

        if (!$subscription) {
            return;
        }

        $payment = new Payment();
        $payment->setSubscription($subscription);
        $payment->setGateway(Subscription::GATEWAY_PAYPAL);
        $payment->setTransactionId($saleId);
        $payment->setAmount((int) ($amount * 100));
        $payment->setCurrency(strtoupper($currency));
        $payment->setStatus(Payment::STATUS_COMPLETED);
        $payment->setCompletedAt(new \DateTimeImmutable());

        $this->entityManager->persist($payment);
        $this->entityManager->flush();
    }

    private function handlePaymentRefunded(array $payload): void
    {
        $saleId = $payload['resource']['sale_id'] ?? null;

        $payment = $this->entityManager
            ->getRepository(Payment::class)
            ->findByTransactionId($saleId);

        if (!$payment) {
            return;
        }

        $payment->setStatus(Payment::STATUS_REFUNDED);
        $payment->setMetadata(array_merge($payment->getMetadata() ?? [], [
            'refunded_at' => (new \DateTimeImmutable())->format('c'),
        ]));

        $this->entityManager->persist($payment);
        $this->entityManager->flush();
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $response = $this->httpClient->request('POST', $this->baseUrl . '/v1/oauth2/token', [
            'auth_basic' => [$this->clientId, $this->clientSecret],
            'body' => [
                'grant_type' => 'client_credentials',
            ],
        ]);

        $data = $response->toArray();
        $this->accessToken = $data['access_token'];

        return $this->accessToken;
    }

    private function getPlanIdForPlan(string $plan, string $interval): string
    {
        // In production, these would be actual PayPal Plan IDs from your PayPal dashboard
        // For now, returning placeholder values - replace with actual Plan IDs
        $planMap = [
            'pro_monthly' => 'P-PLAN-PRO-MONTHLY',
            'pro_yearly' => 'P-PLAN-PRO-YEARLY',
            'enterprise_monthly' => 'P-PLAN-ENT-MONTHLY',
            'enterprise_yearly' => 'P-PLAN-ENT-YEARLY',
        ];

        $key = $plan . '_' . $interval;
        return $planMap[$key] ?? $planMap['pro_monthly'];
    }
}

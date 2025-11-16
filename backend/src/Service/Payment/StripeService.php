<?php

namespace App\Service\Payment;

use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeService
{
    private StripeClient $stripe;
    private string $webhookSecret;

    public function __construct(
        private EntityManagerInterface $entityManager,
        string $secretKey,
        string $webhookSecret
    ) {
        Stripe::setApiKey($secretKey);
        $this->stripe = new StripeClient($secretKey);
        $this->webhookSecret = $webhookSecret;
    }

    /**
     * Create a checkout session for a plan upgrade
     */
    public function createCheckoutSession(
        User $user,
        string $plan,
        string $billingInterval = 'monthly',
        string $successUrl = null,
        string $cancelUrl = null
    ): Session {
        $priceId = $this->getPriceIdForPlan($plan, $billingInterval);

        $sessionData = [
            'payment_method_types' => ['card'],
            'mode' => 'subscription',
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'customer_email' => $user->getEmail(),
            'client_reference_id' => (string) $user->getId(),
            'metadata' => [
                'user_id' => $user->getId(),
                'plan' => $plan,
                'billing_interval' => $billingInterval,
            ],
            'success_url' => $successUrl ?? 'https://pourcha.app/billing/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl ?? 'https://pourcha.app/billing/cancel',
        ];

        return $this->stripe->checkout->sessions->create($sessionData);
    }

    /**
     * Create a one-time payment session (for enterprise or custom plans)
     */
    public function createOneTimePaymentSession(
        User $user,
        int $amount,
        string $currency = 'usd',
        string $description = 'Pourcha Payment',
        string $successUrl = null,
        string $cancelUrl = null
    ): Session {
        $sessionData = [
            'payment_method_types' => ['card'],
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $amount,
                    'product_data' => [
                        'name' => $description,
                    ],
                ],
                'quantity' => 1,
            ]],
            'customer_email' => $user->getEmail(),
            'client_reference_id' => (string) $user->getId(),
            'metadata' => [
                'user_id' => $user->getId(),
            ],
            'success_url' => $successUrl ?? 'https://pourcha.app/billing/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl ?? 'https://pourcha.app/billing/cancel',
        ];

        return $this->stripe->checkout->sessions->create($sessionData);
    }

    /**
     * Handle Stripe webhook events
     */
    public function handleWebhook(string $payload, string $signature): void
    {
        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $this->webhookSecret
            );
        } catch (\Exception $e) {
            throw new \RuntimeException('Invalid webhook signature: ' . $e->getMessage());
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutSessionCompleted($event->data->object);
                break;

            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event->data->object);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;

            case 'invoice.payment_succeeded':
                $this->handleInvoicePaymentSucceeded($event->data->object);
                break;

            case 'invoice.payment_failed':
                $this->handleInvoicePaymentFailed($event->data->object);
                break;
        }
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(Subscription $subscription): void
    {
        if ($subscription->getGatewaySubscriptionId()) {
            try {
                $this->stripe->subscriptions->cancel($subscription->getGatewaySubscriptionId());
            } catch (ApiErrorException $e) {
                throw new \RuntimeException('Failed to cancel Stripe subscription: ' . $e->getMessage());
            }
        }

        $subscription->setStatus(Subscription::STATUS_CANCELLED);
        $subscription->setCancelledAt(new \DateTimeImmutable());
        $subscription->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
    }

    private function handleCheckoutSessionCompleted($session): void
    {
        $userId = $session->metadata->user_id ?? $session->client_reference_id;
        $plan = $session->metadata->plan ?? Subscription::PLAN_PRO;

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            return;
        }

        $subscription = $user->getSubscription();
        if (!$subscription) {
            $subscription = new Subscription();
            $subscription->setUser($user);
            $user->setSubscription($subscription);
        }

        $subscription->setPlan($plan);
        $subscription->setStatus(Subscription::STATUS_ACTIVE);
        $subscription->setGateway(Subscription::GATEWAY_STRIPE);
        $subscription->setGatewaySubscriptionId($session->subscription);
        $subscription->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
    }

    private function handleSubscriptionUpdated($stripeSubscription): void
    {
        $subscription = $this->entityManager
            ->getRepository(Subscription::class)
            ->findByGatewaySubscriptionId($stripeSubscription->id);

        if (!$subscription) {
            return;
        }

        $subscription->setStatus($stripeSubscription->status === 'active' ? Subscription::STATUS_ACTIVE : Subscription::STATUS_CANCELLED);
        $subscription->setRenewalDate(new \DateTimeImmutable('@' . $stripeSubscription->current_period_end));
        $subscription->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
    }

    private function handleSubscriptionDeleted($stripeSubscription): void
    {
        $subscription = $this->entityManager
            ->getRepository(Subscription::class)
            ->findByGatewaySubscriptionId($stripeSubscription->id);

        if (!$subscription) {
            return;
        }

        $subscription->setStatus(Subscription::STATUS_CANCELLED);
        $subscription->setCancelledAt(new \DateTimeImmutable());
        $subscription->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
    }

    private function handleInvoicePaymentSucceeded($invoice): void
    {
        $subscription = $this->entityManager
            ->getRepository(Subscription::class)
            ->findByGatewaySubscriptionId($invoice->subscription);

        if (!$subscription) {
            return;
        }

        $payment = new Payment();
        $payment->setSubscription($subscription);
        $payment->setGateway(Subscription::GATEWAY_STRIPE);
        $payment->setTransactionId($invoice->payment_intent);
        $payment->setAmount($invoice->amount_paid);
        $payment->setCurrency(strtoupper($invoice->currency));
        $payment->setStatus(Payment::STATUS_COMPLETED);
        $payment->setCompletedAt(new \DateTimeImmutable());
        $payment->setMetadata([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->number,
        ]);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();
    }

    private function handleInvoicePaymentFailed($invoice): void
    {
        $subscription = $this->entityManager
            ->getRepository(Subscription::class)
            ->findByGatewaySubscriptionId($invoice->subscription);

        if (!$subscription) {
            return;
        }

        $payment = new Payment();
        $payment->setSubscription($subscription);
        $payment->setGateway(Subscription::GATEWAY_STRIPE);
        $payment->setTransactionId($invoice->payment_intent);
        $payment->setAmount($invoice->amount_due);
        $payment->setCurrency(strtoupper($invoice->currency));
        $payment->setStatus(Payment::STATUS_FAILED);
        $payment->setMetadata([
            'invoice_id' => $invoice->id,
            'error' => $invoice->last_finalization_error,
        ]);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();
    }

    private function getPriceIdForPlan(string $plan, string $interval): string
    {
        // In production, these would be actual Stripe Price IDs from your Stripe dashboard
        // For now, returning placeholder values - replace with actual Price IDs
        $priceMap = [
            'pro_monthly' => 'price_pro_monthly',
            'pro_yearly' => 'price_pro_yearly',
            'enterprise_monthly' => 'price_enterprise_monthly',
            'enterprise_yearly' => 'price_enterprise_yearly',
        ];

        $key = $plan . '_' . $interval;
        return $priceMap[$key] ?? $priceMap['pro_monthly'];
    }
}

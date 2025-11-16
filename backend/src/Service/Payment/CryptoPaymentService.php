<?php

namespace App\Service\Payment;

use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CryptoPaymentService
{
    private const SUPPORTED_CURRENCIES = ['BTC', 'USDT_SOLANA', 'USDC_SOLANA', 'MONERO'];

    private array $wallets;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient,
        array $wallets
    ) {
        $this->wallets = $wallets;
    }

    /**
     * Get wallet address for a specific cryptocurrency
     */
    public function getWalletAddress(string $currency): string
    {
        $currency = strtoupper($currency);

        return match ($currency) {
            'BTC' => $this->wallets['btc'] ?? '',
            'USDT_SOLANA' => $this->wallets['usdt_solana'] ?? '',
            'USDC_SOLANA' => $this->wallets['usdc_solana'] ?? '',
            'MONERO' => $this->wallets['monero'] ?? '',
            default => throw new \InvalidArgumentException('Unsupported cryptocurrency: ' . $currency),
        };
    }

    /**
     * Get all supported wallet addresses
     */
    public function getAllWallets(): array
    {
        return [
            'BTC' => $this->wallets['btc'] ?? '',
            'USDT_SOLANA' => $this->wallets['usdt_solana'] ?? '',
            'USDC_SOLANA' => $this->wallets['usdc_solana'] ?? '',
            'MONERO' => $this->wallets['monero'] ?? '',
        ];
    }

    /**
     * Create a crypto payment record
     */
    public function createPaymentRecord(
        User $user,
        string $currency,
        float $amount,
        string $plan
    ): Payment {
        $subscription = $user->getSubscription();
        if (!$subscription) {
            $subscription = new Subscription();
            $subscription->setUser($user);
            $user->setSubscription($subscription);
            $this->entityManager->persist($subscription);
        }

        $payment = new Payment();
        $payment->setSubscription($subscription);
        $payment->setGateway(Subscription::GATEWAY_CRYPTO);
        $payment->setCurrency($currency);
        $payment->setAmount((int) ($amount * 100)); // Store in cents
        $payment->setStatus(Payment::STATUS_PENDING);
        $payment->setMetadata([
            'plan' => $plan,
            'wallet_address' => $this->getWalletAddress($currency),
            'expected_amount' => $amount,
            'currency' => $currency,
        ]);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }

    /**
     * Verify a crypto payment manually (admin function)
     */
    public function verifyPayment(int $paymentId, string $transactionHash): void
    {
        $payment = $this->entityManager->getRepository(Payment::class)->find($paymentId);

        if (!$payment) {
            throw new \RuntimeException('Payment not found');
        }

        if ($payment->getStatus() === Payment::STATUS_COMPLETED) {
            throw new \RuntimeException('Payment already completed');
        }

        $payment->setTransactionId($transactionHash);
        $payment->setStatus(Payment::STATUS_COMPLETED);
        $payment->setCompletedAt(new \DateTimeImmutable());

        $metadata = $payment->getMetadata() ?? [];
        $metadata['verified_at'] = (new \DateTimeImmutable())->format('c');
        $payment->setMetadata($metadata);

        // Update subscription
        $subscription = $payment->getSubscription();
        $plan = $metadata['plan'] ?? Subscription::PLAN_PRO;

        $subscription->setPlan($plan);
        $subscription->setStatus(Subscription::STATUS_ACTIVE);
        $subscription->setGateway(Subscription::GATEWAY_CRYPTO);

        // Set renewal date based on plan (monthly or yearly)
        $renewalDate = new \DateTimeImmutable('+1 month');
        if (str_contains($plan, 'yearly')) {
            $renewalDate = new \DateTimeImmutable('+1 year');
        }
        $subscription->setRenewalDate($renewalDate);
        $subscription->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($payment);
        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
    }

    /**
     * Get payment details for frontend display
     */
    public function getPaymentDetails(int $paymentId): array
    {
        $payment = $this->entityManager->getRepository(Payment::class)->find($paymentId);

        if (!$payment) {
            throw new \RuntimeException('Payment not found');
        }

        $metadata = $payment->getMetadata() ?? [];

        return [
            'id' => $payment->getId(),
            'status' => $payment->getStatus(),
            'currency' => $payment->getCurrency(),
            'amount' => $metadata['expected_amount'] ?? 0,
            'wallet_address' => $metadata['wallet_address'] ?? '',
            'transaction_id' => $payment->getTransactionId(),
            'created_at' => $payment->getCreatedAt()->format('c'),
            'completed_at' => $payment->getCompletedAt()?->format('c'),
        ];
    }

    /**
     * Calculate crypto amount based on plan and currency
     */
    public function calculateCryptoAmount(string $plan, string $interval, string $currency): float
    {
        // Base prices in USD (cents)
        $prices = [
            'pro_monthly' => 999,    // $9.99
            'pro_yearly' => 9900,    // $99.00
        ];

        $key = $plan . '_' . $interval;
        $usdAmount = ($prices[$key] ?? $prices['pro_monthly']) / 100;

        // Get current exchange rates (in production, fetch from API)
        // For now, using approximate values
        $rates = [
            'BTC' => 40000,           // 1 BTC = $40,000
            'USDT_SOLANA' => 1,       // 1 USDT = $1
            'USDC_SOLANA' => 1,       // 1 USDC = $1
            'MONERO' => 150,          // 1 XMR = $150
        ];

        $rate = $rates[$currency] ?? 1;
        return round($usdAmount / $rate, 8);
    }

    /**
     * Get supported cryptocurrencies
     */
    public function getSupportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    /**
     * Check if payment is pending for too long (24 hours)
     */
    public function checkExpiredPayments(): void
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p')
            ->from(Payment::class, 'p')
            ->where('p.status = :status')
            ->andWhere('p.createdAt < :expiredDate')
            ->andWhere('p.gateway = :gateway')
            ->setParameter('status', Payment::STATUS_PENDING)
            ->setParameter('expiredDate', new \DateTimeImmutable('-24 hours'))
            ->setParameter('gateway', Subscription::GATEWAY_CRYPTO);

        $expiredPayments = $qb->getQuery()->getResult();

        foreach ($expiredPayments as $payment) {
            $payment->setStatus(Payment::STATUS_FAILED);
            $metadata = $payment->getMetadata() ?? [];
            $metadata['expired_at'] = (new \DateTimeImmutable())->format('c');
            $payment->setMetadata($metadata);
            $this->entityManager->persist($payment);
        }

        $this->entityManager->flush();
    }
}

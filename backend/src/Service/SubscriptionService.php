<?php

namespace App\Service;

use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class SubscriptionService
{
    private const PLAN_FEATURES = [
        Subscription::PLAN_FREE => [
            'max_requisitions' => 10,
            'integrations' => false,
            'catalog_filters' => 'basic',
            'support' => 'community',
        ],
        Subscription::PLAN_PRO => [
            'max_requisitions' => -1, // unlimited
            'integrations' => true,
            'catalog_filters' => 'advanced',
            'support' => 'email',
        ],
        Subscription::PLAN_ENTERPRISE => [
            'max_requisitions' => -1, // unlimited
            'integrations' => true,
            'catalog_filters' => 'advanced',
            'support' => 'priority',
            'custom_workflows' => true,
            'dedicated_support' => true,
        ],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private int $proMonthlyPrice,
        private int $proYearlyPrice
    ) {
    }

    /**
     * Get pricing for a plan
     */
    public function getPricing(string $plan, string $interval = 'monthly'): int
    {
        if ($plan === Subscription::PLAN_FREE) {
            return 0;
        }

        if ($plan === Subscription::PLAN_PRO) {
            return $interval === 'yearly' ? $this->proYearlyPrice : $this->proMonthlyPrice;
        }

        // Enterprise pricing is custom - contact sales
        return 0;
    }

    /**
     * Get all available plans with pricing
     */
    public function getPlans(): array
    {
        return [
            [
                'plan' => Subscription::PLAN_FREE,
                'name' => 'Free',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'features' => self::PLAN_FEATURES[Subscription::PLAN_FREE],
            ],
            [
                'plan' => Subscription::PLAN_PRO,
                'name' => 'Pro',
                'price_monthly' => $this->proMonthlyPrice,
                'price_yearly' => $this->proYearlyPrice,
                'features' => self::PLAN_FEATURES[Subscription::PLAN_PRO],
            ],
            [
                'plan' => Subscription::PLAN_ENTERPRISE,
                'name' => 'Enterprise',
                'price_monthly' => 0,
                'price_yearly' => 0,
                'description' => 'Contact sales for pricing',
                'features' => self::PLAN_FEATURES[Subscription::PLAN_ENTERPRISE],
            ],
        ];
    }

    /**
     * Check if user has access to a feature
     */
    public function hasFeature(User $user, string $feature): bool
    {
        $subscription = $user->getSubscription();

        if (!$subscription || !$subscription->isActive()) {
            // Default to free plan features
            return self::PLAN_FEATURES[Subscription::PLAN_FREE][$feature] ?? false;
        }

        $plan = $subscription->getPlan();
        return self::PLAN_FEATURES[$plan][$feature] ?? false;
    }

    /**
     * Get feature value for a user
     */
    public function getFeatureValue(User $user, string $feature)
    {
        $subscription = $user->getSubscription();

        if (!$subscription || !$subscription->isActive()) {
            return self::PLAN_FEATURES[Subscription::PLAN_FREE][$feature] ?? null;
        }

        $plan = $subscription->getPlan();
        return self::PLAN_FEATURES[$plan][$feature] ?? null;
    }

    /**
     * Upgrade a user's subscription
     */
    public function upgradePlan(User $user, string $newPlan, string $gateway, ?string $gatewaySubscriptionId = null): Subscription
    {
        $subscription = $user->getSubscription();

        if (!$subscription) {
            $subscription = new Subscription();
            $subscription->setUser($user);
            $user->setSubscription($subscription);
        }

        $subscription->setPlan($newPlan);
        $subscription->setStatus(Subscription::STATUS_ACTIVE);
        $subscription->setGateway($gateway);
        $subscription->setGatewaySubscriptionId($gatewaySubscriptionId);
        $subscription->setUpdatedAt(new \DateTimeImmutable());

        // Set renewal date
        $renewalDate = new \DateTimeImmutable('+1 month');
        $subscription->setRenewalDate($renewalDate);

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        return $subscription;
    }

    /**
     * Downgrade a user's subscription (e.g., after cancellation)
     */
    public function downgradeToFree(User $user): Subscription
    {
        $subscription = $user->getSubscription();

        if (!$subscription) {
            $subscription = new Subscription();
            $subscription->setUser($user);
            $user->setSubscription($subscription);
        }

        $subscription->setPlan(Subscription::PLAN_FREE);
        $subscription->setStatus(Subscription::STATUS_ACTIVE);
        $subscription->setGateway(null);
        $subscription->setGatewaySubscriptionId(null);
        $subscription->setRenewalDate(null);
        $subscription->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        return $subscription;
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(Subscription $subscription): void
    {
        $subscription->setStatus(Subscription::STATUS_CANCELLED);
        $subscription->setCancelledAt(new \DateTimeImmutable());
        $subscription->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();
    }

    /**
     * Check if a subscription has expired
     */
    public function checkExpiredSubscriptions(): void
    {
        $expiredDate = new \DateTimeImmutable();

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('s')
            ->from(Subscription::class, 's')
            ->where('s.status = :status')
            ->andWhere('s.renewalDate <= :expiredDate')
            ->andWhere('s.plan != :freePlan')
            ->setParameter('status', Subscription::STATUS_ACTIVE)
            ->setParameter('expiredDate', $expiredDate)
            ->setParameter('freePlan', Subscription::PLAN_FREE);

        $expiredSubscriptions = $qb->getQuery()->getResult();

        foreach ($expiredSubscriptions as $subscription) {
            $subscription->setStatus(Subscription::STATUS_EXPIRED);
            $subscription->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->persist($subscription);
        }

        $this->entityManager->flush();
    }

    /**
     * Get subscription summary for a user
     */
    public function getSubscriptionSummary(User $user): array
    {
        $subscription = $user->getSubscription();

        if (!$subscription) {
            return [
                'plan' => Subscription::PLAN_FREE,
                'status' => Subscription::STATUS_ACTIVE,
                'renewal_date' => null,
                'features' => self::PLAN_FEATURES[Subscription::PLAN_FREE],
                'can_upgrade' => true,
            ];
        }

        $canUpgrade = in_array($subscription->getPlan(), [Subscription::PLAN_FREE, Subscription::PLAN_PRO]);

        return [
            'plan' => $subscription->getPlan(),
            'status' => $subscription->getStatus(),
            'renewal_date' => $subscription->getRenewalDate()?->format('c'),
            'gateway' => $subscription->getGateway(),
            'features' => self::PLAN_FEATURES[$subscription->getPlan()] ?? [],
            'can_upgrade' => $canUpgrade,
            'can_cancel' => $subscription->getPlan() !== Subscription::PLAN_FREE && $subscription->isActive(),
        ];
    }
}

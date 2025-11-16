<?php

namespace App\Repository;

use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function findByUser(User $user): ?Subscription
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function findByGatewaySubscriptionId(string $gatewaySubscriptionId): ?Subscription
    {
        return $this->findOneBy(['gatewaySubscriptionId' => $gatewaySubscriptionId]);
    }

    public function findActiveSubscriptions(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->setParameter('status', Subscription::STATUS_ACTIVE)
            ->getQuery()
            ->getResult();
    }

    public function findExpiringSubscriptions(\DateTimeImmutable $beforeDate): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->andWhere('s.renewalDate <= :date')
            ->setParameter('status', Subscription::STATUS_ACTIVE)
            ->setParameter('date', $beforeDate)
            ->getQuery()
            ->getResult();
    }
}

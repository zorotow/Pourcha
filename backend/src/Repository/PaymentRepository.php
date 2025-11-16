<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\Subscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findBySubscription(Subscription $subscription): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.subscription = :subscription')
            ->setParameter('subscription', $subscription)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByTransactionId(string $transactionId): ?Payment
    {
        return $this->findOneBy(['transactionId' => $transactionId]);
    }

    public function findCompletedPayments(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', Payment::STATUS_COMPLETED)
            ->orderBy('p.completedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

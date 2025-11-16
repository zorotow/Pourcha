<?php

namespace App\Repository;

use App\Entity\Delegate;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DelegateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Delegate::class);
    }

    public function findActiveByUser(User $user): ?Delegate
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('d')
            ->where('d.user = :user')
            ->andWhere('d.isActive = :active')
            ->andWhere('(d.startDate IS NULL OR d.startDate <= :now)')
            ->andWhere('(d.endDate IS NULL OR d.endDate >= :now)')
            ->setParameter('user', $user)
            ->setParameter('active', true)
            ->setParameter('now', $now)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findDelegationsToUser(User $user): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('d')
            ->where('d.delegateTo = :user')
            ->andWhere('d.isActive = :active')
            ->andWhere('(d.startDate IS NULL OR d.startDate <= :now)')
            ->andWhere('(d.endDate IS NULL OR d.endDate >= :now)')
            ->setParameter('user', $user)
            ->setParameter('active', true)
            ->setParameter('now', $now)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

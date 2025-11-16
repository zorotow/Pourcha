<?php

namespace App\Repository;

use App\Entity\ChartOfAccounts;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ChartOfAccountsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChartOfAccounts::class);
    }

    public function findByTenant(Tenant $tenant, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->orderBy('c.costCenter', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
                ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    public function searchAccounts(Tenant $tenant, string $query): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.tenant = :tenant')
            ->andWhere('c.isActive = :active')
            ->andWhere('
                c.costCenter LIKE :query OR
                c.costCenterName LIKE :query OR
                c.fund LIKE :query OR
                c.fundName LIKE :query OR
                c.glAccount LIKE :query OR
                c.glAccountName LIKE :query
            ')
            ->setParameter('tenant', $tenant)
            ->setParameter('active', true)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.costCenter', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }
}

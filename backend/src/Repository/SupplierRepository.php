<?php

namespace App\Repository;

use App\Entity\Supplier;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SupplierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Supplier::class);
    }

    public function findByTenant(Tenant $tenant, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->orderBy('s.name', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('s.isActive = :active')
                ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByType(Tenant $tenant, string $type): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.tenant = :tenant')
            ->andWhere('s.type = :type')
            ->andWhere('s.isActive = :active')
            ->setParameter('tenant', $tenant)
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

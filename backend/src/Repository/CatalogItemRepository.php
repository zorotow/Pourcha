<?php

namespace App\Repository;

use App\Entity\CatalogItem;
use App\Entity\Tenant;
use App\Entity\Supplier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CatalogItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CatalogItem::class);
    }

    public function searchItems(Tenant $tenant, array $criteria = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.tenant = :tenant')
            ->andWhere('c.isAvailable = :available')
            ->setParameter('tenant', $tenant)
            ->setParameter('available', true);

        if (!empty($criteria['search'])) {
            $qb->andWhere('c.name LIKE :search OR c.description LIKE :search')
                ->setParameter('search', '%' . $criteria['search'] . '%');
        }

        if (!empty($criteria['supplier'])) {
            $qb->andWhere('c.supplier = :supplier')
                ->setParameter('supplier', $criteria['supplier']);
        }

        if (!empty($criteria['category'])) {
            $qb->andWhere('c.category = :category')
                ->setParameter('category', $criteria['category']);
        }

        if (!empty($criteria['brand'])) {
            $qb->andWhere('c.brand LIKE :brand')
                ->setParameter('brand', '%' . $criteria['brand'] . '%');
        }

        if (isset($criteria['minPrice'])) {
            $qb->andWhere('c.price >= :minPrice')
                ->setParameter('minPrice', $criteria['minPrice']);
        }

        if (isset($criteria['maxPrice'])) {
            $qb->andWhere('c.price <= :maxPrice')
                ->setParameter('maxPrice', $criteria['maxPrice']);
        }

        $sortBy = $criteria['sortBy'] ?? 'name';
        $order = $criteria['order'] ?? 'ASC';
        $qb->orderBy('c.' . $sortBy, $order);

        return $qb->getQuery()->getResult();
    }

    public function getCategories(Tenant $tenant): array
    {
        return $this->createQueryBuilder('c')
            ->select('DISTINCT c.category')
            ->where('c.tenant = :tenant')
            ->andWhere('c.category IS NOT NULL')
            ->setParameter('tenant', $tenant)
            ->orderBy('c.category', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function getBrands(Tenant $tenant): array
    {
        return $this->createQueryBuilder('c')
            ->select('DISTINCT c.brand')
            ->where('c.tenant = :tenant')
            ->andWhere('c.brand IS NOT NULL')
            ->setParameter('tenant', $tenant)
            ->orderBy('c.brand', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }
}

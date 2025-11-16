<?php

namespace App\Repository;

use App\Entity\IntegrationProvider;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IntegrationProvider>
 */
class IntegrationProviderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IntegrationProvider::class);
    }

    public function findByTenant(Tenant $tenant): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->orderBy('i.provider', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByTenantAndProvider(Tenant $tenant, string $provider): ?IntegrationProvider
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.tenant = :tenant')
            ->andWhere('i.provider = :provider')
            ->setParameter('tenant', $tenant)
            ->setParameter('provider', $provider)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findConnectedIntegrations(Tenant $tenant): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.tenant = :tenant')
            ->andWhere('i.status = :status')
            ->setParameter('tenant', $tenant)
            ->setParameter('status', IntegrationProvider::STATUS_CONNECTED)
            ->getQuery()
            ->getResult();
    }
}

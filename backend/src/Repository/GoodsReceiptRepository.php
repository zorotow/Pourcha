<?php

namespace App\Repository;

use App\Entity\GoodsReceipt;
use App\Entity\Requisition;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GoodsReceipt>
 */
class GoodsReceiptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoodsReceipt::class);
    }

    public function generateReceiptNumber(): string
    {
        $lastReceipt = $this->createQueryBuilder('gr')
            ->orderBy('gr.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$lastReceipt) {
            return 'GR-' . date('Ymd') . '-0001';
        }

        // Extract number from last receipt number
        $lastNumber = (int) substr($lastReceipt->getReceiptNumber(), -4);
        $newNumber = $lastNumber + 1;

        return 'GR-' . date('Ymd') . '-' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    public function findByRequisition(Requisition $requisition): array
    {
        return $this->createQueryBuilder('gr')
            ->andWhere('gr.requisition = :requisition')
            ->setParameter('requisition', $requisition)
            ->orderBy('gr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingReceipts(Tenant $tenant): array
    {
        return $this->createQueryBuilder('gr')
            ->join('gr.requisition', 'r')
            ->join('r.createdBy', 'u')
            ->andWhere('u.tenant = :tenant')
            ->andWhere('gr.status = :status')
            ->setParameter('tenant', $tenant)
            ->setParameter('status', GoodsReceipt::STATUS_DRAFT)
            ->orderBy('gr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

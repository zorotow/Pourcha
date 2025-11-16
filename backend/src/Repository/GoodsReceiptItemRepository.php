<?php

namespace App\Repository;

use App\Entity\GoodsReceiptItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GoodsReceiptItem>
 */
class GoodsReceiptItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GoodsReceiptItem::class);
    }
}

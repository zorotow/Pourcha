<?php

namespace App\Repository;

use App\Entity\Approval;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ApprovalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Approval::class);
    }

    public function findPendingByApprover(User $approver): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.approver = :approver')
            ->andWhere('a.status = :status')
            ->setParameter('approver', $approver)
            ->setParameter('status', Approval::STATUS_PENDING)
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

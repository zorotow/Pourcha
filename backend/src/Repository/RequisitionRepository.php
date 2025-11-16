<?php

namespace App\Repository;

use App\Entity\Requisition;
use App\Entity\User;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RequisitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Requisition::class);
    }

    public function generateRequisitionNumber(): string
    {
        $lastRequisition = $this->createQueryBuilder('r')
            ->orderBy('r.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $nextNumber = $lastRequisition ? ($lastRequisition->getId() + 1) : 1;
        return sprintf('REQ-%07d', $nextNumber);
    }

    public function findByUser(User $user, array $statuses = []): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.createdBy = :user OR r.onBehalfOf = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC');

        if (!empty($statuses)) {
            $qb->andWhere('r.status IN (:statuses)')
                ->setParameter('statuses', $statuses);
        }

        return $qb->getQuery()->getResult();
    }

    public function findPendingApprovals(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.approvals', 'a')
            ->where('r.status = :status')
            ->andWhere('a.approver = :user')
            ->andWhere('a.status = :approvalStatus')
            ->setParameter('status', Requisition::STATUS_PENDING_APPROVAL)
            ->setParameter('user', $user)
            ->setParameter('approvalStatus', 'pending')
            ->orderBy('r.submittedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

<?php

namespace App\Repository;

use App\Entity\Invoice;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    public function findByUser(User $user, array $statuses = []): array
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.submittedBy = :user')
            ->setParameter('user', $user)
            ->orderBy('i.createdAt', 'DESC');

        if (!empty($statuses)) {
            $qb->andWhere('i.status IN (:statuses)')
                ->setParameter('statuses', $statuses);
        }

        return $qb->getQuery()->getResult();
    }

    public function findPendingApprovals(User $user): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.approvals', 'a')
            ->where('i.status = :status')
            ->andWhere('a.approver = :user')
            ->andWhere('a.status = :approvalStatus')
            ->setParameter('status', Invoice::STATUS_PENDING_APPROVAL)
            ->setParameter('user', $user)
            ->setParameter('approvalStatus', 'pending')
            ->orderBy('i.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

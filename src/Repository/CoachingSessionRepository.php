<?php

namespace App\Repository;

use App\Entity\Coach;
use App\Entity\CoachingSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CoachingSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CoachingSession::class);
    }

    /**
     * @return CoachingSession[]
     */
    public function findForCoachBetween(Coach $coach, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.coach = :coach')
            ->andWhere('s.scheduledAt >= :from')
            ->andWhere('s.scheduledAt < :to')
            ->setParameter('coach', $coach)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('s.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

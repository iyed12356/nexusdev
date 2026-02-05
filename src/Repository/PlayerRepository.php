<?php

namespace App\Repository;

use App\Entity\Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PlayerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Player::class);
    }

    /**
     * @return Player[]
     */
    public function findProPlayers(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isPro = :pro')
            ->setParameter('pro', true)
            ->orderBy('p.score', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

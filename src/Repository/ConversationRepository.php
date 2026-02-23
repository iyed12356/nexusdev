<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    public function save(Conversation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Conversation $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Conversation[]
     */
    public function findConversationsForUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.participants', 'p')
            ->leftJoin('c.messages', 'm')
            ->addSelect('p', 'm')
            ->where('p.id = :userId')
            ->setParameter('userId', $user->getId())
            ->orderBy('c.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findConversationBetweenUsers(User $user1, User $user2): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', [$user1->getId(), $user2->getId()])
            ->groupBy('c.id')
            ->having('COUNT(DISTINCT p.id) = 2')
            ->getQuery()
            ->getOneOrNullResult();
    }
}

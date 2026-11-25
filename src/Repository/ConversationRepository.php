<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Listing;
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

    /** @return Conversation[] */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('l', 'a', 'b')
            ->join('c.listing', 'l')
            ->join('c.userA', 'a')
            ->join('c.userB', 'b')
            ->where('c.userA = :user OR c.userB = :user')
            ->setParameter('user', $user)
            ->orderBy('c.lastMessageAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findBetween(Listing $listing, User $one, User $two): ?Conversation
    {
        [$a, $b] = $one->getId() < $two->getId() ? [$one, $two] : [$two, $one];

        return $this->findOneBy(['listing' => $listing, 'userA' => $a, 'userB' => $b]);
    }
}

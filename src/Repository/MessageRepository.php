<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /** @return Message[] */
    public function findForConversation(Conversation $conversation): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('s')
            ->join('m.sender', 's')
            ->where('m.conversation = :conversation')
            ->setParameter('conversation', $conversation)
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $conversationIds
     *
     * @return array<int, Message> Last message keyed by conversation id
     */
    public function findLastByConversations(array $conversationIds): array
    {
        if ([] === $conversationIds) {
            return [];
        }

        $messages = $this->createQueryBuilder('m')
            ->addSelect('s')
            ->join('m.sender', 's')
            ->where('m.conversation IN (:ids)')
            ->setParameter('ids', $conversationIds)
            ->orderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();

        $last = [];
        foreach ($messages as $message) {
            $last[$message->getConversation()->getId()] = $message;
        }

        return $last;
    }

    /**
     * @param int[] $conversationIds
     *
     * @return array<int, int> Unread count keyed by conversation id
     */
    public function countUnreadByConversations(array $conversationIds, User $user): array
    {
        if ([] === $conversationIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.conversation) AS cid, COUNT(m.id) AS n')
            ->where('m.conversation IN (:ids)')
            ->andWhere('m.sender != :user')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('ids', $conversationIds)
            ->setParameter('user', $user)
            ->groupBy('cid')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['cid']] = (int) $row['n'];
        }

        return $counts;
    }

    public function markRead(Conversation $conversation, User $user): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.readAt', ':now')
            ->where('m.conversation = :conversation')
            ->andWhere('m.sender != :user')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('conversation', $conversation)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    public function countUnreadForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.conversation IN (SELECT c.id FROM App\Entity\Conversation c WHERE c.userA = :user OR c.userB = :user)')
            ->andWhere('m.sender != :user')
            ->andWhere('m.readAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

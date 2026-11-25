<?php

namespace App\Repository;

use App\Entity\Listing;
use App\Entity\TradeOffer;
use App\Entity\User;
use App\Enum\TradeOfferStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TradeOffer> */
class TradeOfferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TradeOffer::class);
    }

    /** @return list<TradeOffer> */
    public function findRelated(User $user): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.listing', 'l')->addSelect('l')
            ->leftJoin('o.offeredListing', 'ol')->addSelect('ol')
            ->andWhere('o.sender = :user OR o.receiver = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingBySenderAndListing(User $sender, Listing $listing): ?TradeOffer
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.sender = :sender')
            ->andWhere('o.listing = :listing')
            ->andWhere('o.status = :pending')
            ->setParameter('sender', $sender)
            ->setParameter('listing', $listing)
            ->setParameter('pending', TradeOfferStatus::PENDING)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Rejects every still-pending offer that involves one of the given listings.
     *
     * @param list<int> $listingIds
     */
    public function autoRejectFor(array $listingIds, TradeOffer $except): int
    {
        if ([] === $listingIds) {
            return 0;
        }

        return (int) $this->createQueryBuilder('o')
            ->update()
            ->set('o.status', ':rejected')
            ->set('o.respondedAt', ':now')
            ->where('o.status = :pending')
            ->andWhere('o.id != :exceptId')
            ->andWhere('(o.listing IN (:ids) OR o.offeredListing IN (:ids))')
            ->setParameter('rejected', TradeOfferStatus::REJECTED)
            ->setParameter('pending', TradeOfferStatus::PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('exceptId', $except->getId())
            ->setParameter('ids', $listingIds)
            ->getQuery()
            ->execute();
    }

    public function expirePending(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->update()
            ->set('o.status', ':expired')
            ->where('o.status = :pending')
            ->andWhere('o.expiresAt < :now')
            ->setParameter('expired', TradeOfferStatus::EXPIRED)
            ->setParameter('pending', TradeOfferStatus::PENDING)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    public function findCompletedForListing(Listing $listing): ?TradeOffer
    {
        return $this->createQueryBuilder('o')
            ->addSelect('s', 'r')
            ->join('o.sender', 's')
            ->join('o.receiver', 'r')
            ->where('o.status = :completed')
            ->andWhere('o.listing = :listing OR o.offeredListing = :listing')
            ->setParameter('completed', TradeOfferStatus::COMPLETED)
            ->setParameter('listing', $listing)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countCompleted(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status = :completed')
            ->setParameter('completed', TradeOfferStatus::COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

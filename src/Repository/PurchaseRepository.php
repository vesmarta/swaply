<?php

namespace App\Repository;

use App\Entity\Listing;
use App\Entity\Purchase;
use App\Entity\User;
use App\Enum\PurchaseStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Purchase>
 */
class PurchaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Purchase::class);
    }

    /** @return Purchase[] */
    public function findRelated(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('l', 'b', 's')
            ->join('p.listing', 'l')
            ->join('p.buyer', 'b')
            ->join('p.seller', 's')
            ->where('p.buyer = :user OR p.seller = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPendingByBuyerAndListing(User $buyer, Listing $listing): ?Purchase
    {
        return $this->findOneBy([
            'buyer' => $buyer,
            'listing' => $listing,
            'status' => PurchaseStatus::PENDING,
        ]);
    }

    /** @param int[] $listingIds */
    public function autoRejectFor(array $listingIds, Purchase $except): void
    {
        if ([] === $listingIds) {
            return;
        }

        $this->createQueryBuilder('p')
            ->update()
            ->set('p.status', ':rejected')
            ->set('p.respondedAt', ':now')
            ->where('p.listing IN (:ids)')
            ->andWhere('p.status = :pending')
            ->andWhere('p.id != :except')
            ->setParameter('rejected', PurchaseStatus::REJECTED)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('ids', $listingIds)
            ->setParameter('pending', PurchaseStatus::PENDING)
            ->setParameter('except', $except->getId())
            ->getQuery()
            ->execute();
    }

    public function findCompletedForListing(Listing $listing): ?Purchase
    {
        return $this->createQueryBuilder('p')
            ->addSelect('b', 's')
            ->join('p.buyer', 'b')
            ->join('p.seller', 's')
            ->where('p.listing = :listing')
            ->andWhere('p.status = :completed')
            ->setParameter('listing', $listing)
            ->setParameter('completed', PurchaseStatus::COMPLETED)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countCompleted(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :completed')
            ->setParameter('completed', PurchaseStatus::COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

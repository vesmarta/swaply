<?php

namespace App\Repository;

use App\Entity\Listing;
use App\Entity\User;
use App\Enum\ListingStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Listing> */
class ListingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Listing::class);
    }

    /**
     * @param array{q?: string, category?: string, governorate?: string, condition?: string, type?: string, min?: int|string, max?: int|string} $filters
     *
     * @return array{items: list<Listing>, total: int}
     */
    public function search(array $filters = [], string $sort = 'newest', int $page = 1, int $perPage = 12): array
    {
        $qb = $this->baseSearch($filters);
        $this->applySort($qb, $sort);
        $qb->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage);

        /** @var list<Listing> $items */
        $items = $qb->getQuery()->getResult();

        $total = (int) $this->baseSearch($filters)
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return ['items' => $items, 'total' => $total];
    }

    /** @return list<Listing> */
    public function findFeatured(int $limit = 4): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.status = :active')
            ->andWhere('l.isFeatured = true')
            ->setParameter('active', ListingStatus::ACTIVE)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Listing> */
    public function findRecent(int $limit = 8): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.status = :active')
            ->setParameter('active', ListingStatus::ACTIVE)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Listing> */
    public function findBySeller(User $seller): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.seller = :seller')
            ->setParameter('seller', $seller)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Listing> */
    public function findActiveForOfferSelection(User $owner): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.seller = :owner')
            ->andWhere('l.status = :active')
            ->setParameter('owner', $owner)
            ->setParameter('active', ListingStatus::ACTIVE)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(ListingStatus $status): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<Listing> */
    public function findPending(int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->addSelect('s', 'c')
            ->join('l.seller', 's')
            ->join('l.category', 'c')
            ->where('l.status = :pending')
            ->setParameter('pending', ListingStatus::PENDING)
            ->orderBy('l.createdAt', \SortDirection::Ascending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @param array<string, mixed> $filters */
    private function baseSearch(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.status = :active')
            ->setParameter('active', ListingStatus::ACTIVE);

        $q = trim((string) ($filters['q'] ?? ''));
        if ('' !== $q) {
            $qb->andWhere('l.title LIKE :q OR l.description LIKE :q')
                ->setParameter('q', '%'.$q.'%');
        }

        if (!empty($filters['category'])) {
            $qb->join('l.category', 'c')
                ->andWhere('c.slug = :category')
                ->setParameter('category', $filters['category']);
        }

        if (!empty($filters['governorate'])) {
            $qb->andWhere('l.governorate = :governorate')
                ->setParameter('governorate', $filters['governorate']);
        }

        if (!empty($filters['condition'])) {
            $qb->andWhere('l.condition = :condition')
                ->setParameter('condition', $filters['condition']);
        }

        if (!empty($filters['type'])) {
            if ('sale' === $filters['type']) {
                $qb->andWhere("l.type IN ('sale', 'both')");
            } elseif ('swap' === $filters['type']) {
                $qb->andWhere("l.type IN ('swap', 'both')");
            }
        }

        if (isset($filters['min']) && '' !== $filters['min']) {
            $qb->andWhere('l.priceMillimes >= :min')
                ->setParameter('min', (int) $filters['min']);
        }

        if (isset($filters['max']) && '' !== $filters['max']) {
            $qb->andWhere('l.priceMillimes <= :max')
                ->setParameter('max', (int) $filters['max']);
        }

        return $qb;
    }

    private function applySort(QueryBuilder $qb, string $sort): void
    {
        match ($sort) {
            'price_asc' => $qb->addSelect('COALESCE(l.priceMillimes, 999999999999) AS HIDDEN sortPrice')
                ->orderBy('sortPrice', 'ASC'),
            'price_desc' => $qb->addSelect('COALESCE(l.priceMillimes, -1) AS HIDDEN sortPrice')
                ->orderBy('sortPrice', 'DESC'),
            'popular' => $qb->orderBy('l.viewCount', 'DESC'),
            default => $qb->orderBy('l.createdAt', 'DESC'),
        };
    }
}

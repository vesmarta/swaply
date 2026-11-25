<?php

namespace App\Repository;

use App\Entity\Listing;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    public function findOneByAuthorAndListing(User $author, Listing $listing): ?Review
    {
        return $this->findOneBy(['author' => $author, 'listing' => $listing]);
    }

    /** @return array{avg: float|null, count: int} */
    public function statsFor(User $subject): array
    {
        $row = $this->createQueryBuilder('r')
            ->select('AVG(r.rating) AS avg, COUNT(r.id) AS n')
            ->where('r.subject = :subject')
            ->setParameter('subject', $subject)
            ->getQuery()
            ->getArrayResult()[0] ?? ['avg' => null, 'n' => 0];

        return [
            'avg' => null !== $row['avg'] ? round((float) $row['avg'], 1) : null,
            'count' => (int) $row['n'],
        ];
    }

    /** @return Review[] */
    public function findRecentForSubject(User $subject, int $limit = 3): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('a')
            ->join('r.author', 'a')
            ->where('r.subject = :subject')
            ->setParameter('subject', $subject)
            ->orderBy('r.createdAt', \SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

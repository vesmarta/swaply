<?php

namespace App\Repository;

use App\Entity\Favorite;
use App\Entity\Listing;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Favorite>
 */
class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    public function isFavorited(User $user, Listing $listing): bool
    {
        return null !== $this->findOneBy(['user' => $user, 'listing' => $listing]);
    }

    /** @return Listing[] */
    public function findListingsFor(User $user): array
    {
        $favorites = $this->createQueryBuilder('f')
            ->addSelect('l', 'c')
            ->join('f.listing', 'l')
            ->join('l.category', 'c')
            ->where('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.createdAt', \SortDirection::Descending)
            ->getQuery()
            ->getResult();

        return array_map(static fn (Favorite $favorite) => $favorite->getListing(), $favorites);
    }
}

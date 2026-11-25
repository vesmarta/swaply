<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /** @return list<User> */
    public function searchUsers(string $q = ''): array
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', \SortDirection::Descending)
            ->setMaxResults(200);

        if ('' !== $q) {
            $qb->andWhere('u.email LIKE :q OR u.username LIKE :q OR u.firstName LIKE :q OR u.lastName LIKE :q')
                ->setParameter('q', '%'.$q.'%');
        }

        return $qb->getQuery()->getResult();
    }
}

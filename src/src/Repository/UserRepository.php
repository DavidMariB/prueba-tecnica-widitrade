<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry){
        parent::__construct($registry, User::class);
    }

    public function registerUser(User $user) {
        
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();

    }

    public function userExists(string $username, string $email): bool
    {
        $existingUser = $this->createQueryBuilder('u')
            ->where('u.username = :username OR u.email = :email')
            ->setParameter('username', $username)
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();

        return $existingUser !== null;
    }

    public function updateUser(User $user): User {

        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();

        return $user;
    }

    public function deleteUser(User $user) {

        $this->getEntityManager()->remove($user);
        $this->getEntityManager()->flush();
    }

}

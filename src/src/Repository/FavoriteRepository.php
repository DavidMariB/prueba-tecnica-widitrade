<?php

// src/Repository/FavoriteRepository.php

namespace App\Repository;

use App\Entity\Favorite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    public function addFavorite(Favorite $favorite)
    {
        $this->getEntityManager()->persist($favorite);
        $this->getEntityManager()->flush();
    }

    public function deleteFavorite(Favorite $favorite)
    {
        $this->getEntityManager()->remove($favorite);
        $this->getEntityManager()->flush();
    }
}
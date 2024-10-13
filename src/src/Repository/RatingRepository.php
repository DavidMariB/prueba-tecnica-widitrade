<?php

namespace App\Repository;

use App\Entity\Rating;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rating::class);
    }

    public function saveOrUpdateRating(Rating $rating)
    {

        $this->getEntityManager()->persist($rating);
        $this->getEntityManager()->flush();

    }

    public function deleteRating(Rating $rating)
    {

        $this->getEntityManager()->remove($rating);
        $this->getEntityManager()->flush();

    }
}

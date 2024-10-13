<?php

// src/Repository/AccessTokenRepository.php
namespace App\Repository;

use App\Entity\AccessToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AccessTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccessToken::class);
    }

    public function saveToken(AccessToken $accessToken): void
    {
        $this->getEntityManager()->persist($accessToken);
        $this->getEntityManager()->flush();
    }

}
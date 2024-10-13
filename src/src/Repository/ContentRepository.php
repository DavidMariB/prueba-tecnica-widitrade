<?php

namespace App\Repository;

use App\Entity\Content;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Content>
 */
class ContentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Content::class);
    }

    public function createContent(Content $content)
    {

        $this->getEntityManager()->persist($content);
        $this->getEntityManager()->flush();
    }

    public function updateContent(Content $content)
    {

        $this->getEntityManager()->persist($content);
        $this->getEntityManager()->flush();

    }

    public function deleteContent(Content $content)
    {

        $this->getEntityManager()->remove($content);
        $this->getEntityManager()->flush();
    }

    public function findByFilters(string $title = '', string $description = '', int $limit = 10, int $offset = 0): array
    {

        $qb = $this->createQueryBuilder('c')
            ->select('c.title, c.description, c.media_urls')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $conditions = [];
        $params = [];

        if ($title) {
            $conditions[] = 'LOWER(c.title) LIKE LOWER(:title)';
            $params['title'] = '%' . strtolower($title) . '%';
        }

        if ($description) {
            $conditions[] = 'LOWER(c.description) LIKE LOWER(:description)';
            $params['description'] = '%' . strtolower($description) . '%';
        }

        if ($conditions) {
            $qb->andWhere(implode(' OR ', $conditions));
            foreach ($params as $key => $value) {
                $qb->setParameter($key, $value);
            }
        }

        return $qb->getQuery()->getResult();

    }
}

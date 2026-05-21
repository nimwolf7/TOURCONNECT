<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\DBAL\Types\Types;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Find all activity logs ordered by most recent first
     * @return ActivityLog[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.timestamp', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ActivityLog[]
     */
    public function findByActionKeywords(array $keywords): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.timestamp', 'DESC');

        if ($keywords === []) {
            return $qb->getQuery()->getResult();
        }

        foreach (array_values($keywords) as $index => $keyword) {
            $param = 'keyword_' . $index;
            $qb->orWhere("a.action LIKE :$param")
                ->setParameter($param, '%' . $keyword . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return ActivityLog[]
     */
    public function findFiltered(array $keywords, ?\DateTimeInterface $fromDate): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.timestamp', 'DESC');

        if ($keywords !== []) {
            $orX = $qb->expr()->orX();
            foreach (array_values($keywords) as $index => $keyword) {
                $param = 'keyword_' . $index;
                $orX->add("a.action LIKE :$param");
                $qb->setParameter($param, '%' . $keyword . '%');
            }
            $qb->andWhere($orX);
        }

        if ($fromDate !== null) {
            $qb->andWhere('a.timestamp >= :fromDate')
                ->setParameter('fromDate', $fromDate, Types::DATETIME_MUTABLE);
        }

        return $qb->getQuery()->getResult();
    }

    //    /**
    //     * @return ActivityLog[] Returns an array of ActivityLog objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ActivityLog
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

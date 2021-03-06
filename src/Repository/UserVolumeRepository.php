<?php

namespace App\Repository;

use App\Entity\UserVolume;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method UserVolume|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserVolume|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserVolume[]    findAll()
 * @method UserVolume[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserVolumeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserVolume::class);
    }

    /**
     * Method to get the number of available volumes to lend on the website
     * @return array
     */
    public function getAvailableCount() : array
    {
        return $this->createQueryBuilder('volume')
            ->select('count(volume.id) as count')
            ->where('volume.status = 1')
            ->getQuery()
            ->getSingleResult();
    }
    // /**
    //  * @return UserVolume[] Returns an array of UserVolume objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?UserVolume
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}

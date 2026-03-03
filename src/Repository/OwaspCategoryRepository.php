<?php

namespace App\Repository;

use App\Entity\OwaspCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OwaspCategory>
 *
 * @method OwaspCategory|null find($id, $lockMode = null, $lockVersion = null)
 * @method OwaspCategory|null findOneBy(array $criteria, array $orderBy = null)
 * @method OwaspCategory[]    findAll()
 * @method OwaspCategory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OwaspCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OwaspCategory::class);
    }

    public function findByCode(string $code): ?OwaspCategory
    {
        return $this->findOneBy(['code' => $code]);
    }
}

<?php

namespace App\Repository;

use App\Entity\MappingRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MappingRule>
 *
 * @method MappingRule|null find($id, $lockMode = null, $lockVersion = null)
 * @method MappingRule|null findOneBy(array $criteria, array $orderBy = null)
 * @method MappingRule[]    findAll()
 * @method MappingRule[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MappingRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MappingRule::class);
    }

    public function findByToolType(string $toolType): array
    {
        return $this->findBy(['toolType' => $toolType]);
    }

    public function findByToolTypeAndMatchType(string $toolType, string $matchType): array
    {
        return $this->findBy(['toolType' => $toolType, 'matchType' => $matchType]);
    }
}

<?php

namespace App\Entity;

use App\Repository\MappingRuleRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * MappingRule Entity
 * 
 * Définit une règle pour mapper automatiquement les vulnérabilités détectées
 * vers une catégorie OWASP spécifique.
 * 
 * Chaque règle a:
 * - Un pattern (regex ou mot-clé)
 * - Un type de correspondance (regex, keyword, severity)
 * - Un type d'outil (sast, dependency, secrets, etc.)
 * - Un score de confiance (0-100)
 * 
 * Exemple:
 * - Pattern: "SQLite|sql_injection"
 * - MatchType: "keyword"
 * - Confidence: 90
 * - Maps to: A05 (Injection)
 */
#[ORM\Entity(repositoryClass: MappingRuleRepository::class)]
#[ORM\Table(name: 'mapping_rule')]
class MappingRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Relation ManyToOne avec OwaspCategory
     * Chaque règle est liée à exactement une catégorie OWASP
     */
    #[ORM\ManyToOne(targetEntity: OwaspCategory::class, inversedBy: 'mappingRules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private OwaspCategory $owaspCategory;

    /** Pattern à chercher: regex ou mot-clé selon le type de correspondance */
    #[ORM\Column(type: 'string', length: 255)]
    private string $pattern;

    /** Type de correspondance: 'regex', 'keyword', ou 'severity' */
    #[ORM\Column(type: 'string', length: 50)]
    private string $matchType;

    /** Type d'outil concerné: 'sast', 'dependency', 'secrets', etc. */
    #[ORM\Column(type: 'string', length: 100)]
    private string $toolType;

    /** Confiance du mappage (0-100). Plus élevé = plus sûr */
    #[ORM\Column(type: 'integer')]
    private int $confidence = 80;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Obtenir la catégorie OWASP associée
     */
    public function getOwaspCategory(): OwaspCategory
    {
        return $this->owaspCategory;
    }

    /**
     * Associer une catégorie OWASP
     */
    public function setOwaspCategory(?OwaspCategory $owaspCategory): self
    {
        $this->owaspCategory = $owaspCategory;
        return $this;
    }

    /**
     * Obtenir le pattern (regex ou mot-clé)
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * Définir le pattern
     */
    public function setPattern(string $pattern): self
    {
        $this->pattern = $pattern;
        return $this;
    }

    /**
     * Obtenir le type de correspondance
     */
    public function getMatchType(): string
    {
        return $this->matchType;
    }

    /**
     * Définir le type de correspondance
     */
    public function setMatchType(string $matchType): self
    {
        $this->matchType = $matchType;
        return $this;
    }

    /**
     * Obtenir le type d'outil
     */
    public function getToolType(): string
    {
        return $this->toolType;
    }

    /**
     * Définir le type d'outil
     */
    public function setToolType(string $toolType): self
    {
        $this->toolType = $toolType;
        return $this;
    }

    /**
     * Obtenir le score de confiance (0-100)
     */
    public function getConfidence(): int
    {
        return $this->confidence;
    }

    /**
     * Définir le score de confiance
     * S'assure que la valeur reste entre 0 et 100
     */
    public function setConfidence(int $confidence): self
    {
        $this->confidence = max(0, min(100, $confidence));
        return $this;
    }
}

<?php

namespace App\Entity;

use App\Repository\OwaspCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\MappingRule;

/**
 * OwaspCategory Entity
 * 
 * Représente une catégorie OWASP Top 10 2025 (A01-A10).
 * Cette entité stocke les informations sur chaque catégorie de vulnérabilité
 * et maintient une relation avec les règles de mappage et les vulnérabilités.
 * 
 * Exemple:
 * - Code: 'A01'
 * - Name: 'Broken Access Control'
 * - Description: Une description détaillée de la catégorie
 * - Examples: ['SQL Injection', 'Path Traversal', etc.]
 */
#[ORM\Entity(repositoryClass: OwaspCategoryRepository::class)]
#[ORM\Table(name: 'owasp_category')]
class OwaspCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** Code OWASP: A01, A02, A03, etc. */
    #[ORM\Column(type: 'string', length: 10)]
    private string $code;

    /** Nom complet de la catégorie: 'Broken Access Control', etc. */
    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    /** Description détaillée de la catégorie et des risques */
    #[ORM\Column(type: 'text')]
    private string $description;

    /** Tableau d'exemples de vulnérabilités détectables dans cette catégorie */
    #[ORM\Column(type: 'json')]
    private array $examples = [];

    /**
     * Relation OneToMany avec MappingRule
     * Une catégorie OWASP peut avoir plusieurs règles de mappage
     * @var Collection<int, MappingRule>
     */
    #[ORM\OneToMany(targetEntity: MappingRule::class, mappedBy: 'owaspCategory', cascade: ['persist', 'remove'])]
    private Collection $mappingRules;

    public function __construct()
    {
        $this->mappingRules = new ArrayCollection();
    }

    /**
     * Obtenir l'identifiant unique de la catégorie
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Obtenir le code OWASP (A01, A02, etc.)
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Définir le code OWASP
     */
    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    /**
     * Obtenir le nom complet de la catégorie
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Définir le nom de la catégorie
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Obtenir la description complète
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Définir la description
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Obtenir les exemples de vulnérabilités
     */
    public function getExamples(): array
    {
        return $this->examples;
    }

    /**
     * Définir les exemples
     */
    public function setExamples(array $examples): self
    {
        $this->examples = $examples;
        return $this;
    }

    /**
     * Obtenir toutes les règles de mappage associées
     * @return Collection<int, MappingRule>
     */
    public function getMappingRules(): Collection
    {
        return $this->mappingRules;
    }

    /**
     * Ajouter une règle de mappage
     */
    public function addMappingRule(MappingRule $mappingRule): self
    {
        if (!$this->mappingRules->contains($mappingRule)) {
            $this->mappingRules->add($mappingRule);
            $mappingRule->setOwaspCategory($this);
        }
        return $this;
    }

    /**
     * Retirer une règle de mappage
     */
    public function removeMappingRule(MappingRule $mappingRule): self
    {
        if ($this->mappingRules->removeElement($mappingRule)) {
            if ($mappingRule->getOwaspCategory() === $this) {
                $mappingRule->setOwaspCategory(null);
            }
        }
        return $this;
    }
}

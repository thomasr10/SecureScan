<?php

namespace App\Service;

use App\Entity\MappingRule;
use App\Entity\OwaspCategory;
use App\Entity\Vulnerability;
use App\Repository\MappingRuleRepository;
use App\Repository\OwaspCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * OwaspMappingService
 * 
 * Service responsable du mappage automatique des vulnérabilités détectées
 * vers les catégories OWASP Top 10 2025.
 * 
 * Flux de fonctionnement:
 * 1. Récupère les règles de mappage pour l'outil de détection (semgrep, npm-audit, etc.)
 * 2. Évalue chaque règle contre la vulnérabilité
 * 3. Utilise 3 stratégies de correspondance:
 *    - Mot-clé: Recherche case-insensitive dans titre/description
 *    - Regex: Pattern matching pour correspondances complexes
 *    - Sévérité: Basé sur le niveau de sévérité
 * 4. Sélectionne la meilleure correspondance (confiance la plus élevée)
 * 5. Persiste le mappage avec un score de confiance
 * 
 * Exemple:
 * $category = $service->mapVulnerability($vulnerability);
 * // Result: Vulnerability est maintenant liée à A05 (Injection) avec 90% de confiance
 */
class OwaspMappingService
{
    public function __construct(
        private MappingRuleRepository $mappingRuleRepository,
        private OwaspCategoryRepository $owaspCategoryRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Mapper une vulnérabilité à une catégorie OWASP
     * 
     * Algorithme:
     * 1. Récupère les règles applicables pour cet outil
     * 2. Évalue chaque règle contre la vulnérabilité
     * 3. Sélectionne la règle avec la confiance la plus élevée
     * 4. Met à jour la vulnérabilité avec la catégorie et la confiance
     * 5. Persist l'entité en attente de flush
     * 
     * @param Vulnerability $vulnerability La vulnérabilité à mapper
     * @return ?OwaspCategory La catégorie OWASP mappée, ou null si pas de correspondance
     */
    public function mapVulnerability(Vulnerability $vulnerability): ?OwaspCategory
    {
        // Récupère les règles disponibles pour cet outil (ex: 'semgrep', 'npm-audit')
        $rules = $this->mappingRuleRepository->findByToolType($vulnerability->getToolName());

        $bestMatch = null;
        $highestConfidence = 0;

        // Teste toutes les règles et garde la meilleure correspondance
        foreach ($rules as $rule) {
            $matchConfidence = $this->evaluateRule($vulnerability, $rule);

            // Compare avec la confiance actuelle la plus élevée
            if ($matchConfidence > $highestConfidence) {
                $highestConfidence = $matchConfidence;
                $bestMatch = $rule->getOwaspCategory();
            }
        }

        // Si une correspondance a été trouvée, met à jour la vulnérabilité
        if ($bestMatch) {
            $vulnerability->setOwaspCategory($bestMatch);
            $vulnerability->setConfidenceScore((int)$highestConfidence);
            $this->entityManager->persist($vulnerability);
        }

        return $bestMatch;
    }

    /**
     * Évaluer si une règle correspond à une vulnérabilité
     * 
     * Teste la règle selon son type de correspondance:
     * - 'severity': Compare les niveaux de sévérité
     * - 'keyword': Recherche des mots-clés dans le titre/description
     * - 'regex': Teste un pattern regex
     * 
     * @param Vulnerability $vulnerability La vulnérabilité à évaluer
     * @param MappingRule $rule La règle à appliquer
     * @return float Score de confiance (0-100)
     */
    private function evaluateRule(Vulnerability $vulnerability, MappingRule $rule): float
    {
        $confidence = 0.0;
        $matchType = $rule->getMatchType();
        $pattern = $rule->getPattern();

        // Test basé sur la sévérité (ex: "high" >= "high")
        if ($matchType === 'severity') {
            if ($this->matchSeverity($vulnerability->getSeverity(), $pattern)) {
                $confidence = $rule->getConfidence();
            }
        }
        // Test de mot-clé case-insensitive
        elseif ($matchType === 'keyword') {
            if ($this->matchKeyword($vulnerability, $pattern)) {
                $confidence = $rule->getConfidence();
            }
        }
        // Test de pattern regex
        elseif ($matchType === 'regex') {
            if ($this->matchRegex($vulnerability, $pattern)) {
                $confidence = $rule->getConfidence();
            }
        }

        return $confidence;
    }

    /**
     * Vérifier la correspondance de sévérité
     * 
     * Compare deux niveaux de sévérité (critical > high > medium > low)
     * La correspondance est positive si la vulnérabilité est >= au pattern
     * 
     * Exemple:
     * - Vulnérabilité 'high' vs Pattern 'high' → true
     * - Vulnérabilité 'critical' vs Pattern 'high' → true
     * - Vulnérabilité 'medium' vs Pattern 'high' → false
     * 
     * @param string $severity Sévérité de la vulnérabilité
     * @param string $pattern Sévérité minimale requise
     * @return bool true si la sévérité atteint le seuil
     */
    private function matchSeverity(string $severity, string $pattern): bool
    {
        // Carte de niveaux de sévérité
        $severityMap = [
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
        ];

        $vulnLevel = $severityMap[strtolower($severity)] ?? 0;
        $patternLevel = $severityMap[strtolower($pattern)] ?? 0;

        return $vulnLevel >= $patternLevel;
    }

    /**
     * Vérifier la correspondance de mot-clé
     * 
     * Fait une recherche case-insensitive du mot-clé dans:
     * - Le titre de la vulnérabilité
     * - La description complète
     * 
     * Exemple:
     * - Keyword: "SQL" → Rechnert dans "SQL Injection Attack" → match
     * - Keyword: "csrf" → Recherche dans "Cross-site request forgery" → match
     * 
     * @param Vulnerability $vulnerability La vulnérabilité à vérifier
     * @param string $keyword Le mot-clé recherché
     * @return bool true si le mot-clé est trouvé
     */
    private function matchKeyword(Vulnerability $vulnerability, string $keyword): bool
    {
        $keyword = strtolower($keyword);
        $title = strtolower($vulnerability->getTitle());
        $description = strtolower($vulnerability->getDescription());

        return str_contains($title, $keyword) || str_contains($description, $keyword);
    }

    /**
     * Vérifier la correspondance de regex
     * 
     * Teste un pattern regex contre le titre et la description.
     * Gère les erreurs regex silencieusement (@ supprime les avertissements).
     * 
     * Exemple:
     * - Pattern: "/sql_?injection/i" → Cherche injections SQL
     * - Pattern: "/xss|cross.site/i" → Cherche XSS
     * 
     * @param Vulnerability $vulnerability La vulnérabilité à vérifier
     * @param string $pattern Le pattern regex à utiliser
     * @return bool true si le pattern match
     */
    private function matchRegex(Vulnerability $vulnerability, string $pattern): bool
    {
        try {
            $title = $vulnerability->getTitle();
            $description = $vulnerability->getDescription();

            // Teste le pattern sur le titre et la description
            $titleMatch = @preg_match($pattern, $title) === 1;
            $descriptionMatch = @preg_match($pattern, $description) === 1;

            return $titleMatch || $descriptionMatch;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Mapper plusieurs vulnérabilités en batch
     * 
     * Utile pour traiter plusieurs vulnérabilités en une seule opération.
     * Flush la base de données une seule fois à la fin.
     * 
     * @param array $vulnerabilities Tableau de vulnérabilités à mapper
     * @return array Résultats du mappage avec catégories et confiances
     */
    public function batchMapVulnerabilities(array $vulnerabilities): array
    {
        $mappedResults = [];

        // Mappe chaque vulnérabilité
        foreach ($vulnerabilities as $vulnerability) {
            $category = $this->mapVulnerability($vulnerability);
            $mappedResults[] = [
                'vulnerability' => $vulnerability,
                'owaspCategory' => $category,
                'confidence' => $vulnerability->getConfidenceScore(),
            ];
        }

        // Flush une seule fois à la fin pour la performance
        $this->entityManager->flush();

        return $mappedResults;
    }

    /**
     * Obtenir les statistiques de vulnérabilités par catégorie OWASP
     * 
     * Génère un résumé pour chaque catégorie:
     * - Nombre total de vulnérabilités
     * - Nombre par niveau de sévérité (critical, high, medium, low)
     * 
     * Utilisé pour les tableaux de bord et rapports.
     * 
     * @return array Statistiques groupées par code OWASP (A01, A02, etc.)
     */
    public function getVulnerabilityStatistics(): array
    {
        $categories = $this->owaspCategoryRepository->findAll();
        $stats = [];

        // Parcourt chaque catégorie OWASP
        foreach ($categories as $category) {
            // Récupère les vulnérabilités mappées à cette catégorie
            $vulnerabilities = $this->entityManager
                ->getRepository(Vulnerability::class)
                ->findByOwasp($category->getId());

            // Agrège les statistiques
            $stats[$category->getCode()] = [
                'category' => $category,
                'count' => count($vulnerabilities),
                'bySeverity' => $this->groupVulnerabilitiesBySeverity($vulnerabilities),
            ];
        }

        return $stats;
    }

    /**
     * Grouper les vulnérabilités par niveau de sévérité
     * 
     * Utilitaire pour compter les vulnérabilités par sévérité
     * dans une liste donnée.
     * 
     * @param array $vulnerabilities Tableau de vulnérabilités
     * @return array Compte pour chaque sévérité (critical, high, medium, low)
     */
    private function groupVulnerabilitiesBySeverity(array $vulnerabilities): array
    {
        $grouped = [
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        // Compte chaque vulnérabilité dans sa catégorie de sévérité
        foreach ($vulnerabilities as $vuln) {
            $severity = strtolower($vuln->getSeverity());
            if (isset($grouped[$severity])) {
                $grouped[$severity]++;
            }
        }

        return $grouped;
    }
}

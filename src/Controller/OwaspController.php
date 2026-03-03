<?php

namespace App\Controller;

use App\Repository\OwaspCategoryRepository;
use App\Repository\VulnerabilityRepository;
use App\Service\OwaspMappingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * OwaspController
 * 
 * Contrôleur API REST pour accéder aux données de mappage OWASP.
 * 
 * Endpoint de base: /api/owasp
 * 
 * Endpoints disponibles:
 * 1. GET /api/owasp/categories - Toutes les catégories OWASP
 * 2. GET /api/owasp/category/{code} - Détails d'une catégorie avec ses vulnérabilités
 * 3. GET /api/owasp/statistics - Statistiques agrégées par sévérité
 * 4. GET /api/owasp/vulnerabilities - Liste complète des vulnérabilités mappées
 * 
 * Format de réponse: JSON
 * 
 * Exemple d'utilisation:
 * curl http://localhost:8000/api/owasp/categories
 * curl http://localhost:8000/api/owasp/statistics
 */
#[Route('/api/owasp', name: 'app_owasp_')]
class OwaspController extends AbstractController
{
    public function __construct(
        private OwaspMappingService $mappingService,
        private OwaspCategoryRepository $owaspCategoryRepository,
        private VulnerabilityRepository $vulnerabilityRepository,
    ) {}

    /**
     * GET /api/owasp/categories
     * 
     * Récupère la liste complète des catégories OWASP
     * avec le nombre de vulnérabilités pour chacune.
     * 
     * Réponse JSON:
     * [
     *   {
     *     "id": 1,
     *     "code": "A01",
     *     "name": "Broken Access Control",
     *     "description": "...",
     *     "examples": ["IDOR", "CORS misconfiguration", ...],
     *     "vulnerabilityCount": 5
     *   },
     *   ...
     * ]
     * 
     * @return JsonResponse Array de catégories avec count
     */
    #[Route('/categories', name: 'categories', methods: ['GET'])]
    public function getCategories(): JsonResponse
    {
        // Récupère toutes les catégories
        $categories = $this->owaspCategoryRepository->findAll();
        $data = [];

        // Formate chaque catégorie avec le compte de vulnérabilités
        foreach ($categories as $category) {
            // Compte les vulnérabilités mappées à cette catégorie
            $vulnCount = count($this->vulnerabilityRepository->findByOwasp($category->getId()));
            
            $data[] = [
                'id' => $category->getId(),
                'code' => $category->getCode(),
                'name' => $category->getName(),
                'description' => $category->getDescription(),
                'examples' => $category->getExamples(),
                'vulnerabilityCount' => $vulnCount,
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * GET /api/owasp/category/{code}
     * 
     * Récupère les détails d'une catégorie OWASP spécifique
     * avec toutes les vulnérabilités mappées à cette catégorie.
     * 
     * Paramètres:
     * - code: Code OWASP (ex: A01, A05, etc.)
     * 
     * Réponse JSON:
     * {
     *   "code": "A05",
     *   "name": "Injection",
     *   "description": "...",
     *   "examples": ["SQL injection", "XSS", ...],
     *   "vulnerabilities": [
     *     {
     *       "id": 1,
     *       "title": "SQL Injection in UserRepository",
     *       "description": "...",
     *       "severity": "critical",
     *       "tool": "semgrep",
     *       "file": "src/Repository/UserRepository.php",
     *       "line": 28,
     *       "confidence": 95
     *     },
     *     ...
     *   ]
     * }
     * 
     * Erreur:
     * - 404 si la catégorie n'existe pas
     * 
     * @param string $code Le code OWASP (ex: A01)
     * @return JsonResponse Détails de la catégorie et vulnérabilités
     */
    #[Route('/category/{code}', name: 'category_detail', methods: ['GET'])]
    public function getCategoryDetail(string $code): JsonResponse
    {
        // Recherche la catégorie par code
        $category = $this->owaspCategoryRepository->findByCode($code);

        // Retourne 404 si pas trouvée
        if (!$category) {
            return new JsonResponse(['error' => 'Category not found'], 404);
        }

        // Récupère les vulnérabilités mappées à cette catégorie
        $vulnerabilities = $this->vulnerabilityRepository->findByOwasp($category->getId());

        // Formate et retourne les résultats
        return new JsonResponse([
            'code' => $category->getCode(),
            'name' => $category->getName(),
            'description' => $category->getDescription(),
            'examples' => $category->getExamples(),
            // Transforme les vulnérabilités en format JSON
            'vulnerabilities' => array_map(function ($vuln) {
                return [
                    'id' => $vuln->getId(),
                    'title' => $vuln->getTitle(),
                    'description' => $vuln->getDescription(),
                    'severity' => $vuln->getSeverity(),
                    'tool' => $vuln->getToolName(),
                    'file' => $vuln->getFilePath(),
                    'line' => $vuln->getLineNumber(),
                    'confidence' => $vuln->getConfidenceScore(),
                ];
            }, $vulnerabilities),
        ]);
    }

    /**
     * GET /api/owasp/statistics
     * 
     * Récupère les statistiques agrégées des vulnérabilités
     * par catégorie OWASP et par niveau de sévérité.
     * 
     * Utile pour:
     * - Tableaux de bord
     * - Vue d'ensemble des risques
     * - Rapports de sécurité
     * 
     * Réponse JSON:
     * {
     *   "statistics": [
     *     {
     *       "code": "A01",
     *       "name": "Broken Access Control",
     *       "totalVulnerabilities": 3,
     *       "bySeverity": {
     *         "critical": 1,
     *         "high": 2,
     *         "medium": 0,
     *         "low": 0
     *       }
     *     },
     *     ...
     *   ],
     *   "totalVulnerabilities": 24
     * }
     * 
     * @return JsonResponse Statistiques par catégorie et sévérité
     */
    #[Route('/statistics', name: 'statistics', methods: ['GET'])]
    public function getStatistics(): JsonResponse
    {
        // Récupère les statistiques via le service
        $statistics = $this->mappingService->getVulnerabilityStatistics();
        $data = [];

        // Formate chaque statistique de catégorie
        foreach ($statistics as $code => $stat) {
            $data[] = [
                'code' => $code,
                'name' => $stat['category']->getName(),
                'totalVulnerabilities' => $stat['count'],
                'bySeverity' => $stat['bySeverity'], // critical, high, medium, low
            ];
        }

        // Calcule le total de toutes les vulnérabilités
        $totalVulns = array_sum(array_column($data, 'totalVulnerabilities'));

        return new JsonResponse([
            'statistics' => $data,
            'totalVulnerabilities' => $totalVulns,
        ]);
    }

    /**
     * GET /api/owasp/vulnerabilities
     * 
     * Récupère la liste complète de TOUTES les vulnérabilités mappées,
     * avec leurs informations de mappage OWASP et de confiance.
     * 
     * Utile pour:
     * - Export de données
     * - Listes complètes
     * - Intégration avec d'autres systèmes
     * 
     * Réponse JSON:
     * {
     *   "total": 24,
     *   "vulnerabilities": [
     *     {
     *       "id": 1,
     *       "title": "Reflected XSS in user input",
     *       "description": "...",
     *       "severity": "high",
     *       "tool": "semgrep",
     *       "file": "src/Controller/SearchController.php",
     *       "line": 42,
     *       "owasp": {
     *         "code": "A05",
     *         "name": "Injection"
     *       },
     *       "confidence": 95,
     *       "detectedAt": "2024-03-03T10:30:00+01:00"
     *     },
     *     ...
     *   ]
     * }
     * 
     * @return JsonResponse Liste complète des vulnérabilités avec mappages
     */
    #[Route('/vulnerabilities', name: 'vulnerabilities', methods: ['GET'])]
    public function getVulnerabilities(): JsonResponse
    {
        // Récupère toutes les vulnérabilités
        $vulnerabilities = $this->vulnerabilityRepository->findAll();
        $data = [];

        // Formate chaque vulnérabilité avec son mappage OWASP
        foreach ($vulnerabilities as $vuln) {
            // Formate la date de détection si elle existe
            $detectedAt = $vuln->getDetectedAt();
            $detectedAtFormatted = $detectedAt ? $detectedAt->format('c') : null;
            
            $data[] = [
                'id' => $vuln->getId(),
                'title' => $vuln->getTitle(),
                'description' => $vuln->getDescription(),
                'severity' => $vuln->getSeverity(),
                'tool' => $vuln->getToolName(),
                'file' => $vuln->getFilePath(),
                'line' => $vuln->getLineNumber(),
                // Mappe OWASP (null si pas mappé)
                'owasp' => $vuln->getOwaspCategory() ? [
                    'code' => $vuln->getOwaspCategory()->getCode(),
                    'name' => $vuln->getOwaspCategory()->getName(),
                ] : null,
                // Score de confiance du mappage
                'confidence' => $vuln->getConfidenceScore(),
                // Timestamp ISO 8601
                'detectedAt' => $detectedAtFormatted,
            ];
        }

        // Retourne avec le total
        return new JsonResponse([
            'total' => count($data),
            'vulnerabilities' => $data,
        ]);
    }
}

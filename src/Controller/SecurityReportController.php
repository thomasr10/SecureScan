<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * SecurityReportController
 * 
 * Contrôleur pour les pages Web de rapports de sécurité et de visualisation.
 * 
 * Contrairement à OwaspController qui fournit une API REST JSON,
 * ce contrôleur fournit des pages HTML pour la visualisation interactive.
 * 
 * Routes fournies:
 * - GET /mapping - Interface web pour visualiser les vulnérabilités mappées à OWASP
 */
#[Route('/', name: 'app_')]
class SecurityReportController extends AbstractController
{
    /**
     * GET /mapping
     * 
     * Affiche la page interactive de mappage OWASP.
     * 
     * Cette page:
     * 1. Affiche un header avec titre et dégradé
     * 2. Charge les statistiques via l'API /api/owasp/statistics
     * 3. Affiche les catégories OWASP en cartes colorées
     * 4. Liste toutes les vulnérabilités avec leur mappage
     * 5. Permet d'explorer les détails de chaque catégorie
     * 
     * Données chargées en JavaScript asynchrone (fetch):
     * - GET /api/owasp/statistics - Statistiques aggrégées
     * - GET /api/owasp/categories - Liste des catégories
     * - GET /api/owasp/vulnerabilities - Liste des vulnérabilités
     * - GET /api/owasp/category/{code} - Détails d'une catégorie
     * 
     * Template utilisé: templates/owasp/mapping.html.twig
     * 
     * @return Response La page HTML de mappage
     */
    #[Route('/mapping', name: 'mapping', methods: ['GET'])]
    public function mapping(): Response
    {
        // Rend le template Twig avec les données (chargées en JavaScript côté client)
        return $this->render('owasp/mapping.html.twig');
    }
}

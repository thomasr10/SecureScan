<?php

namespace App\Command;

use App\Entity\Vulnerability;
use App\Service\OwaspMappingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * DemoMapVulnerabilitiesCommand
 * 
 * Commande Symfony pour créer des vulnérabilités de démonstration
 * et les mapper automatiquement à des catégories OWASP.
 * 
 * Utilisation:
 * php bin/console app:demo-map-vulnerabilities
 * 
 * Objectif:
 * - Créer des données de test réalistes
 * - Démontrer le mappage automatique OWASP
 * - Remplir la base de données pour les tests et démos
 * - Montrer comment le système fonctionne en action
 * 
 * Contenu:
 * Crée 8 vulnérabilités variées couvrant:
 * - XSS (A05, Cross-site scripting)
 * - SQL Injection (A05, Injection)
 * - Debug enabled (A02, Security Misconfiguration)
 * - CVE dans dépendances (A03, Supply Chain)
 * - Secrets exposés (A04, Cryptographic Failures)
 * - CORS misconfiguration (A01, Broken Access Control)
 * - Weak hashing (A04, Cryptographic Failures)
 * - Deprecated packages (A03, Supply Chain)
 * 
 * Note: Peut être exécutée plusieurs fois
 * (crée de nouvelles vulnérabilités à chaque fois)
 */
#[AsCommand(
    name: 'app:demo-map-vulnerabilities',
    description: 'Demo command to create example vulnerabilities and map them to OWASP categories',
)]
class DemoMapVulnerabilitiesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OwaspMappingService $mappingService,
    ) {
        parent::__construct();
    }

    /**
     * Execute la commande
     * 
     * Étapes:
     * 1. Affiche un message de démarrage
     * 2. Définit un tableau de 8 vulnérabilités de test
     * 3. Pour chaque vulnérabilité:
     *    a. Crée une entité Vulnerability
     *    b. Mappé à une catégorie OWASP via OwaspMappingService
     *    c. Persiste en base de données
     *    d. Affiche le résultat du mappage
     * 4. Flush toutes les entités
     * 5. Affiche l'URL pour voir les résultats
     * 
     * @param InputInterface $input Interface d'entrée
     * @param OutputInterface $output Interface de sortie
     * @return int Code de retour (0 = success)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Creating demo vulnerabilities...');

        /**
         * Tableau de vulnérabilités de démonstration
         * 
         * Chaque vulnérabilité contient:
         * - title: Nom court de la vulnérabilité
         * - description: Description détaillée
         * - severity: Niveau (critical, high, medium, low)
         * - tool: Outil qui l'a détectée (semgrep, npm-audit, etc.)
         * - file: Chemin du fichier affecté
         * - line: Numéro de ligne (null si non applicable)
         * 
         * Mappage prévu pour chaque vulnérabilité:
         * 1. XSS → A05 (Injection)
         * 2. SQL Injection → A05 (Injection)
         * 3. Debug enabled → A02 (Security Misconfiguration)
         * 4. npm CVE → A03 (Supply Chain)
         * 5. Exposed secret → A04 (Cryptographic)
         * 6. CORS → A01 (Access Control)
         * 7. MD5 hashing → A04 (Cryptographic)
         * 8. Composer CVE → A03 (Supply Chain)
         */
        $demoVulnerabilities = [
            // Injection - XSS
            [
                'title' => 'Reflected XSS in user input',
                'description' => 'User input is not properly escaped before being rendered in the HTML page',
                'severity' => 'high',
                'tool' => 'semgrep',
                'file' => 'src/Controller/SearchController.php',
                'line' => 42,
            ],
            
            // Injection - SQL
            [
                'title' => 'SQL Injection vulnerability in database query',
                'description' => 'User input concatenated directly into SQL query without parameterized statements',
                'severity' => 'critical',
                'tool' => 'semgrep',
                'file' => 'src/Repository/UserRepository.php',
                'line' => 28,
            ],
            
            // Security Misconfiguration
            [
                'title' => 'Debug mode enabled in production',
                'description' => 'APP_DEBUG is set to true, exposing sensitive information',
                'severity' => 'high',
                'tool' => 'configuration-scanner',
                'file' => '.env.production',
                'line' => 2,
            ],
            
            // Supply Chain - npm
            [
                'title' => 'Outdated lodash package with known CVE',
                'description' => 'lodash 4.17.15 has a prototype pollution vulnerability (CVE-2019-1010266)',
                'severity' => 'critical',
                'tool' => 'npm-audit',
                'file' => 'package.json',
                'line' => null,
            ],
            
            // Cryptographic - Secret
            [
                'title' => 'AWS API key found in source code',
                'description' => 'AWS_SECRET_ACCESS_KEY exposed in environment configuration file',
                'severity' => 'critical',
                'tool' => 'git-secrets',
                'file' => 'src/config/aws.php',
                'line' => 5,
            ],
            
            // Broken Access Control
            [
                'title' => 'Missing CORS headers validation',
                'description' => 'CORS headers not properly configured, allowing unauthorized cross-origin requests',
                'severity' => 'medium',
                'tool' => 'semgrep',
                'file' => 'src/Middleware/SecurityHeaders.php',
                'line' => 15,
            ],
            
            // Cryptographic - Weak hash
            [
                'title' => 'Weak password hashing algorithm (MD5)',
                'description' => 'Passwords are hashed using MD5 instead of bcrypt or argon2',
                'severity' => 'high',
                'tool' => 'semgrep',
                'file' => 'src/Security/PasswordManager.php',
                'line' => 22,
            ],
            
            // Supply Chain - Composer
            [
                'title' => 'Vulnerable symfony/http-foundation package',
                'description' => 'symfony/http-foundation 5.0.0 has a security issue (CVE-2022-24894)',
                'severity' => 'high',
                'tool' => 'composer-audit',
                'file' => 'composer.lock',
                'line' => null,
            ],
        ];

        // Crée et mappe chaque vulnérabilité
        foreach ($demoVulnerabilities as $vulnData) {
            // Crée une nouvelle entité Vulnerability
            $vuln = new Vulnerability();
            $vuln->setTitle($vulnData['title']);
            $vuln->setDescription($vulnData['description']);
            $vuln->setSeverity($vulnData['severity']);
            $vuln->setToolName($vulnData['tool']);
            $vuln->setFilePath($vulnData['file']);
            $vuln->setLineNumber($vulnData['line']);

            // Mappe automatiquement à une catégorie OWASP
            // OwaspMappingService analyse les règles et sélectionne la meilleure correspondance
            $this->mappingService->mapVulnerability($vuln);

            // Persiste la vulnérabilité
            $this->entityManager->persist($vuln);

            // Affiche le résultat du mappage
            $owaspCode = $vuln->getOwaspCategory() ? $vuln->getOwaspCategory()->getCode() : 'NOT MAPPED';
            $output->writeln("<info>✓</info> Created: {$vuln->getTitle()} → {$owaspCode}");
        }

        // Flush toutes les vulnérabilités à la base de données
        $this->entityManager->flush();
        $output->writeln('<fg=green>Demo vulnerabilities created and mapped!</>');
        $output->writeln('View results at: http://localhost:8000/mapping');

        return Command::SUCCESS;
    }
}

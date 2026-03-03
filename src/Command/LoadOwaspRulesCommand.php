<?php

namespace App\Command;

use App\Entity\MappingRule;
use App\Entity\OwaspCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * LoadOwaspRulesCommand
 * 
 * Commande Symfony pour charger les catégories OWASP Top 10 2025
 * et les règles de mappage associées en base de données.
 * 
 * Utilisation:
 * php bin/console app:load-owasp-rules
 * 
 * Processus:
 * 1. Vide les tables existantes (MappingRule et OwaspCategory)
 * 2. Crée 5 catégories OWASP (A01-A05)
 * 3. Pour chaque catégorie, crée plusieurs règles de mappage
 * 4. Persiste tout en base de données
 * 5. Affiche un résumé des catégories chargées
 * 
 * Résultat: 5 catégories avec ~23 règles de mappage
 * 
 * Note: Cette commande doit être exécutée une seule fois pour initialiser
 * ou après un reset complet de la base de données.
 */
#[AsCommand(
    name: 'app:load-owasp-rules',
    description: 'Load OWASP Top 10 2025 categories and mapping rules',
)]
class LoadOwaspRulesCommand extends Command
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    /**
     * Execute la commande
     * 
     * Étapes:
     * 1. Affiche un message de démarrage
     * 2. Vide les données existantes
     * 3. Charge les définitions de catégories depuis getOwaspCategories()
     * 4. Crée les entités OwaspCategory et MappingRule
     * 5. Persiste en base et flush
     * 6. Affiche un message de succès
     * 
     * @param InputInterface $input Interface d'entrée
     * @param OutputInterface $output Interface de sortie
     * @return int Code de retour (0 = success)
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Loading OWASP Top 10 2025 categories and mapping rules...');

        // Nettoie les données existantes pour réinitialiser complètement
        // Ordre important: d'abord tout le reste (foreign key), ensuite les catégories
        // Les vulnérabilités doivent être supprimées avant les règles et catégories
        $this->entityManager->createQuery('DELETE FROM App\Entity\Vulnerability')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\MappingRule')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\OwaspCategory')->execute();

        // Récupère les définitions de catégories
        $categories = $this->getOwaspCategories();

        // Crée les entités pour chaque catégorie et ses règles
        foreach ($categories as $categoryData) {
            // Crée la catégorie OWASP
            $category = new OwaspCategory();
            $category->setCode($categoryData['code']);
            $category->setName($categoryData['name']);
            $category->setDescription($categoryData['description']);
            $category->setExamples($categoryData['examples']);

            // Persiste la catégorie
            $this->entityManager->persist($category);

            // Crée les règles de mappage pour cette catégorie
            foreach ($categoryData['rules'] as $ruleData) {
                $rule = new MappingRule();
                $rule->setOwaspCategory($category);
                $rule->setPattern($ruleData['pattern']);
                $rule->setMatchType($ruleData['matchType']);
                $rule->setToolType($ruleData['toolType']);
                $rule->setConfidence($ruleData['confidence'] ?? 85);

                // Persiste la règle
                $this->entityManager->persist($rule);
            }

            // Affiche le progrès
            $output->writeln("<info>✓</info> Loaded {$category->getCode()}: {$category->getName()}");
        }

        // Flush toutes les entités à la base de données
        $this->entityManager->flush();
        $output->writeln('<fg=green>OWASP rules loaded successfully!</>');

        return Command::SUCCESS;
    }

    /**
     * Retourne les définitions de catégories OWASP
     * 
     * Structure de chaque définition:
     * - code: Identifiant OWASP (A01-A10)
     * - name: Nom complet
     * - description: Description détaillée
     * - examples: Tableau d'exemples de vulnérabilités
     * - rules: Tableau de règles de mappage
     * 
     * Structure de chaque règle:
     * - pattern: Le pattern à rechercher (regex ou mot-clé)
     * - matchType: Type de correspondance ('keyword', 'regex', ou 'severity')
     * - toolType: Type d'outil ('semgrep', 'npm-audit', 'git-secrets', etc.)
     * - confidence: Score de confiance (0-100)
     * 
     * Cette fonction peut être étendue pour inclure A06-A10
     * 
     * @return array Tableau de catégories avec leurs règles
     */
    private function getOwaspCategories(): array
    {
        return [
            // ===== A01: Broken Access Control =====
            /**
             * A01: Broken Access Control
             * 
             * Accès non autorisé à des ressources par:
             * - IDOR (Insecure Direct Object References)
             * - Contournement de vérifications de permission
             * - Escalade de privilèges
             * - Configurations CORS incorrectes
             * 
             * Règles:
             * - IDOR: Recherche du mot-clé 'idor'
             * - CORS: Configurations CORS incorrectes
             * - Privilèges: Escalade de privilèges
             * - Access control: Patterns généraux d'accès
             */
            [
                'code' => 'A01',
                'name' => 'Broken Access Control',
                'description' => 'Unauthorized access to resources through permission bypass, IDOR, or privilege escalation',
                'examples' => ['IDOR', 'CORS misconfiguration', 'Privilege escalation', 'Missing authentication checks'],
                'rules' => [
                    ['pattern' => 'idor', 'matchType' => 'keyword', 'toolType' => 'semgrep', 'confidence' => 90],
                    ['pattern' => 'cors', 'matchType' => 'keyword', 'toolType' => 'semgrep', 'confidence' => 85],
                    ['pattern' => 'privilege', 'matchType' => 'keyword', 'toolType' => 'semgrep', 'confidence' => 80],
                    ['pattern' => '/access.*control|permission/i', 'matchType' => 'regex', 'toolType' => 'semgrep', 'confidence' => 75],
                ],
            ],
            
            // ===== A02: Security Misconfiguration =====
            /**
             * A02: Security Misconfiguration
             * 
             * Configurations de sécurité défaillantes:
             * - Identifiants par défaut
             * - En-têtes de sécurité manquants
             * - Mode debug activé en production
             * - Fichiers de configuration exposés
             * 
             * Règles:
             * - Headers: Vérification des en-têtes de sécurité
             * - Debug: Mode debug détecté
             * - Default credentials: Identifiants par défaut
             * - Configuration: Fichiers de config exposés
             * - Severity: Vulnérabilités hautes/critiques
             */
            [
                'code' => 'A02',
                'name' => 'Security Misconfiguration',
                'description' => 'Weak security configuration including default credentials, unpatched systems, debug mode enabled',
                'examples' => ['Missing security headers', 'Debug mode enabled', 'Default credentials', 'Exposed configuration files'],
                'rules' => [
                    ['pattern' => 'header', 'matchType' => 'keyword', 'toolType' => 'semgrep', 'confidence' => 80],
                    ['pattern' => 'debug', 'matchType' => 'keyword', 'toolType' => 'configuration-scanner', 'confidence' => 95],
                    ['pattern' => 'default.*password|admin:admin', 'matchType' => 'regex', 'toolType' => 'semgrep', 'confidence' => 90],
                    ['pattern' => 'config', 'matchType' => 'keyword', 'toolType' => 'configuration-scanner', 'confidence' => 70],
                    ['pattern' => 'high', 'matchType' => 'severity', 'toolType' => 'semgrep', 'confidence' => 75],
                ],
            ],
            
            // ===== A03: Software Supply Chain Failures =====
            /**
             * A03: Software Supply Chain Failures
             * 
             * Vulnérabilités dans la chaîne logistique logicielle:
             * - Dépendances avec CVE connu
             * - Packages obsolètes
             * - Packages malveillants
             * - Versions de bibliothèque non sécurisées
             * 
             * Outils de détection:
             * - npm-audit: Pour les packages JavaScript
             * - composer-audit: Pour les packages PHP
             * 
             * Règles:
             * - Vulnerability keywords: CVE, vulnerability, etc.
             * - Severity: Les niveaux critiques/high
             */
            [
                'code' => 'A03',
                'name' => 'Software Supply Chain Failures',
                'description' => 'Vulnerable dependencies, compromised packages, insecure updates',
                'examples' => ['Known CVE in dependency', 'Outdated package', 'Malicious package', 'Insecure library versions'],
                'rules' => [
                    ['pattern' => 'vulnerability|cve|vulnerable|known cve|security issue', 'matchType' => 'keyword', 'toolType' => 'npm-audit', 'confidence' => 95],
                    ['pattern' => 'vulnerability|cve|vulnerable|known cve|security issue', 'matchType' => 'keyword', 'toolType' => 'composer-audit', 'confidence' => 95],
                    ['pattern' => 'outdated|update available', 'matchType' => 'keyword', 'toolType' => 'npm-audit', 'confidence' => 80],
                    ['pattern' => 'critical|high', 'matchType' => 'severity', 'toolType' => 'npm-audit', 'confidence' => 90],
                    ['pattern' => 'critical|high', 'matchType' => 'severity', 'toolType' => 'composer-audit', 'confidence' => 90],
                ],
            ],
            
            // ===== A04: Cryptographic Failures =====
            /**
             * A04: Cryptographic Failures
             * 
             * Défaillances cryptographiques:
             * - Passwords stockés en plaintext
             * - Hachage faible (MD5, SHA1)
             * - Chiffrement manquant
             * - Secrets codés en dur
             * 
             * Outils de détection:
             * - Semgrep: Patterns de code faible
             * - git-secrets: Détection de secrets
             * 
             * Règles:
             * - Keywords: password, secret, token, API keys
             * - Regex: plaintext, weak hashing (MD5, SHA1)
             * - Severity: Les trouvailles de git-secrets sont critiques
             */
            [
                'code' => 'A04',
                'name' => 'Cryptographic Failures',
                'description' => 'Weak encryption, plaintext passwords, obsolete cryptographic algorithms',
                'examples' => ['Passwords stored in plaintext', 'Weak hashing (MD5, SHA1)', 'Missing encryption', 'Hardcoded secrets'],
                'rules' => [
                    ['pattern' => 'password|secret|key|token|api.*key', 'matchType' => 'keyword', 'toolType' => 'git-secrets', 'confidence' => 95],
                    ['pattern' => 'plaintext|plain.*text|no.*encrypt|md5|sha1', 'matchType' => 'regex', 'toolType' => 'semgrep', 'confidence' => 95],
                    ['pattern' => 'md5.*hash|sha1.*hash|weak.*hash', 'matchType' => 'regex', 'toolType' => 'semgrep', 'confidence' => 90],
                    ['pattern' => 'critical', 'matchType' => 'severity', 'toolType' => 'git-secrets', 'confidence' => 95],
                ],
            ],
            
            // ===== A05: Injection =====
            /**
             * A05: Injection
             * 
             * Attaques par injection de code:
             * - SQL injection: Injection dans des requêtes SQL
             * - XSS: Cross-site scripting
             * - Command injection: Injection dans des commandes système
             * - LDAP injection
             * - OS command injection
             * 
             * Règles:
             * - SQL Injection: Patterns regex pour SQL
             * - XSS: Patterns pour cross-site scripting
             * - Command injection: Patterns pour injection de commandes
             * - Keywords: "injection"
             * - Severity: Les niveaux high/critical
             */
            [
                'code' => 'A05',
                'name' => 'Injection',
                'description' => 'SQL injection, XSS, command injection through unvalidated input',
                'examples' => ['SQL injection', 'Cross-site scripting (XSS)', 'Command injection', 'LDAP injection', 'OS command injection'],
                'rules' => [
                    ['pattern' => 'sql.*injection|sql.*inject', 'matchType' => 'regex', 'toolType' => 'semgrep', 'confidence' => 95],
                    ['pattern' => 'xss|cross.*site.*script|reflected.*xss', 'matchType' => 'regex', 'toolType' => 'semgrep', 'confidence' => 95],
                    ['pattern' => 'command.*injection|os.*command|exec.*untrusted', 'matchType' => 'regex', 'toolType' => 'semgrep', 'confidence' => 90],
                    ['pattern' => 'injection', 'matchType' => 'keyword', 'toolType' => 'semgrep', 'confidence' => 85],
                    ['pattern' => 'critical|high', 'matchType' => 'severity', 'toolType' => 'semgrep', 'confidence' => 85],
                ],
            ],
        ];
    }
}

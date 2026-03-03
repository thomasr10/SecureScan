<?php

namespace App\Service;

use App\Entity\Vulnerability;
use Doctrine\ORM\EntityManagerInterface;

/**
 * SecurityToolParserService
 * 
 * Convertit les sorties de différents outils de sécurité en entités Vulnerability.
 * 
 * Outils supportés:
 * - Semgrep: SAST (analyse statique de code)
 * - npm-audit: Vérification des vulnérabilités dans les dépendances JavaScript
 * - composer-audit: Vérification des vulnérabilités dans les dépendances PHP
 * - git-secrets: Détection de secrets enregistrés dans git
 * - TruffleHog: Recherche de secrets dans l'historique git
 * 
 * Flux:
 * 1. Parse la sortie JSON/plaintext de l'outil
 * 2. Crée une entité Vulnerability pour chaque découverte
 * 3. Mappe automatiquement à une catégorie OWASP via OwaspMappingService
 * 4. Persiste en base de données
 * 
 * Exemple d'utilisation:
 * $vulnerabilities = $parser->parseSemgrepOutput($jsonOutput);
 * // Result: Array de Vulnerability entities avec mappage OWASP
 */
class SecurityToolParserService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OwaspMappingService $mappingService,
    ) {}

    /**
     * Parser la sortie JSON de Semgrep
     * 
     * Semgrep est un outil SAST (Static Application Security Testing) qui analyse le code source
     * pour détecter des patterns de vulnérabilités. Ses résultats sont au format JSON.
     * 
     * Format attendu:
     * {
     *   "results": [
     *     {
     *       "check_id": "php.lang.security.hardcoded-password",
     *       "extra": { "message": "Hardcoded password detected" },
     *       "path": "src/config/db.php",
     *       "start": { "line": 42 }
     *     }
     *   ]
     * }
     * 
     * Processus:
     * 1. Parse le JSON
     * 2. Crée une Vulnerability pour chaque résultat
     * 3. Mappe à OWASP (ex: hardcoded-password → A02)
     * 4. Persiste en base
     * 
     * @param string $jsonOutput Sortie JSON de Semgrep
     * @return array Tableau de Vulnerability mappées et persistées
     */
    public function parseSemgrepOutput(string $jsonOutput): array
    {
        $data = json_decode($jsonOutput, true);
        $vulnerabilities = [];

        foreach ($data['results'] ?? [] as $result) {
            $vuln = new Vulnerability();
            $vuln->setTitle($result['check_id'] ?? 'Unknown');
            $vuln->setDescription($result['extra']['message'] ?? 'Semgrep finding');
            $vuln->setSeverity($this->mapSemgrepSeverity($result));
            $vuln->setToolName('semgrep');
            $vuln->setFilePath($result['path'] ?? null);
            $vuln->setLineNumber($result['start']['line'] ?? null);
            $vuln->setRawData($result);

            // Mappe automatiquement à une catégorie OWASP
            $this->mappingService->mapVulnerability($vuln);
            $this->entityManager->persist($vuln);
            $vulnerabilities[] = $vuln;
        }

        // Flush une fois tous les éléments persisted
        $this->entityManager->flush();
        return $vulnerabilities;
    }

    /**
     * Parser la sortie JSON de npm audit
     * 
     * npm-audit détecte les vulnérabilités connues dans les dépendances JavaScript.
     * Utilisé pour analyser les packages npm.
     * 
     * Format attendu:
     * {
     *   "vulnerabilities": {
     *     "express": {
     *       "via": [
     *         {
     *           "title": "Express XSS vulnerability",
     *           "severity": "high",
     *           "url": "https://npmjs.com/..."
     *         }
     *       ]
     *     }
     *   }
     * }
     * 
     * @param string $jsonOutput Sortie JSON de npm audit
     * @return array Tableau de Vulnerability mappées et persistées
     */
    public function parseNpmAuditOutput(string $jsonOutput): array
    {
        $data = json_decode($jsonOutput, true);
        $vulnerabilities = [];

        foreach ($data['vulnerabilities'] ?? [] as $packageName => $vulnData) {
            foreach ($vulnData['via'] ?? [] as $via) {
                if (is_array($via)) {
                    $vuln = new Vulnerability();
                    $vuln->setTitle($via['title'] ?? "Vulnerability in {$packageName}");
                    $vuln->setDescription($via['url'] ?? $via['description'] ?? 'npm audit finding');
                    $vuln->setSeverity($via['severity'] ?? 'medium');
                    $vuln->setToolName('npm-audit');
                    $vuln->setRawData($via);

                    // Mappe à OWASP (ex: XSS → A03, Injection → A05)
                    $this->mappingService->mapVulnerability($vuln);
                    $this->entityManager->persist($vuln);
                    $vulnerabilities[] = $vuln;
                }
            }
        }

        $this->entityManager->flush();
        return $vulnerabilities;
    }

    /**
     * Parser la sortie JSON de composer audit
     * 
     * composer-audit détecte les vulnérabilités connues dans les dépendances PHP.
     * Utilisé pour analyser les packages Composer.
     * 
     * Format attendu: Array des vulnérabilités trouvées
     * [
     *   {
     *     "package": "vendor/package",
     *     "title": "Security issue",
     *     "fromVersion": "1.0.0",
     *     "toVersion": "1.0.5",
     *     "cve": "CVE-2024-1234"
     *   }
     * ]
     * 
     * @param string $jsonOutput Sortie JSON de composer audit
     * @return array Tableau de Vulnerability mappées et persistées
     */
    public function parseComposerAuditOutput(string $jsonOutput): array
    {
        $data = json_decode($jsonOutput, true);
        $vulnerabilities = [];

        foreach ($data ?? [] as $audit) {
            $vuln = new Vulnerability();
            $vuln->setTitle($audit['title'] ?? 'Composer dependency vulnerability');
            $vuln->setDescription(
                "Package: {$audit['package']} " .
                "Affected versions: {$audit['fromVersion']}-{$audit['toVersion']} " .
                ($audit['cve'] ?? '')
            );
            // Les vulnérabilités de dépendances sont généralement graves
            $vuln->setSeverity('high');
            $vuln->setToolName('composer-audit');
            $vuln->setRawData($audit);

            // Mappe à OWASP (ex: Supply Chain → A03)
            $this->mappingService->mapVulnerability($vuln);
            $this->entityManager->persist($vuln);
            $vulnerabilities[] = $vuln;
        }

        $this->entityManager->flush();
        return $vulnerabilities;
    }

    /**
     * Parser la sortie plaintext de git-secrets
     * 
     * git-secrets détecte les secrets enregistrés dans l'historique git.
     * Cherche des patterns de mots de passe, clés API, tokens, etc.
     * 
     * Format attendu: Une ligne par découverte
     * path/to/file.php:line_number:secret_pattern
     * src/config/.env:15:AWS_SECRET_ACCESS_KEY=xyz123
     * 
     * Processus:
     * 1. Parse chaque ligne au format chemin:ligne:pattern
     * 2. Crée une Vulnerability critical (secret = risque très grave)
     * 3. Mappe à A02 (Security Misconfiguration)
     * 
     * @param string $plainOutput Sortie plaintext de git-secrets
     * @return array Tableau de Vulnerability mappées et persistées
     */
    public function parseGitSecretsOutput(string $plainOutput): array
    {
        $vulnerabilities = [];
        $lines = explode("\n", trim($plainOutput));

        foreach ($lines as $line) {
            if (empty($line)) {
                continue;
            }

            // Parse le format: path/to/file.php:line_number:secret_pattern
            if (preg_match('/^(.+?):(\d+):(.+)$/', $line, $matches)) {
                $vuln = new Vulnerability();
                $vuln->setTitle('Hardcoded Secret Detected');
                $vuln->setDescription("Potential secret found: {$matches[3]}");
                // Les secrets sont une menace critique
                $vuln->setSeverity('critical');
                $vuln->setToolName('git-secrets');
                $vuln->setFilePath($matches[1]);
                $vuln->setLineNumber((int)$matches[2]);
                $vuln->setRawData(['line' => $line]);

                // Mappe à A02 (Security Misconfiguration)
                $this->mappingService->mapVulnerability($vuln);
                $this->entityManager->persist($vuln);
                $vulnerabilities[] = $vuln;
            }
        }

        $this->entityManager->flush();
        return $vulnerabilities;
    }

    /**
     * Parser la sortie JSON de TruffleHog
     * 
     * TruffleHog est un outil de détection de secrets dans l'historique git.
     * Plus avancé que git-secrets, utilise des patterns regex et machine learning.
     * 
     * Format attendu: JSON array de findings
     * [
     *   {
     *     "type": "AWS Access Key",
     *     "sourceMetadata": {
     *       "data": {
     *         "file": "src/.env",
     *         "line": 5
     *       }
     *     }
     *   }
     * ]
     * 
     * @param string $jsonOutput Sortie JSON de TruffleHog
     * @return array Tableau de Vulnerability mappées et persistées
     */
    public function parseTrufflehogOutput(string $jsonOutput): array
    {
        $data = json_decode($jsonOutput, true);
        $vulnerabilities = [];

        foreach ($data ?? [] as $finding) {
            $type = $finding['type'] ?? 'Unknown';
            $file = $finding['sourceMetadata']['data']['file'] ?? 'unknown file';
            $line = $finding['sourceMetadata']['data']['line'] ?? null;
            
            $vuln = new Vulnerability();
            $vuln->setTitle("Secret Detected: {$type}");
            $vuln->setDescription(
                "Secret type: {$type} " .
                "in {$file}"
            );
            // Secrets = risque critique
            $vuln->setSeverity('critical');
            $vuln->setToolName('trufflehog');
            $vuln->setFilePath($file);
            $vuln->setLineNumber($line);
            $vuln->setRawData($finding);

            // Mappe à A02 (Security Misconfiguration)
            $this->mappingService->mapVulnerability($vuln);
            $this->entityManager->persist($vuln);
            $vulnerabilities[] = $vuln;
        }

        $this->entityManager->flush();
        return $vulnerabilities;
    }

    /**
     * Mappe la sévérité Semgrep à nos niveaux standards
     * 
     * Semgrep utilise: CRITICAL, ERROR, WARNING, NOTE, INFO
     * Nous utilisons: critical, high, medium, low
     * 
     * Mapping:
     * - CRITICAL → critical
     * - ERROR → high
     * - WARNING → medium
     * - NOTE, INFO → low
     * 
     * @param array $result Le résultat Semgrep
     * @return string La sévérité normalisée
     */
    private function mapSemgrepSeverity(array $result): string
    {
        $severity = $result['extra']['severity'] ?? 'INFO';
        return match (strtoupper($severity)) {
            'CRITICAL' => 'critical',
            'ERROR' => 'high',
            'WARNING' => 'medium',
            'NOTE', 'INFO' => 'low',
            default => 'medium',
        };
    }
}

<?php

namespace App\Controller;

use App\Service\ComposerAuditService;
use App\Service\LanguageDetector;
use App\Service\NpmAuditService;
use App\Service\PhpstanAnalyzerService;
use App\Service\ProjectService;
use App\Service\SemgrepResultNormalizer;
use App\Service\ReportService;
use App\Service\RemoveDirectoryService;
use App\Service\SemgrepScanService;
use App\Service\SecurityToolParserService;
use App\Service\OwaspMappingService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use ZipArchive;

final class HomeController extends AbstractController
{
    public function __construct(
        private LanguageDetector $languageDetector,
        private ProjectService $projectService,
        private ReportService $reportService,
        private SecurityToolParserService $securityToolParser,
        private OwaspMappingService $owaspMapper
    ) {}

    // Landing accessible à tous (comme ta maquette)
    #[IsGranted("ROLE_USER")]
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function showHome(): Response
    {
        return $this->render('home/index.html.twig');
    }

    // Page "analyse en cours"
    #[Route('/scanning', name: 'app_scanning', methods: ['GET'])]
    public function scanning(): Response
    {
        return $this->render('home/scanning.html.twig');
    }


    // Dashboard (placeholder pour l’instant)
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        $analysisArray = $request->getSession()->get('analysisArray');

        $score = 80;
        $status = 'done';

        return $this->render('home/dashboard.html.twig', [
            'analysisArray' => $analysisArray,
            'score' => $score,
            'status' => $status,
        ]);
    }

    // Upload protégé : faut être connecté
    #[IsGranted("ROLE_USER")]
    #[Route('/upload', name: 'app_upload', methods: ['POST'])]
    public function upload(
        Request $request,
        LoggerInterface $logger,
        PhpstanAnalyzerService $phpAnalyzerService,
        ComposerAuditService $composerAuditService,
        NpmAuditService $npmAuditService,
        SemgrepScanService $semgrepScanService,
        SemgrepResultNormalizer $semgrepNormalizer,
        RemoveDirectoryService $removeDirectoryService
    ): Response {
        $url = $request->request->get('project_url');
        $zip = $request->files->get('project_zip');
        $projectsDir = $this->getParameter('kernel.project_dir') . '/projects/';
        
        // Créer le répertoire s'il n'existe pas
        if (!is_dir($projectsDir)) {
            mkdir($projectsDir, 0777, true);
        }
        
        if (is_dir($projectsDir)) {
            $removeDirectoryService->removeDirectory($projectsDir);
            mkdir($projectsDir, 0777, true);
        }

        // =============================
        // SI URL GIT
        // =============================
        if ($url && !$zip) {
            $url = trim($url);
            
            // Extraire l'URL si l'utilisateur a collé "git clone https://..."
            if (str_starts_with($url, 'git clone ')) {
                $url = trim(substr($url, strlen('git clone ')));
            }

            if (!str_ends_with($url, '.git')) {
                return $this->redirectToRoute('app_home');
            }

            $type = 'git';
            $name = basename($url, '.git');

            try {
                $projectId = 'project_' . Uuid::v4();
                $projectsDir = $this->getParameter('kernel.project_dir') . '/projects/' . $projectId;

                $process = new Process(['git', 'clone', $url, $projectsDir]);
                $process->setTimeout(300); // 5 minutes pour cloner les gros repos
                $process->run();

                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
                
                // Vérifier que le répertoire a bien été créé
                if (!is_dir($projectsDir)) {
                    throw new \Exception("Git clone failed: directory not created at $projectsDir");
                }


                // Création + insertion du projet en base
                $project = $this->projectService->createProject($projectId, $name, $type, $url);

                $languageInfo = $this->languageDetector->detect($projectsDir);
                $logger->info('Langage détecté', $languageInfo);

                $request->getSession()->set('languageInfo', $languageInfo);
                $request->getSession()->set('projectsDir', $projectsDir);

                // ON RECUPERE LES FICHIERS D'ANALYSE
                $phpStan = $phpAnalyzerService->analyze($projectsDir, $projectId);
                
                $composerAudit = [];
                try {
                    $composerAudit = $composerAuditService->audit($projectsDir, $projectId);
                } catch (\Exception $e) {
                    $logger->warning('Composer audit skipped (Git): ' . $e->getMessage());
                }
                
                $npmAudit = [];
                try {
                    $npmAudit = $npmAuditService->audit($projectsDir, $projectId);
                } catch (\Exception $e) {
                    $logger->warning('npm audit skipped (Git): ' . $e->getMessage());
                }
                
                try {
                    $semgrepScanService->scan($projectsDir, $projectId);
                } catch (\Exception $e) {
                    $logger->warning('Semgrep skipped (Git): ' . $e->getMessage());
                }
                
                // PARSING ET MAPPAGE OWASP AUTOMATIQUE
                $vulnerabilities = [];
                try {
                    if (!empty($phpStan)) {
                        $vulnsPHPStan = $this->securityToolParser->parsePhpstanOutput($phpStan);
                        $vulnerabilities = array_merge($vulnerabilities, $vulnsPHPStan);
                        $logger->info('PHPStan: ' . count($vulnsPHPStan) . ' vulnérabilités détectées');
                    }
                    
                    if (!empty($composerAudit)) {
                        $vulnsComposer = $this->securityToolParser->parseComposerAuditOutput(json_encode($composerAudit));
                        $vulnerabilities = array_merge($vulnerabilities, $vulnsComposer);
                        $logger->info('Composer Audit: ' . count($vulnsComposer) . ' vulnérabilités détectées');
                    }
                    
                    if (!empty($npmAudit)) {
                        $vulnsNpm = $this->securityToolParser->parseNpmAuditOutput(json_encode($npmAudit));
                        $vulnerabilities = array_merge($vulnerabilities, $vulnsNpm);
                        $logger->info('npm Audit: ' . count($vulnsNpm) . ' vulnérabilités détectées');
                    }
                    
                    $logger->info('Total vulnérabilités détectées et mappées: ' . count($vulnerabilities));
                } catch (\Exception $e) {
                    $logger->error('Erreur parsing vulnérabilités: ' . $e->getMessage());
                }

                // SCORE ET STATUS
                $score = $this->calculateSecurityScore(count($vulnerabilities));
                $status = 'done';
                
                // INSERTION RAPPORT EN BDD
                $analysisArray = array_map(function($vuln) {
                    return [
                        'title' => $vuln->getTitle(),
                        'severity' => $vuln->getSeverity(),
                        'tool' => $vuln->getToolName(),
                        'file' => $vuln->getFilePath(),
                        'line' => $vuln->getLineNumber(),
                        'owasp' => $vuln->getOwaspCategory()?->getCode() ?? 'Unknown',
                        'confidence' => $vuln->getConfidenceScore(),
                    ];
                }, $vulnerabilities);
                
                $this->reportService->insertReport($languageInfo['detected'], $score, $status, $analysisArray, $project);

                $this->addFlash('success', '✅ Analyse terminée! ' . count($vulnerabilities) . ' vulnérabilité(s) détectée(s).');
                return $this->redirectToRoute('app_scanning');
            } catch (\Throwable $e) {
                $logger->error('Erreur upload GIT: ' . $e->getMessage());
                $this->addFlash('error', '❌ Erreur lors de l\'analyse: ' . $e->getMessage());
                return $this->redirectToRoute('app_home');
            }
        }

        // =============================
        // SI ZIP
        // =============================
        if ($zip && !$url) {
            $zipProject = new ZipArchive();

            $type = 'zip';
            $name = pathinfo($zip->getClientOriginalName(), PATHINFO_FILENAME);

            try {
                $projectId = 'project_' . Uuid::v4();
                $projectsDir = $this->getParameter('kernel.project_dir') . '/projects/' . $projectId;

                if ($zipProject->open($zip->getPathname()) !== true) {
                    $logger->error('Erreur ouverture ZIP');
                    return $this->redirectToRoute('app_home');
                }

                $zipProject->extractTo($projectsDir);
                $zipProject->close();
                
                // Vérifier que l'extraction a réussi
                if (!is_dir($projectsDir) || count(glob("$projectsDir/*")) === 0) {
                    throw new \Exception("ZIP extraction failed: directory is empty or doesn't exist");
                }

                // Création + insertion du projet en base
                $project = $this->projectService->createProject($projectId, $name, $type, hash_file('sha256', $zip->getPathname()));

                $languageInfo = $this->languageDetector->detect($projectsDir);
                $logger->info('Langage détecté', $languageInfo);

                $request->getSession()->set('languageInfo', $languageInfo);
                $request->getSession()->set('projectsDir', $projectsDir);

                $phpStan = $phpAnalyzerService->analyze($projectsDir, $projectId);
                
                $composerAudit = [];
                try {
                    $composerAudit = $composerAuditService->audit($projectsDir, $projectId);
                } catch (\Exception $e) {
                    $logger->warning('Composer audit skipped (ZIP): ' . $e->getMessage());
                }
                
                $npmAudit = [];
                try {
                    $npmAudit = $npmAuditService->audit($projectsDir, $projectId);
                } catch (\Exception $e) {
                    $logger->warning('npm audit skipped (ZIP): ' . $e->getMessage());
                }
                
                try {
                    $semgrepScanService->scan($projectsDir, $projectId);
                } catch (\Exception $e) {
                    $logger->warning('Semgrep skipped (ZIP): ' . $e->getMessage());
                }
                
                // PARSING ET MAPPAGE OWASP AUTOMATIQUE
                $vulnerabilities = [];
                try {
                    if (!empty($phpStan)) {
                        $vulnsPHPStan = $this->securityToolParser->parsePhpstanOutput($phpStan);
                        $vulnerabilities = array_merge($vulnerabilities, $vulnsPHPStan);
                        $logger->info('PHPStan: ' . count($vulnsPHPStan) . ' vulnérabilités détectées');
                    }
                    
                    if (!empty($composerAudit)) {
                        $vulnsComposer = $this->securityToolParser->parseComposerAuditOutput(json_encode($composerAudit));
                        $vulnerabilities = array_merge($vulnerabilities, $vulnsComposer);
                        $logger->info('Composer Audit: ' . count($vulnsComposer) . ' vulnérabilités détectées');
                    }
                    
                    if (!empty($npmAudit)) {
                        $vulnsNpm = $this->securityToolParser->parseNpmAuditOutput(json_encode($npmAudit));
                        $vulnerabilities = array_merge($vulnerabilities, $vulnsNpm);
                        $logger->info('npm Audit: ' . count($vulnsNpm) . ' vulnérabilités détectées');
                    }
                    
                    $logger->info('Total vulnérabilités détectées et mappées: ' . count($vulnerabilities));
                } catch (\Exception $e) {
                    $logger->error('Erreur parsing vulnérabilités: ' . $e->getMessage());
                }

                // SCORE ET STATUS
                $score = $this->calculateSecurityScore(count($vulnerabilities));
                $status = 'done';
                
                // INSERTION RAPPORT EN BDD
                $analysisArray = array_map(function($vuln) {
                    return [
                        'title' => $vuln->getTitle(),
                        'severity' => $vuln->getSeverity(),
                        'tool' => $vuln->getToolName(),
                        'file' => $vuln->getFilePath(),
                        'line' => $vuln->getLineNumber(),
                        'owasp' => $vuln->getOwaspCategory()?->getCode() ?? 'Unknown',
                        'confidence' => $vuln->getConfidenceScore(),
                    ];
                }, $vulnerabilities);

                $this->reportService->insertReport($languageInfo['detected'], $score, $status, $analysisArray, $project);

                $this->addFlash('success', '✅ Analyse terminée! ' . count($vulnerabilities) . ' vulnérabilité(s) détectée(s).');
                return $this->redirectToRoute('app_scanning');
            } catch (\Throwable $e) {
                $logger->error('Erreur upload ZIP: ' . $e->getMessage());
                $this->addFlash('error', '❌ Erreur lors de l\'analyse: ' . $e->getMessage());
                return $this->redirectToRoute('app_home');
            }
        }
        //  IMPORTANT : après upload → page scanning
        return $this->redirectToRoute('app_scanning');
    }

    #[Route('/debug/semgrep', name: 'debug_semgrep', methods: ['GET'])]
    public function debugSemgrep(
        Request $request,
        SemgrepScanService $semgrepScanService,
        SemgrepResultNormalizer $semgrepNormalizer
    ): Response {
        $projectsDir = $request->getSession()->get('projectsDir');
        if (!$projectsDir) {
            return new Response("Pas de projectsDir en session. Fais d'abord un upload (git/zip).", 400);
        }

        $projectId = 'debug_' . Uuid::v4();

        $raw = $semgrepScanService->scan($projectsDir, $projectId);
        $normalized = $semgrepNormalizer->normalize($raw, $projectId);

        return $this->json([
            'raw_has_results' => isset($raw['results']) ? count($raw['results']) : 0,
            'normalized_count' => count($normalized),
            'normalized_preview' => array_slice($normalized, 0, 5),
        ]);
    }

    /**
     * Calcule un score de sécurité basé sur le nombre de vulnérabilités
     * 
     * Score: 100 - (nombre_vulnérabilités * 5)
     * Minimum: 0, Maximum: 100
     */
    private function calculateSecurityScore(int $vulnerabilityCount): int
    {
        $score = 100 - ($vulnerabilityCount * 5);
        return max(0, min(100, $score));
    }
}
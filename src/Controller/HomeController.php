<?php

namespace App\Controller;

use App\Service\ComposerAuditService;
use App\Service\LanguageDetector;
use App\Service\NpmAuditService;
use App\Service\PhpstanAnalyzerService;
use App\Service\ProjectService;
use App\Service\SemgrepResultNormalizer;
use App\Service\SemgrepScanService;
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
        private ProjectService $projectService
    ) {}

    #[IsGranted("ROLE_USER")]
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function showHome(): Response
    {
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
        ]);
    }

    #[Route('/upload', name: 'app_upload', methods: ['POST'])]
    public function upload(
        Request $request,
        LoggerInterface $logger,
        PhpstanAnalyzerService $phpAnalyzerService,
        ComposerAuditService $composerAuditService,
        NpmAuditService $npmAuditService,
        SemgrepScanService $semgrepScanService,
        SemgrepResultNormalizer $semgrepNormalizer
    ): Response {
        $url = $request->request->get('project_url');
        $zip = $request->files->get('project_zip');

        // =============================
        // SI URL GIT
        // =============================
        if ($url && !$zip) {
            $url = trim($url);

            if (!str_ends_with($url, '.git')) {
                return $this->redirectToRoute('app_home');
            }

            $type = 'git';
            $name = basename($url, '.git');

            try {
                $projectId = 'project_' . Uuid::v4();
                $projectsDir = $this->getParameter('kernel.project_dir') . '/projects/' . $projectId;

                $process = new Process(['git', 'clone', $url, $projectsDir]);
                $process->run();

                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }

                // Création + insertion du projet en base
                $this->projectService->createProject($projectId, $name, $type, $url);

                // Détection du langage
                $languageInfo = $this->languageDetector->detect($projectsDir);
                $logger->info('Langage détecté', $languageInfo);

                // Stockage temporaire en session
                $request->getSession()->set('languageInfo', $languageInfo);
                $request->getSession()->set('projectsDir', $projectsDir);

                // Analyses existantes
                $phpAnalyzerService->analyze($projectsDir, $projectId);
                $composerAuditService->audit($projectsDir, $projectId);
                $npmAuditService->audit($projectsDir, $projectId);

                //  Semgrep: stocker + normaliser
                $semgrepRaw = $semgrepScanService->scan($projectsDir, $projectId);
                $semgrepNormalized = $semgrepNormalizer->normalize($semgrepRaw, $projectId);

                // Pour debug rapide (sans UI): on stocke en session
                $request->getSession()->set('semgrepNormalized', $semgrepNormalized);

            } catch (\Throwable $e) {
                $logger->error('Erreur upload Git: ' . $e->getMessage());
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

                // Création + insertion du projet en base
                $this->projectService->createProject(
                    $projectId,
                    $name,
                    $type,
                    hash_file('sha256', $zip->getPathname())
                );

                // Détection du langage
                $languageInfo = $this->languageDetector->detect($projectsDir);
                $logger->info('Langage détecté', $languageInfo);

                // Stockage temporaire en session
                $request->getSession()->set('languageInfo', $languageInfo);
                $request->getSession()->set('projectsDir', $projectsDir);

                // Analyses existantes
                $phpAnalyzerService->analyze($projectsDir, $projectId);
                $composerAuditService->audit($projectsDir, $projectId);
                $npmAuditService->audit($projectsDir, $projectId);

                // Semgrep: stocker + normaliser
                $semgrepRaw = $semgrepScanService->scan($projectsDir, $projectId);
                $semgrepNormalized = $semgrepNormalizer->normalize($semgrepRaw, $projectId);

                // Pour debug rapide (sans UI): on stocke en session
                $request->getSession()->set('semgrepNormalized', $semgrepNormalized);

            } catch (\Throwable $e) {
                $logger->error('Erreur upload ZIP: ' . $e->getMessage());
                return $this->redirectToRoute('app_home');
            }
        }

        return $this->redirectToRoute('app_home');
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
}
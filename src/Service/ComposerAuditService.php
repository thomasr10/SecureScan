<?php
namespace App\Service;
use Symfony\Component\Process\Process;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

class ComposerAuditService
{
    public function __construct(private ParameterBagInterface $params, private RemoveDirectoryService $removeDirectoryService, private LoggerInterface $logger)
    {
    }

    public function audit(string $projectDir, string $projectId): array
    {
        // Vérifier que le répertoire existe
        if (!is_dir($projectDir)) {
            throw new \Exception("Project directory does not exist: $projectDir");
        }
        
        // CHECK SI COMPOSER.JSON
        if (!file_exists($projectDir . '/composer.json')) {
            return [];
        }
        // vérifie que le dossier des reports est bien créer
        $reportsDir = $this->params->get("kernel.project_dir") . "/reports/composer_audit_reports";
        if (is_dir($reportsDir)) {
            $this->removeDirectoryService->removeDirectory($reportsDir);
        }
        if (!is_dir($reportsDir)) {
            mkdir($reportsDir, 0777, true);
        }

        // lance un composer install pour pouvoir scanner les dependances
        $process = new Process(["composer", "-d", $projectDir, "install", "--no-interaction", "--no-scripts"]);
        $process->setTimeout(300); // 5 minutes pour installer les dépendances
        $process->run();

        // Ne pas faire de throw si install échoue - on essaye l'audit même sans install
        if (!$process->isSuccessful()) {
            $this->logger->warning('Composer install failed but continuing: ' . $process->getErrorOutput());
        }

        // scan les dépendances
        $process = new Process(["composer", "-d", $projectDir, "audit", "--format=json"]);
        $process->setTimeout(300); // 5 minutes pour auditer
        $process->run();
        $output = $process->getOutput();

        if (!$process->isSuccessful()) {
            // Composer audit peut échouer pour diverses raisons, log mais ne throw pas
            $this->logger->warning('Composer audit failed: ' . $process->getErrorOutput());
        }

        // enregistre dans un json le resultat
        file_put_contents($reportsDir . "/composer_audit_" . $projectId . ".json", $output);
        return json_decode($output, true) ?? [];
    }
}
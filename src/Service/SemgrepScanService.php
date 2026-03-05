<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Process;
use Psr\Log\LoggerInterface;

class SemgrepScanService{
    public function __construct(
        private ParameterBagInterface $params, 
        private RemoveDirectoryService $removeDirectoryService,
        private LoggerInterface $logger
    ) {}

    public function scan(string $projectDir, string $projectId){
        // Vérifier que le répertoire existe
        if (!is_dir($projectDir)) {
            throw new \Exception("Project directory does not exist: $projectDir");
        }
        
        $reportsDir = $this->params->get("kernel.project_dir") . "/reports/semgrep_scan_reports";
        if (is_dir($reportsDir)) {
            $this->removeDirectoryService->removeDirectory($reportsDir);
        }

        if (!is_dir($reportsDir)) {
            mkdir($reportsDir, 0777, true);
        }

        // IMPORTANT: on met le scan sur le dossier du projet
        $process = new Process(['semgrep', '--config=auto', '--json', $projectDir]);
        $process->setEnv(['PYTHONUTF8' => '1']);
        $process->run();

        $rawJson = $process->getOutput();

        // On garde le report sur disque (comme avant)
        file_put_contents($reportsDir . '/semgrep_scan_' . $projectId . '.json', $rawJson);
        
        $this->logger->info('Semgrep scan completed successfully');

        // Et maintenant on retourne aussi le résultat (pour le stocker dans une variable)
        $decoded = json_decode($rawJson, true);

        return is_array($decoded) ? $decoded : [];
    }
}
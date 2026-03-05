<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class SemgrepScanService{
    public function __construct(private ParameterBagInterface $params, private RemoveDirectoryService $removeDirectoryService)
    {
    }

    public function scan(string $projectDir, string $projectId){
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
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $rawJson = $process->getOutput();

        // On garde le report sur disque (comme avant)
        file_put_contents($reportsDir . '/semgrep_scan_' . $projectId . '.json', $rawJson);

        // Et maintenant on retourne aussi le résultat (pour le stocker dans une variable)
        $decoded = json_decode($rawJson, true);

        return is_array($decoded) ? $decoded : [];
    }
}
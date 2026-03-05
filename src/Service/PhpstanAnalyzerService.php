<?php
namespace App\Service;

use Symfony\Component\Process\Process;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PhpstanAnalyzerService
{
    public function __construct(private ParameterBagInterface $params, private RemoveDirectoryService $removeDirectoryService)
    {
    }
    public function analyze(string $repoPath, string $projectId): array
    {
        // Vérifier que le répertoire existe
        if (!is_dir($repoPath)) {
            throw new \Exception("Project directory does not exist: $repoPath");
        }
        
        $reportsDir = $this->params->get("kernel.project_dir") . "/reports/phpstan_reports";
        if (is_dir($reportsDir)) {
            $this->removeDirectoryService->removeDirectory($reportsDir);
        }
        if (!is_dir($reportsDir)) {
            mkdir($reportsDir, 0777, true);
        }
        $phpstanPath = $this->params->get("kernel.project_dir") . "/vendor/bin/phpstan";
        $process = new Process([
            $phpstanPath,
            'analyse',
            '--level=5',
            $repoPath,
            '--error-format=json',
            '--no-progress',
            '--memory-limit=512M'
        ]);
        $process->run();
        $outPut = $process->getOutput();
        file_put_contents($reportsDir . '/phpstan_' . $projectId . '.json', $outPut);

        return json_decode($outPut, true) ?? [];
    }
}
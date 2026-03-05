<?php
namespace App\Service;
use Symfony\Component\Process\Process;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

class NpmAuditService
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
        
        if (!file_exists($projectDir . '/package.json')) {
            return [];
        }
        $reportsDir = $this->params->get("kernel.project_dir") . "/reports/npm_audit_reports";
        if (is_dir($reportsDir)) {
            $this->removeDirectoryService->removeDirectory($reportsDir);
        }
        if (!is_dir($reportsDir)) {
            mkdir($reportsDir, 0777, true);
        }

        $process = new Process(['npm', 'install', '--prefix', $projectDir]);
        $process->setTimeout(300); // 5 minutes pour installer les dépendances npm
        $process->run();

        if (!$process->isSuccessful()) {
            // npm install peut échouer (workspaces, etc) mais on peut continuer avec audit
            $this->logger->warning('npm install failed but continuing: ' . $process->getErrorOutput());
        }

        $process = new Process(['npm', 'audit', '--json', '--prefix', $projectDir]);
        $process->setTimeout(300); // 5 minutes pour auditer
        $process->run();

        // if (!$process->isSuccessful()) {
        //     throw new ProcessFailedException($process);
        // }

        $output = $process->getOutput();
        file_put_contents($reportsDir . "/npm_audit_" . $projectId . ".json", $output);

        return json_decode($output, true) ?? [];
    }

}
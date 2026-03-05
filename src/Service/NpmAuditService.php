<?php
namespace App\Service;
use Symfony\Component\Process\Process;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;

class NpmAuditService
{
    public function __construct(private ParameterBagInterface $params, private RemoveDirectoryService $removeDirectoryService)
    {
    }

    public function audit(string $projectDir, string $projectId): array
    {
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
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $process = new Process(['npm', 'audit', '--json', '--prefix', $projectDir]);
        $process->run();

        // if (!$process->isSuccessful()) {
        //     throw new ProcessFailedException($process);
        // }

        $output = $process->getOutput();
        file_put_contents($reportsDir . "/npm_audit_" . $projectId . ".json", $output);

        return json_decode($output, true) ?? [];
    }

}
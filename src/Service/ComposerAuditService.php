<?php
namespace App\Service;
use Symfony\Component\Process\Process;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ComposerAuditService
{
    public function __construct(private ParameterBagInterface $params, private RemoveDirectoryService $removeDirectoryService)
    {
    }

    public function audit(string $projectDir, string $projectId): array
    {
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
        $process->run();

        // scan les dépendances
        $process = new Process(["composer", "-d", $projectDir, "audit", "--format=json"]);
        $process->run();
        $output = $process->getOutput();

        // JE COMMENTE POUR PAS SUPPR
        // if (empty($output)) {
        //     throw new ProcessFailedException($process);
        // }

        // enregistre dans un json le resultat
        file_put_contents($reportsDir . "/composer_audit_" . $projectId . ".json", $output);
        return json_decode($output, true) ?? [];
    }
}
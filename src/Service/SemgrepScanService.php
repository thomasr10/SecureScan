<?php
namespace App\Service;
use Symfony\Component\Process\Process;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SemgrepScanService{
    public function __construct(private ParameterBagInterface $params)
    {
    }

    public function scan(string $projectDir, string $projectId){
        $reportsDir = $this->params->get("kernel.project_dir") . "/reports/semgrep_scan_reports";
        if (!is_dir($reportsDir)) {
            mkdir($reportsDir, 0777, true);
        }

        $process = new Process(['semgrep', 'scan', '--config=auto', '--json', $projectDir]);
        $process->setEnv(['PYTHONUTF8' => '1']);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        file_put_contents($reportsDir .'/semgrep_scan_' . $projectId . '.json', $process->getOutput());
    }
}
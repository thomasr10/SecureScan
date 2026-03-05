<?php
namespace App\Service;
use Symfony\Component\Process\Process;


class RemoveDirectoryService
{
    public function removeDirectory(string $dir): void{
        if (!is_dir($dir)) {
            return;
        }

        $command = PHP_OS_FAMILY === 'Windows'
            ? ['cmd', '/c', 'rd', '/s', '/q', str_replace('/', '\\', $dir)]
            : ['rm', '-rf', $dir];

        $process = new Process($command);
        $process->run();

        $attempts = 0;
        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Erreur suppression : ' . $process->getErrorOutput());
        }

        while (is_dir($dir) && $attempts < 10) {
            usleep(200000);
            $attempts++;
        }
    }
}
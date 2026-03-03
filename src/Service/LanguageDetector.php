<?php

namespace App\Service;

final class LanguageDetector
{
    public function detect(string $projectPath): array
    {
        $projectPath = rtrim($projectPath, DIRECTORY_SEPARATOR);

        // Signatures fortes
        $signatures = [
            'javascript' => ['package.json', 'pnpm-lock.yaml', 'yarn.lock', 'bun.lockb'],
            'python'     => ['requirements.txt', 'pyproject.toml', 'Pipfile', 'poetry.lock'],
            'php'        => ['composer.json'],
        ];

        $scores = ['javascript' => 0, 'python' => 0, 'php' => 0];
        $evidence = [];

        foreach ($signatures as $lang => $files) {
            foreach ($files as $file) {
                if (is_file($projectPath . DIRECTORY_SEPARATOR . $file)) {
                    $scores[$lang] += 50;
                    $evidence[] = "$lang: fichier détecté ($file)";
                }
            }
        }

        // Fallback extensions (on ignore vendor/node_modules/.git)
        $extMap = [
            'javascript' => ['js','ts','jsx','tsx'],
            'python'     => ['py'],
            'php'        => ['php'],
        ];

        $counts = $this->countExtensions($projectPath, 3000);

        foreach ($extMap as $lang => $exts) {
            $c = 0;
            foreach ($exts as $ext) {
                $c += $counts[$ext] ?? 0;
            }
            if ($c > 0) {
                $scores[$lang] += min(40, $c);
                $evidence[] = "$lang: extensions détectées ($c fichiers)";
            }
        }

        arsort($scores);
        $primary = array_key_first($scores);
        $best = $scores[$primary] ?? 0;

        if ($best === 0) {
            return [
                'primary' => 'inconnu',
                'detected' => [],
                'confidence' => 0,
                'evidence' => ['Aucun indice trouvé'],
            ];
        }

        return [
            'primary' => $primary,
            'detected' => array_keys(array_filter($scores, fn($s) => $s > 0)),
            'confidence' => min(100, $best),
            'evidence' => $evidence,
        ];
    }

    private function countExtensions(string $path, int $maxFiles): array
    {
        $counts = [];
        $seen = 0;

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            if ($seen >= $maxFiles) break;
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) continue;

            $p = $file->getPathname();

            if (str_contains($p, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) ||
                str_contains($p, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR) ||
                str_contains($p, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)
            ) {
                continue;
            }

            $ext = strtolower($file->getExtension());
            if ($ext !== '') {
                $counts[$ext] = ($counts[$ext] ?? 0) + 1;
                $seen++;
            }
        }

        return $counts;
    }
}
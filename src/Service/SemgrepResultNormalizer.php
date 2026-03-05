<?php

namespace App\Service;

class SemgrepResultNormalizer
{
    /**
     * Transforme la sortie Semgrep (--json) en format "vulnerability" simple.
     */
    public function normalize(array $semgrepData, string $projectId): array
    {
        $vulns = [];

        foreach (($semgrepData['results'] ?? []) as $r) {
            $severityRaw = strtolower((string)($r['extra']['severity'] ?? 'info'));

            $severity = match ($severityRaw) {
                'critical' => 'critical',
                'high', 'error' => 'high',
                'medium', 'warning' => 'medium',
                'low', 'info' => 'low',
                default => 'low',
            };

            $vulns[] = [
                'tool' => 'semgrep',
                'projectId' => $projectId,
                'ruleId' => $r['check_id'] ?? 'semgrep.unknown',
                'severity' => $severity,
                'message' => $r['extra']['message'] ?? '',
                'owasp' => $r['extra']['metadata']['owasp'] ?? '',
                'file' => $r['path'] ?? '',
                'startLine' => $r['start']['line'] ?? null,
                'endLine' => $r['end']['line'] ?? null,
            ];
        }

        return $vulns;
    }
}
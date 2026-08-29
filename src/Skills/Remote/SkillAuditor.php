<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Skills\Remote;

final class SkillAuditor
{
    /**
     * @param array<string, RemoteSkill> $skills
     * @return array<string, list<AuditResult>>
     */
    public function audit(GitHubSkillProvider $provider, array $skills): array
    {
        $results = [];

        foreach ($skills as $skill) {
            $files = $provider->fetchSkillFiles($skill);
            if ($files === []) {
                continue;
            }

            $analysis = $this->analyzeFiles($files);
            $results[$skill->name] = [
                new AuditResult(
                    partner: 'local_heuristics',
                    risk: $analysis['risk'],
                    signals: $analysis['signals'],
                ),
            ];
        }

        return $results;
    }

    /**
     * @param array<string, string> $files
     * @return array{risk: Risk, signals: list<string>}
     */
    private function analyzeFiles(array $files): array
    {
        $signals = [];
        $score = 0;

        $rules = [
            ['pattern' => '/rm\s+-rf\b/i', 'weight' => 5, 'signal' => 'Contains destructive delete commands'],
            ['pattern' => '/\bdrop\s+table\b/i', 'weight' => 5, 'signal' => 'Contains database drop commands'],
            ['pattern' => '/curl\b.*\|\s*(sh|bash)\b/i', 'weight' => 5, 'signal' => 'Contains curl-pipe-shell pattern'],
            ['pattern' => '/wget\b.*\|\s*(sh|bash)\b/i', 'weight' => 5, 'signal' => 'Contains wget-pipe-shell pattern'],
            ['pattern' => '/\bchmod\s+777\b/i', 'weight' => 4, 'signal' => 'Contains permissive chmod usage'],
            ['pattern' => '/\bsudo\b/i', 'weight' => 4, 'signal' => 'Requests sudo privileges'],
            ['pattern' => '/\b(kubectl\s+delete|docker\s+system\s+prune)\b/i', 'weight' => 4, 'signal' => 'Contains infra-destructive commands'],
            ['pattern' => '/\b(ssh|scp|rsync)\b/i', 'weight' => 3, 'signal' => 'Contains remote access commands'],
            ['pattern' => '/\b(api[_-]?key|secret|token)\b/i', 'weight' => 3, 'signal' => 'References sensitive credentials'],
            ['pattern' => '/\b(exfiltrate|upload\s+logs|send\s+to\s+external)\b/i', 'weight' => 3, 'signal' => 'Mentions potential data exfiltration'],
        ];

        foreach ($files as $path => $content) {
            $haystack = $path . "\n" . $content;

            foreach ($rules as $rule) {
                if (!preg_match($rule['pattern'], $haystack)) {
                    continue;
                }

                $signals[] = $rule['signal'] . " in {$path}";
                $score = max($score, $rule['weight']);
            }
        }

        $risk = match (true) {
            $score >= 5 => Risk::Critical,
            $score === 4 => Risk::High,
            $score === 3 => Risk::Medium,
            $score === 2 => Risk::Low,
            default => Risk::Safe,
        };

        return [
            'risk' => $risk,
            'signals' => array_values(array_unique($signals)),
        ];
    }
}

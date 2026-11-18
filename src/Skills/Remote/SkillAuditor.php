<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Skills\Remote;

final class SkillAuditor
{
	/**
	 * @param array<string, RemoteSkill> $skills
	 * @return array<string, AuditResult>
	 */
	public function audit(GitHubSkillProvider $provider, array $skills): array
	{
		$results = [];

		foreach ($skills as $skill) {
			$files = $provider->fetchSkillFiles($skill);
			if ($files === []) {
				$results[$skill->name] = new AuditResult(
					risk: Risk::High,
					signals: ['Skill files could not be retrieved or verified prior to installation'],
				);
				continue;
			}

			$analysis = $this->analyzeFiles($files);
			$results[$skill->name] = new AuditResult(
				risk: $analysis['risk'],
				signals: $analysis['signals'],
			);
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
			['pattern' => '/ignore\s+(all\s+)?(previous|prior)\s+instructions/i', 'weight' => 5, 'signal' => 'Contains prompt-injection override instructions'],
			['pattern' => '/\bPW_MCP_ALLOW_(EXECUTE|RESTORE)\s*=\s*1\b/', 'weight' => 4, 'signal' => 'Attempts to enable guarded MCP execution/restore flags'],
			['pattern' => '/\b(eval|shell_exec|passthru|proc_open|popen)\s*\(/i', 'weight' => 4, 'signal' => 'Contains dynamic code or shell execution calls'],
			['pattern' => '/\b(base64_decode|gzinflate|gzuncompress|str_rot13)\s*\(/i', 'weight' => 4, 'signal' => 'Contains encoded or obfuscated payload decoding calls'],
			['pattern' => '/\bbase64\s+(-d|--decode)\b/i', 'weight' => 4, 'signal' => 'Contains shell base64 decode pipeline'],
			['pattern' => '/\bchmod\s+777\b/i', 'weight' => 4, 'signal' => 'Contains permissive chmod usage'],
			['pattern' => '/\bsudo\b/i', 'weight' => 4, 'signal' => 'Requests sudo privileges'],
			['pattern' => '/\b(kubectl\s+delete|docker\s+system\s+prune)\b/i', 'weight' => 4, 'signal' => 'Contains infra-destructive commands'],
			['pattern' => '/\b(ssh|scp|rsync)\b/i', 'weight' => 3, 'signal' => 'Contains remote access commands'],
			['pattern' => '/\b(exfiltrate|upload\s+logs|send\s+to\s+external)\b/i', 'weight' => 3, 'signal' => 'Mentions potential data exfiltration'],
			['pattern' => '/\b(api[_-]?key|secret|token)\b/i', 'weight' => 2, 'signal' => 'References credential or token identifiers'],
		];

		$utf8Rules = [
			['pattern' => '/[\x{202A}-\x{202E}\x{2066}-\x{2069}\x{061C}\x{E0000}-\x{E007F}]/u', 'weight' => 5, 'signal' => 'Contains Trojan Source bidi override or invisible Unicode tag smuggling characters'],
			['pattern' => '/[\x{200B}-\x{200D}\x{2060}]/u', 'weight' => 3, 'signal' => 'Contains zero-width invisible Unicode characters'],
		];

		foreach ($files as $path => $content) {
			$haystack = $path . "\n" . $content;

			if (preg_match('//u', $haystack) !== 1) {
				$signals[] = "Contains invalid UTF-8 or binary content in {$path}";
				$score = max($score, 4);
			} else {
				foreach ($utf8Rules as $uRule) {
					if (preg_match($uRule['pattern'], $haystack) === 1) {
						$signals[] = $uRule['signal'] . " in {$path}";
						$score = max($score, $uRule['weight']);
					}
				}
			}

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

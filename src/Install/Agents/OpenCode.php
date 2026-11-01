<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Agents;

use Totoglu\Console\Boost\Install\SkillWriter;

final class OpenCode extends Agent
{
	public function name(): string
	{
		return 'opencode';
	}

	public function displayName(): string
	{
		return 'OpenCode';
	}

	public function mcpConfigPath(): ?string
	{
		return 'opencode.json';
	}

	public function systemDetectionPaths(): array
	{
		return ['~/.config/opencode', '~/.opencode'];
	}

	public function systemDetectionBinaries(): array
	{
		return ['opencode'];
	}

	public function projectDetectionPaths(): array
	{
		return ['opencode.json', '.opencode'];
	}

	public function skillsPath(): string
	{
		return '.opencode/skills';
	}

	public function mcpConfigKey(): string
	{
		return 'mcp';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function defaultMcpConfig(): array
	{
		return ['$schema' => 'https://opencode.ai/config.json'];
	}

	/**
	 * @param list<string> $args
	 * @param array<string, string> $env
	 * @return array<string, mixed>
	 */
	public function mcpServerConfig(string $command, array $args = [], array $env = [], string $cwd = ''): array
	{
		$payload = [
			'type' => 'local',
			'enabled' => true,
			'command' => array_merge([$command], $args),
			'environment' => $env,
		];

		if ($cwd !== '') {
			$payload['cwd'] = $cwd;
		}

		return $payload;
	}

	public function guidelinesPath(): string
	{
		return 'AGENTS.md';
	}

	public function exportSkill(string $skillName, string $skillPath, string $targetDir): string
	{
		$skillDirName = str_replace('_', '-', $skillName);
		$skillDir = $targetDir . '/' . $skillDirName;
		if (!is_dir($skillDir)) {
			mkdir($skillDir, 0755, true);
		}

		if (is_dir($skillPath)) {
			SkillWriter::copyDirectory($skillPath, $skillDir);
		}

		$skillMdPath = is_file($skillPath . '/SKILL.md') ? $skillPath . '/SKILL.md' : $skillPath;
		$content = is_file($skillMdPath) ? (string) file_get_contents($skillMdPath) : '';
		$targetPath = $skillDir . '/SKILL.md';

		if (str_starts_with(trim($content), '---')) {
			if (!str_contains($content, 'compatibility: opencode')) {
				$content = preg_replace('/^---\R/', "---\ncompatibility: opencode\n", $content, 1) ?? $content;
			}
			file_put_contents($targetPath, $content);
			return $targetPath;
		}

		$description = $this->extractDescription($content);
		$frontmatter = $this->buildFrontmatter($skillDirName, $description);

		file_put_contents($targetPath, $frontmatter . "\n" . $content);

		return $targetPath;
	}

	private function extractDescription(string $content): string
	{
		$lines = explode("\n", trim($content));
		$description = '';
		foreach ($lines as $line) {
			$line = trim($line);
			if (empty($line) || str_starts_with($line, '#')) {
				continue;
			}
			$description = trim(ltrim($line, '- '));
			$description = preg_replace('/^description:\s*/i', '', $description);
			break;
		}
		$description = str_replace(["\n", '*', '`'], '', $description);
		$description = preg_replace('/\s+/', ' ', $description);

		return substr($description, 0, 1024);
	}

	private function buildFrontmatter(string $name, string $description): string
	{
		return <<<YAML
---
name: {$name}
description: {$description}
license: MIT
compatibility: opencode
---
YAML;
	}
}

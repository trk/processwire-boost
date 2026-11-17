<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Agents;

use Totoglu\Console\Boost\Install\Enums\McpPathStrategy;
use Totoglu\Console\Boost\Install\SkillWriter;

final class Trae extends Agent
{
	public function name(): string
	{
		return 'trae';
	}

	public function displayName(): string
	{
		return 'Trae';
	}

	public function mcpConfigPath(): string
	{
		return '.trae/mcp.json';
	}

	public function systemDetectionPaths(): array
	{
		return ['/Applications/Trae.app', '~/.trae'];
	}

	public function projectDetectionPaths(): array
	{
		return ['.trae', '.trae/mcp.json'];
	}

	public function skillsPath(): string
	{
		return '.trae/skills';
	}

	/**
	 * Trae IDE supports ${workspaceFolder} variable natively.
	 */
	public function mcpPathStrategy(): McpPathStrategy
	{
		return McpPathStrategy::WorkspaceFolder;
	}

	public function exportSkill(string $skillName, string $skillPath, string $targetDir): string
	{
		if (!is_dir($targetDir)) {
			mkdir($targetDir, 0755, true);
		}

		$skillDir = $targetDir . '/' . $skillName;
		if (is_dir($skillDir) && realpath($skillPath) !== realpath($skillDir)) {
			SkillWriter::deleteTree($skillDir);
		}
		if (!is_dir($skillDir)) {
			mkdir($skillDir, 0755, true);
		}

		if (is_dir($skillPath)) {
			SkillWriter::copyDirectory($skillPath, $skillDir);
		}

		$skillMdPath = is_file($skillPath . '/SKILL.md') ? $skillPath . '/SKILL.md' : $skillPath;
		$raw = is_file($skillMdPath) ? (string) file_get_contents($skillMdPath) : '';
		$targetPath = $skillDir . '/SKILL.md';

		if (str_starts_with(trim($raw), '---')) {
			file_put_contents($targetPath, $raw);
			return $targetPath;
		}

		$lines = preg_split('/\R/', $raw) ?: [];
		$title = '';
		foreach ($lines as $i => $line) {
			if ($i === 0 && str_starts_with(trim($line), '#')) {
				$title = trim(ltrim(trim($line), '# '));
			}
		}
		$desc = SkillWriter::extractFrontmatterDescription($raw);

		$frontmatter = "---\nname: \"{$skillName}\"\ndescription: \"" . addcslashes($desc !== '' ? $desc : $title, "\\\"") . "\"\n---\n\n";
		$body = $raw;
		if (!str_starts_with(ltrim($body), '#')) {
			$body = "# {$title}\n\n" . $body;
		}
		file_put_contents($targetPath, $frontmatter . $body);

		return $targetPath;
	}
}

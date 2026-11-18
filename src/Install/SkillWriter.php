<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install;

use Totoglu\Console\Boost\Install\Agents\Agent;

final class SkillWriter
{
	private const RETIRED_CORE_SKILLS = [
		'pw-module-fieldtype-inputfield',
		'pw-module-filevalidator',
		'pw-module-markup',
		'pw-module-process',
		'pw-module-textformatter',
	];

	/**
	 * @param array<string, array{path: string, guidelines_path: ?string, skills_path: ?string, agents_path?: ?string, api_path?: ?string, has_guidelines: bool, has_skills: bool}> $discoverableModules
	 */
	public function __construct(
		private readonly string $targetDir,
		private readonly array $discoverableModules
	) {
	}

	/**
	 * @param list<string> $modules
	 * @param list<Agent> $agents
	 */
	public function sync(array $modules, array $agents = []): void
	{
		$target = $this->targetDir . '/skills';
		if (is_link($this->targetDir) || is_link($target)) {
			throw new \RuntimeException('Refusing to sync skills through a symbolic link.');
		}
		if (!is_dir($target)) {
			mkdir($target, 0755, true);
		}

		$skillSources = $this->collectDesiredSkillSources($modules);
		$allModuleSkills = $this->collectDesiredSkillSources(array_keys($this->discoverableModules));
		$prunableNames = array_unique(array_merge(
			array_keys(array_diff_key($allModuleSkills, $skillSources)),
			array_values(array_filter(
				self::RETIRED_CORE_SKILLS,
				static fn(string $name): bool => !isset($skillSources[$name])
			))
		));

		foreach ($prunableNames as $deselectedSkillName) {
			$staleTargetPath = $target . '/' . $deselectedSkillName;
			if (is_dir($staleTargetPath) || is_link($staleTargetPath)) {
				self::deleteTree($staleTargetPath);
			}
		}

		foreach ($skillSources as $skillName => $sourcePath) {
			$targetPath = $target . '/' . $skillName;

			if ($sourcePath === $targetPath) {
				continue;
			}

			if (is_link($targetPath)) {
				@unlink($targetPath);
			} elseif (is_dir($targetPath)) {
				self::deleteTree($targetPath);
			} elseif (is_file($targetPath)) {
				unlink($targetPath);
			}

			self::copyDirectory($sourcePath, $targetPath);
		}

		$projectRoot = dirname($this->targetDir);
		$canonicalSkills = $this->collectSkillSourcesFromDirectory($target);

		foreach ($agents as $agent) {
			$agentSkillsPath = $agent->skillsPath();

			if ($agentSkillsPath === '' || $agentSkillsPath === '.agents/skills') {
				continue;
			}

			$agentTargetDir = $projectRoot . '/' . ltrim($agentSkillsPath, '/');
			if (is_link($agentTargetDir)) {
				throw new \RuntimeException("Refusing to sync agent skills through a symbolic link: {$agentTargetDir}");
			}
			if (!is_dir($agentTargetDir)) {
				mkdir($agentTargetDir, 0755, true);
			}

			foreach ($prunableNames as $deselectedSkillName) {
				$candidates = array_unique([
					$deselectedSkillName,
					str_replace('_', '-', $deselectedSkillName),
				]);
				foreach ($candidates as $dirName) {
					$staleAgentSkillPath = $agentTargetDir . '/' . $dirName;
					if (is_dir($staleAgentSkillPath) || is_link($staleAgentSkillPath)) {
						self::deleteTree($staleAgentSkillPath);
					}
				}
			}

			foreach ($canonicalSkills as $skillName => $sourcePath) {
				$agent->exportSkill($skillName, $sourcePath, $agentTargetDir);
			}
		}
	}

	/**
	 * @param list<Agent>|null $agents
	 */
	public function removeCoreSkills(?array $agents = null): void
	{
		$managedSkills = array_unique(array_merge(
			array_keys($this->collectDesiredSkillSources(array_keys($this->discoverableModules))),
			self::RETIRED_CORE_SKILLS
		));
		foreach ($managedSkills as $skillName) {
			$skillPath = $this->targetDir . '/skills/' . $skillName;
			if (is_dir($skillPath) || is_link($skillPath)) {
				self::deleteTree($skillPath);
			}
		}

		if ($agents !== null) {
			foreach ($agents as $agent) {
				$this->removeAgentSkills($agent);
			}
		}
	}

	public function removeAgentSkills(Agent $agent): void
	{
		$skillsPath = $agent->skillsPath();
		if ($skillsPath === '' || $skillsPath === '.agents/skills') {
			return;
		}

		$projectRoot = dirname($this->targetDir);
		$skillsFullPath = $projectRoot . '/' . ltrim($skillsPath, '/');
		if (is_link(dirname($skillsFullPath)) || is_link($skillsFullPath) || !is_dir($skillsFullPath)) {
			return;
		}

		$managedNames = array_merge(
			array_keys($this->collectDesiredSkillSources(array_keys($this->discoverableModules))),
			self::RETIRED_CORE_SKILLS
		);
		$canonicalSkillsDir = $this->targetDir . '/skills';
		if (!is_link($canonicalSkillsDir) && is_dir($canonicalSkillsDir)) {
			foreach (scandir($canonicalSkillsDir) ?: [] as $entry) {
				if ($entry !== '.' && $entry !== '..') {
					$managedNames[] = $entry;
				}
			}
		}

		$candidateDirs = [];
		foreach (array_unique($managedNames) as $skillName) {
			$candidateDirs[$skillName] = true;
			$sanitized = self::sanitizeSkillName($skillName);
			if ($sanitized !== '') {
				$candidateDirs[$sanitized] = true;
				$candidateDirs[str_replace('_', '-', $sanitized)] = true;
			}
		}

		foreach (array_keys($candidateDirs) as $dirName) {
			$targetSkillDir = $skillsFullPath . '/' . $dirName;
			if (is_link($targetSkillDir)) {
				@unlink($targetSkillDir);
			} elseif (is_dir($targetSkillDir)) {
				self::deleteTree($targetSkillDir);
			}
		}

		$remaining = array_diff(scandir($skillsFullPath) ?: [], ['.', '..']);
		if ($remaining === []) {
			@rmdir($skillsFullPath);
			$parentDir = dirname($skillsFullPath);
			if (
				$parentDir !== $projectRoot
				&& !is_link($parentDir)
				&& is_dir($parentDir)
				&& array_diff(scandir($parentDir) ?: [], ['.', '..']) === []
			) {
				@rmdir($parentDir);
			}
		}
	}

	/**
	 * @param list<string> $modules
	 * @return array<string, string>
	 */
	private function collectDesiredSkillSources(array $modules): array
	{
		if (isset($this->discoverableModules['site-overrides']) && !in_array('site-overrides', $modules, true)) {
			$modules[] = 'site-overrides';
		}

		$sources = $this->collectSkillSourcesFromDirectory(dirname(__DIR__, 2) . '/resources/boost/skills');

		foreach ($modules as $moduleName) {
			if (!isset($this->discoverableModules[$moduleName])) {
				continue;
			}

			$skillsPath = $this->discoverableModules[$moduleName]['skills_path'] ?? null;
			if (!is_string($skillsPath)) {
				continue;
			}

			$sources = array_merge($sources, $this->collectSkillSourcesFromDirectory($skillsPath));
		}

		return $sources;
	}

	/**
	 * @return array<string, string>
	 */
	private function collectSkillSourcesFromDirectory(string $sourceDir): array
	{
		if (!is_dir($sourceDir)) {
			return [];
		}

		$sources = [];
		foreach (new \DirectoryIterator($sourceDir) as $file) {
			if ($file->isDot() || $file->isLink() || !$file->isDir()) {
				continue;
			}

			$skillPath = $file->getPathname();
			if (is_link($skillPath . '/SKILL.md') || !is_file($skillPath . '/SKILL.md')) {
				continue;
			}

			$sources[$file->getFilename()] = $skillPath;
		}

		return $sources;
	}

	public static function sanitizeSkillName(string $name): string
	{
		return trim((string) preg_replace('/[^a-z0-9_-]/i', '-', trim($name)), '.-');
	}

	public static function extractFrontmatterDescription(string $markdown): string
	{
		$searchTarget = $markdown;
		if (preg_match('/\A---\s*\R(.*?)\R---/s', $markdown, $fmMatches)) {
			$searchTarget = $fmMatches[1];
		}

		if (!preg_match('/^description:\s*(.+)$/m', $searchTarget, $matches)) {
			return '';
		}

		$raw = trim($matches[1]);
		if ((str_starts_with($raw, '"') && str_ends_with($raw, '"')) || (str_starts_with($raw, "'") && str_ends_with($raw, "'"))) {
			$quote = $raw[0];
			$inner = substr($raw, 1, -1);
			if ($quote === '"') {
				$inner = str_replace(['\\\\', '\\"'], ['\\', '"'], $inner);
			} else {
				$inner = str_replace("''", "'", $inner);
			}
			return trim($inner);
		}

		return $raw;
	}

	public static function copyDirectory(string $source, string $target): void
	{
		if (!is_dir($target)) {
			mkdir($target, 0755, true);
		}

		foreach (scandir($source) ?: [] as $file) {
			if ($file === '.' || $file === '..') {
				continue;
			}

			$srcFile = $source . '/' . $file;
			if (is_link($srcFile)) {
				continue;
			}

			$dstFile = $target . '/' . $file;

			if (is_dir($srcFile)) {
				self::copyDirectory($srcFile, $dstFile);
				continue;
			}

			copy($srcFile, $dstFile);
		}
	}

	public static function deleteTree(string $dir): bool
	{
		if (is_link($dir)) {
			return @unlink($dir);
		}

		if (!is_dir($dir)) {
			return false;
		}

		foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $file) {
			$path = $dir . '/' . $file;
			if (!is_link($path) && is_dir($path)) {
				self::deleteTree($path);
			} else {
				@unlink($path);
			}
		}

		return @rmdir($dir);
	}
}

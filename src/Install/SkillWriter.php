<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install;

use Totoglu\Console\Boost\Install\Agents\Agent;

final class SkillWriter
{
	/**
	 * @param array<string, array{path: string, guidelines_path: ?string, skills_path: ?string, has_guidelines: bool, has_skills: bool}> $discoverableModules
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
		if (!is_dir($target)) {
			mkdir($target, 0755, true);
		}

		$skillSources = $this->collectDesiredSkillSources($modules);
		$allModuleSkills = $this->collectDesiredSkillSources(array_keys($this->discoverableModules));

		foreach (array_diff_key($allModuleSkills, $skillSources) as $deselectedSkillName => $_path) {
			$staleTargetPath = $target . '/' . $deselectedSkillName;
			if (is_dir($staleTargetPath)) {
				self::deleteTree($staleTargetPath);
			}
		}

		foreach ($skillSources as $skillName => $sourcePath) {
			$targetPath = $target . '/' . $skillName;

			if ($sourcePath === $targetPath) {
				continue;
			}

			if (is_dir($targetPath)) {
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
			if (!is_dir($agentTargetDir)) {
				mkdir($agentTargetDir, 0755, true);
			}

			foreach (array_diff_key($allModuleSkills, $skillSources) as $deselectedSkillName => $_path) {
				$staleAgentSkillPath = $agentTargetDir . '/' . $deselectedSkillName;
				if (is_dir($staleAgentSkillPath)) {
					self::deleteTree($staleAgentSkillPath);
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
		$managedSkills = $this->collectDesiredSkillSources(array_keys($this->discoverableModules));
		foreach (array_keys($managedSkills) as $skillName) {
			$skillPath = $this->targetDir . '/skills/' . $skillName;
			if (is_dir($skillPath)) {
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
		if (is_dir($skillsFullPath)) {
			self::deleteTree($skillsFullPath);
		}
	}

	/**
	 * @param list<string> $modules
	 * @return array<string, string>
	 */
	private function collectDesiredSkillSources(array $modules): array
	{
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
			if ($file->isDot() || !$file->isDir()) {
				continue;
			}

			$skillPath = $file->getPathname();
			if (!is_file($skillPath . '/SKILL.md')) {
				continue;
			}

			$sources[$file->getFilename()] = $skillPath;
		}

		return $sources;
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
		if (!is_dir($dir)) {
			return false;
		}

		foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $file) {
			$path = $dir . '/' . $file;
			is_dir($path) ? self::deleteTree($path) : @unlink($path);
		}

		return @rmdir($dir);
	}
}

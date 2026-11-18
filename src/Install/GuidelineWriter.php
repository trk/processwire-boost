<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install;

use Totoglu\Console\Boost\Install\Agents\Agent;
use Totoglu\Console\Boost\Install\Agents\Antigravity;

final class GuidelineWriter
{
	private const CORE_GUIDELINE_ORDER = [
		'processwire-security.md',
		'processwire-core.md',
		'processwire-selectors.md',
		'processwire-development.md',
		'php.md',
		'boost.md',
	];

	private const LEGACY_GUIDELINE_PATHS = [
		'.cursorrules',
	];

	/**
	 * @param array<string, array{path: string, guidelines_path: ?string, skills_path: ?string, agents_path?: ?string, api_path?: ?string, has_guidelines: bool, has_skills: bool}> $discoverableModules
	 */
	public function __construct(
		private readonly string $projectRoot,
		private readonly string $targetDir,
		private readonly array $discoverableModules
	) {
	}

	/**
	 * @param list<Agent> $agents
	 * @param list<string> $modules
	 */
	public function write(array $agents, array $modules, bool $mcpEnabled = true): void
	{
		$effectiveAgents = $agents !== [] ? $agents : [new Antigravity()];

		/** @var array<string, list<Agent>> $groupedTargets */
		$groupedTargets = [];
		foreach ($effectiveAgents as $agent) {
			$path = $agent->guidelinesPath();
			if ($path === '') {
				continue;
			}
			$groupedTargets[$path][] = $agent;
		}

		$coreGuidelines = $this->loadCoreGuidelines();
		$moduleInstructions = $this->buildModuleInstructions($modules);
		$installedSkills = $this->loadInstalledSkills();
		$projectProfile = $this->renderProjectProfile();

		foreach ($groupedTargets as $guidelinesFile => $targetAgents) {
			$parts = [];

			$foundation = $this->renderFoundationRule($targetAgents, $installedSkills, $projectProfile, $mcpEnabled);
			if ($foundation !== null) {
				$parts[] = trim($foundation);
			}

			foreach ($coreGuidelines as $content) {
				$trimmed = trim($content);
				if ($trimmed !== '') {
					$parts[] = $trimmed;
				}
			}

			foreach ($moduleInstructions as $moduleSection) {
				$trimmed = trim($moduleSection);
				if ($trimmed !== '') {
					$parts[] = $trimmed;
				}
			}

			$fullInstructions = implode("\n\n---\n\n", $parts);
			$boostBlock = "<processwire-boost-guidelines>\n\n{$fullInstructions}\n\n</processwire-boost-guidelines>";

			$primaryAgent = $targetAgents[0];
			if (count($targetAgents) === 1) {
				$boostBlock = $primaryAgent->transformGuidelines($boostBlock);
			}

			$defaultHeader = ($guidelinesFile === 'AGENTS.md')
				? "# Universal AI Agent Instructions\n\nGenerated for ProcessWire AI Ecosystem.\n\n"
				: $primaryAgent->defaultGuidelinesHeader();

			$this->writeBoostBlock($this->projectRoot . '/' . $guidelinesFile, $boostBlock, $defaultHeader);
		}

		// Clean up retired legacy guideline files (such as .cursorrules) when replaced by modern paths
		foreach (self::LEGACY_GUIDELINE_PATHS as $legacyRelPath) {
			if (!isset($groupedTargets[$legacyRelPath])) {
				$this->removeBoostBlockFromFile($this->projectRoot . '/' . $legacyRelPath);
			}
		}
	}

	/**
	 * @param list<Agent> $agents
	 */
	public function remove(array $agents): void
	{
		$targets = ['AGENTS.md', ...self::LEGACY_GUIDELINE_PATHS];
		foreach ($agents as $agent) {
			$path = $agent->guidelinesPath();
			if ($path !== '' && !in_array($path, $targets, true)) {
				$targets[] = $path;
			}
		}

		foreach ($targets as $path) {
			$this->removeBoostBlockFromFile($this->projectRoot . '/' . $path);
		}
	}

	public function removeAgent(Agent $agent): void
	{
		$path = $agent->guidelinesPath();
		if ($path === '' || $path === 'AGENTS.md') {
			return;
		}

		$this->removeBoostBlockFromFile($this->projectRoot . '/' . $path);
		foreach (self::LEGACY_GUIDELINE_PATHS as $legacyRelPath) {
			$this->removeBoostBlockFromFile($this->projectRoot . '/' . $legacyRelPath);
		}
	}

	private function removeBoostBlockFromFile(string $fullPath): void
	{
		if (is_link($fullPath) || !is_file($fullPath)) {
			return;
		}

		$content = (string) file_get_contents($fullPath);
		if (!str_contains($content, '<processwire-boost-guidelines>')) {
			return;
		}

		$updated = preg_replace('/\R*<processwire-boost-guidelines>.*?<\/processwire-boost-guidelines>\R*/s', "\n", $content);
		$updated = is_string($updated) ? trim($updated) : trim($content);

		$isDefaultShellOnly = $updated === ''
			|| preg_match('/\A(?:---\s*\R(?:description:[^\r\n]+\R|alwaysApply:[^\r\n]+\R)+---\s*\R+)?# [^\r\n]+ Instructions\R\RGenerated for ProcessWire AI Ecosystem\.\s*\z/', $updated) === 1;

		if ($isDefaultShellOnly) {
			unlink($fullPath);
			$rootReal = rtrim($this->projectRoot, '/');
			$parentDir = dirname($fullPath);
			while (
				$parentDir !== ''
				&& $parentDir !== $rootReal
				&& str_starts_with($parentDir, $rootReal . '/')
				&& !is_link($parentDir)
				&& is_dir($parentDir)
				&& array_diff(scandir($parentDir) ?: [], ['.', '..']) === []
			) {
				@rmdir($parentDir);
				$parentDir = dirname($parentDir);
			}
		} else {
			file_put_contents($fullPath, $updated . "\n");
		}
	}

	/**
	 * Load core guidelines in deterministic priority order (security and core invariants first).
	 *
	 * @return list<string>
	 */
	private function loadCoreGuidelines(): array
	{
		$guidelinesDir = dirname(__DIR__, 2) . '/resources/boost/guidelines';
		if (!is_dir($guidelinesDir)) {
			return [];
		}

		$orderedFiles = self::CORE_GUIDELINE_ORDER;
		foreach (scandir($guidelinesDir) ?: [] as $file) {
			if (
				$file === '.'
				|| $file === '..'
				|| $file === 'foundation.md'
				|| in_array($file, $orderedFiles, true)
				|| strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'md'
			) {
				continue;
			}
			$orderedFiles[] = $file;
		}

		$guidelines = [];
		foreach ($orderedFiles as $file) {
			$filePath = $guidelinesDir . '/' . $file;
			if (!is_link($filePath) && is_file($filePath)) {
				$guidelines[] = (string) file_get_contents($filePath);
			}
		}

		return $guidelines;
	}

	/**
	 * @param list<string> $modules
	 * @return list<string>
	 */
	private function buildModuleInstructions(array $modules): array
	{
		if (isset($this->discoverableModules['site-overrides']) && !in_array('site-overrides', $modules, true)) {
			$modules[] = 'site-overrides';
		}

		if ($modules === []) {
			return [];
		}

		$instructions = [];
		$indexRows = [];

		foreach ($modules as $moduleName) {
			if (!isset($this->discoverableModules[$moduleName])) {
				continue;
			}

			$module = $this->discoverableModules[$moduleName];
			$moduleContent = '';

			if (is_string($module['guidelines_path']) && is_dir($module['guidelines_path'])) {
				foreach (scandir($module['guidelines_path']) ?: [] as $file) {
					if ($file === '.' || $file === '..' || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'md') {
						continue;
					}

					$mdPath = $module['guidelines_path'] . '/' . $file;
					if (!is_file($mdPath) || is_link($mdPath)) {
						continue;
					}

					$moduleContent .= (string) file_get_contents($mdPath) . "\n\n";
				}
			}

			if (trim($moduleContent) !== '') {
				$instructions[] = trim($moduleContent);
			}

			$agentsPath = $module['agents_path'] ?? ($module['path'] . '/AGENTS.md');
			$hasAgents = is_string($agentsPath) && !is_link($agentsPath) && is_file($agentsPath);

			$apiPath = $module['api_path'] ?? ($module['path'] . '/API.md');
			$hasApi = is_string($apiPath) && !is_link($apiPath) && is_file($apiPath);

			if (!$hasAgents && !$hasApi) {
				continue;
			}

			$description = '';
			if ($hasAgents) {
				$agentContent = (string) file_get_contents($agentsPath);
				$description = SkillWriter::extractFrontmatterDescription($agentContent);
			}
			if ($description === '' && $hasApi) {
				$apiContent = (string) file_get_contents($apiPath);
				$description = self::extractMarkdownLeadSummary($apiContent);
			}
			if ($description === '') {
				$description = "Context and API reference for {$moduleName}.";
			}
			$compactSummary = self::truncateUtf8(str_replace(['|', "\r", "\n"], ['/', ' ', ' '], $description), 120);

			$refs = [];
			if ($hasApi) {
				$refs[] = 'API: `' . $this->toRelativePath($apiPath) . '`';
			}
			if ($hasAgents) {
				$refs[] = 'Architecture: `' . $this->toRelativePath($agentsPath) . '`';
			}

			$indexRows[] = sprintf('| **%s** | %s | %s |', $moduleName, $compactSummary, implode('<br>', $refs));
		}

		if ($indexRows !== []) {
			$table = "# Module Context Index\n\n"
				. "Consult `API.md` for public signatures and `AGENTS.md` when modifying a module's own internals — **never guess module APIs**:\n\n"
				. "| Module | Summary | Reference Files |\n"
				. "|---|---|---|\n"
				. implode("\n", $indexRows);
			$instructions[] = $table;
		}

		return $instructions;
	}

	private function toRelativePath(string $path): string
	{
		return str_starts_with($path, $this->projectRoot . '/')
			? substr($path, strlen($this->projectRoot) + 1)
			: $path;
	}

	private static function extractMarkdownLeadSummary(string $markdown): string
	{
		$clean = preg_replace('/\A---\s*\R.*?\R---\s*\R?/s', '', $markdown) ?? $markdown;
		foreach (preg_split('/\R/', $clean) ?: [] as $line) {
			$trimmed = trim(ltrim(trim($line), '>'));
			if (
				$trimmed === ''
				|| str_starts_with($trimmed, '#')
				|| str_starts_with($trimmed, '```')
				|| str_starts_with($trimmed, '|')
				|| str_starts_with($trimmed, '- ')
				|| str_starts_with($trimmed, '* ')
			) {
				continue;
			}
			return self::truncateUtf8($trimmed, 160);
		}
		return '';
	}

	private static function truncateUtf8(string $text, int $maxBytes): string
	{
		$trimmed = trim($text);
		if (strlen($trimmed) <= $maxBytes) {
			return $trimmed;
		}
		$sliceLen = max(1, $maxBytes - 3);
		if (preg_match('/\A(.{1,' . $sliceLen . '})/us', $trimmed, $m) === 1) {
			return rtrim($m[1]) . '...';
		}
		return rtrim(substr($trimmed, 0, $sliceLen)) . '...';
	}

	/**
	 * @return array<string, string> Map of skillName => description
	 */
	private function loadInstalledSkills(): array
	{
		$skills = [];
		$skillsDir = $this->targetDir . '/skills';
		if (!is_dir($skillsDir) || is_link($skillsDir)) {
			return [];
		}

		foreach (new \DirectoryIterator($skillsDir) as $file) {
			if ($file->isDot() || $file->isLink() || !$file->isDir()) {
				continue;
			}

			$skillFile = $file->getPathname() . '/SKILL.md';
			if (is_link($skillFile) || !is_file($skillFile)) {
				continue;
			}

			$description = SkillWriter::extractFrontmatterDescription((string) file_get_contents($skillFile));
			$skills[$file->getFilename()] = $description !== '' ? $description : 'ProcessWire task playbook.';
		}

		ksort($skills);
		return $skills;
	}

	/**
	 * @param list<Agent> $targetAgents
	 * @param array<string, string> $installedSkills
	 */
	private function renderFoundationRule(
		array $targetAgents,
		array $installedSkills,
		string $projectProfile,
		bool $mcpEnabled
	): ?string {
		$templatePath = dirname(__DIR__, 2) . '/resources/boost/guidelines/foundation.md';
		if (!is_file($templatePath)) {
			return null;
		}

		$content = (string) file_get_contents($templatePath);

		$pwVersion = '3.x';
		if (self::isProcessWireBooted()) {
			try {
				$config = \ProcessWire\wire('config');
				if (is_object($config) && isset($config->version)) {
					$pwVersion = (string) $config->version;
				}
			} catch (\Throwable) {
				// ProcessWire instance is not booted in standalone/test context
			}
		}

		$stackSummary = 'PHP ' . PHP_VERSION . ', ProcessWire v' . $pwVersion;
		$resolutionStep1 = $this->renderResolutionStep1($targetAgents, $mcpEnabled);
		$skillsSection = $this->renderSkillsSection($targetAgents, $installedSkills);

		$rendered = str_replace(
			['{{ STACK_SUMMARY }}', '{{ PROJECT_PROFILE }}', '{{ RESOLUTION_STEP_1 }}', '{{ SKILLS_SECTION }}'],
			[$stackSummary, $projectProfile, $resolutionStep1, $skillsSection],
			$content
		);

		return preg_replace("/\n{3,}/", "\n\n", $rendered) ?? $rendered;
	}

	/**
	 * @param list<Agent> $targetAgents
	 */
	private function renderResolutionStep1(array $targetAgents, bool $mcpEnabled): string
	{
		$targetSupportsMcp = false;
		if ($mcpEnabled) {
			foreach ($targetAgents as $agent) {
				if ($agent->supportsMcp()) {
					$targetSupportsMcp = true;
					break;
				}
			}
		}

		$hasMapJson = !is_link($this->targetDir . '/map.json') && is_file($this->targetDir . '/map.json');
		$mapHint = $hasMapJson ? ' (or read `.agents/map.json` offline)' : '';

		if ($targetSupportsMcp) {
			return '**Live Runtime (MCP / CLI / Schema Map):** Call `pw_schema_read` and `pw_module_list`'
				. $mapHint
				. ' before writing selectors, templates, or migrations; test selectors via `pw_query` and inspect logs via `pw_system_logs_tail_last` (CLI equivalents: `vendor/bin/wire template:list|field:list|module:list|page:find|log:read --json`).';
		}

		return '**Schema Map & CLI Runtime:** Inspect `.agents/map.json` (when present) and run `php vendor/bin/wire template:list --json`, `field:list --json`, and `module:list --json` before writing selectors, templates, or migrations; test queries via `wire page:find --json` and inspect logs via `wire log:read --json`.';
	}

	private function renderProjectProfile(): string
	{
		$bullets = [];

		if (self::isProcessWireBooted()) {
			try {
				$config = \ProcessWire\wire('config');
				if (is_object($config)) {
					$usePageClasses = !empty($config->usePageClasses) ? 'true' : 'false';
					$useFunctionsApi = !empty($config->useFunctionsAPI) ? 'true' : 'false';
					$useMarkupRegions = !empty($config->useMarkupRegions) ? 'true' : 'false';
					$prepend = isset($config->prependTemplateFile) && is_string($config->prependTemplateFile) && $config->prependTemplateFile !== ''
						? basename($config->prependTemplateFile)
						: 'none';
					$append = isset($config->appendTemplateFile) && is_string($config->appendTemplateFile) && $config->appendTemplateFile !== ''
						? basename($config->appendTemplateFile)
						: 'none';

					$hasDefaultPageClass = !is_link($this->projectRoot . '/site/classes/DefaultPage.php')
						&& is_file($this->projectRoot . '/site/classes/DefaultPage.php');
					$pageClassNote = ($usePageClasses === 'true' && $hasDefaultPageClass) ? ' (`DefaultPage.php` base active)' : '';

					$bullets[] = sprintf(
						'- **Active Template & API Mode:** `usePageClasses=%s`%s, `useFunctionsAPI=%s`, `useMarkupRegions=%s`, `prependTemplateFile=%s`, `appendTemplateFile=%s`.',
						$usePageClasses,
						$pageClassNote,
						$useFunctionsApi,
						$useMarkupRegions,
						$prepend,
						$append
					);
				}

				$languages = \ProcessWire\wire('languages');
				if (is_iterable($languages)) {
					$langNames = [];
					foreach ($languages as $lang) {
						if (is_object($lang) && isset($lang->name) && is_string($lang->name) && $lang->name !== '') {
							$langNames[] = '`' . $lang->name . '`';
						}
					}
					if ($langNames !== []) {
						$modules = \ProcessWire\wire('modules');
						$pageNamesActive = is_object($modules) && method_exists($modules, 'isInstalled') && $modules->isInstalled('LanguageSupportPageNames');
						$bullets[] = sprintf(
							'- **Multi-Language (`LanguageSupport`):** Active (%s)%s.',
							implode(', ', $langNames),
							$pageNamesActive ? '; localized URLs enabled (`LanguageSupportPageNames`)' : ''
						);
					}
				}
			} catch (\Throwable) {
				// Ignore uninitialized runtime properties in isolated test environments
			}
		}

		$mapPath = $this->targetDir . '/map.json';
		if (!is_link($mapPath) && is_file($mapPath)) {
			$decodedMap = json_decode((string) file_get_contents($mapPath), true);
			if (is_array($decodedMap)) {
				$rawTemplates = is_array($decodedMap['templates'] ?? null) ? array_keys($decodedMap['templates']) : [];
				$fieldCount = is_array($decodedMap['fields'] ?? null) ? count($decodedMap['fields']) : 0;
				$systemTemplates = ['admin', 'user', 'role', 'permission', 'language'];
				$siteTemplates = array_values(array_filter(
					$rawTemplates,
					static fn(string $t): bool => !in_array($t, $systemTemplates, true)
						&& !str_starts_with($t, 'test_')
						&& !str_starts_with($t, 'pw_e2e_')
						&& !str_starts_with($t, 'e2e_')
						&& !str_contains($t, '_e2e_')
				));
				sort($siteTemplates);
				$preview = array_slice($siteTemplates, 0, 15);
				$tplSummary = $preview !== []
					? ' — site templates: ' . implode(', ', array_map(static fn(string $t): string => "`{$t}`", $preview))
						. (count($siteTemplates) > 15 ? sprintf(' (+%d more)', count($siteTemplates) - 15) : '')
					: '';
				$bullets[] = sprintf(
					'- **Schema Snapshot (`.agents/map.json`):** %d templates, %d fields%s.',
					count($rawTemplates),
					$fieldCount,
					$tplSummary
				);
			}
		}

		$tooling = $this->detectProjectTooling();
		if ($tooling !== '') {
			$bullets[] = '- **Detected Tooling:** ' . $tooling . '.';
		}

		if ($bullets === []) {
			return '';
		}

		return "## Active Project Profile\n\n" . implode("\n", $bullets);
	}

	private function detectProjectTooling(): string
	{
		$found = [];

		$composerPath = $this->projectRoot . '/composer.json';
		if (!is_link($composerPath) && is_file($composerPath)) {
			$composer = json_decode((string) file_get_contents($composerPath), true);
			if (is_array($composer)) {
				$deps = array_merge(
					is_array($composer['require'] ?? null) ? $composer['require'] : [],
					is_array($composer['require-dev'] ?? null) ? $composer['require-dev'] : []
				);
				foreach ([
					'trk/processwire-console' => 'processwire-console',
					'trk/processwire-boost' => 'processwire-boost',
					'pestphp/pest' => 'Pest',
					'phpunit/phpunit' => 'PHPUnit',
					'phpstan/phpstan' => 'PHPStan',
				] as $pkg => $label) {
					if (isset($deps[$pkg])) {
						$found[] = "`{$label}`";
					}
				}
			}
		}

		$packageJsonPath = $this->projectRoot . '/package.json';
		if (!is_link($packageJsonPath) && is_file($packageJsonPath)) {
			$pkgJson = json_decode((string) file_get_contents($packageJsonPath), true);
			if (is_array($pkgJson)) {
				$jsDeps = array_merge(
					is_array($pkgJson['dependencies'] ?? null) ? $pkgJson['dependencies'] : [],
					is_array($pkgJson['devDependencies'] ?? null) ? $pkgJson['devDependencies'] : []
				);
				foreach ([
					'vite' => 'Vite',
					'typescript' => 'TypeScript',
					'tailwindcss' => 'Tailwind CSS',
					'react' => 'React',
					'vue' => 'Vue',
					'@playwright/test' => 'Playwright',
				] as $pkg => $label) {
					if (isset($jsDeps[$pkg])) {
						$found[] = "`{$label}`";
					}
				}
				if (is_array($pkgJson['scripts'] ?? null) && $pkgJson['scripts'] !== []) {
					$scriptKeys = array_values(array_intersect(
						['dev', 'build', 'typecheck', 'lint', 'test', 'test:e2e'],
						array_keys($pkgJson['scripts'])
					));
					if ($scriptKeys !== []) {
						$found[] = 'npm scripts (' . implode(', ', array_map(static fn(string $s): string => "`{$s}`", $scriptKeys)) . ')';
					}
				}
			}
		}

		return implode(', ', $found);
	}

	private static function isProcessWireBooted(): bool
	{
		return class_exists('\\ProcessWire\\ProcessWire', false)
			&& \ProcessWire\ProcessWire::getCurrentInstance() !== null
			&& function_exists('\\ProcessWire\\wire');
	}

	/**
	 * Render an agent-aware skills pointer:
	 * - Native skill-loader agents receive a compact directory pointer + skill slug list (avoiding duplicate 8+ KB descriptions).
	 * - File-only agents (e.g. Amp) receive a concise 1-line-per-skill index pointing to `.agents/skills/<name>/SKILL.md`.
	 *
	 * @param list<Agent> $targetAgents
	 * @param array<string, string> $installedSkills
	 */
	private function renderSkillsSection(array $targetAgents, array $installedSkills): string
	{
		if ($installedSkills === []) {
			return '';
		}

		$allSupportNativeSkills = true;
		$skillPaths = [];
		foreach ($targetAgents as $agent) {
			if (!$agent->supportsSkills()) {
				$allSupportNativeSkills = false;
			} else {
				$skillPaths[] = $agent->skillsPath();
			}
		}

		if ($allSupportNativeSkills) {
			$uniquePaths = array_values(array_unique($skillPaths));
			$skillsDir = count($uniquePaths) === 1 ? $uniquePaths[0] : '.agents/skills';
			$slugList = implode(', ', array_map(static fn(string $s): string => "`{$s}`", array_keys($installedSkills)));

			return "## Skills Activation (`{$skillsDir}`)\n\n"
				. "Your environment loads skill descriptions natively from `{$skillsDir}/<skill>/SKILL.md`. Activate the matching skill immediately when working in any of these domains:\n\n"
				. $slugList;
		}

		$lines = [];
		foreach ($installedSkills as $name => $desc) {
			$short = self::truncateUtf8(preg_replace('/^Use when\s+/i', '', $desc) ?? $desc, 95);
			$lines[] = "- `{$name}` — {$short}";
		}

		return "## Skills Activation (`.agents/skills`)\n\n"
			. "Read `.agents/skills/<skill>/SKILL.md` before implementing tasks in any matching domain:\n\n"
			. implode("\n", $lines);
	}

	private function writeBoostBlock(string $filePath, string $boostBlock, string $defaultHeader): void
	{
		$rootReal = rtrim($this->projectRoot, '/');
		$directory = dirname($filePath);
		if (is_link($filePath)) {
			throw new \RuntimeException("Refusing to write Boost guidelines through a symbolic link: {$filePath}");
		}

		$probe = $directory;
		while ($probe !== '' && $probe !== $rootReal && str_starts_with($probe, $rootReal . '/')) {
			if (is_link($probe)) {
				throw new \RuntimeException("Refusing to write Boost guidelines through a symbolic link directory: {$probe}");
			}
			$probe = dirname($probe);
		}

		if (!is_dir($directory)) {
			mkdir($directory, 0755, true);
		}

		if (!file_exists($filePath)) {
			file_put_contents($filePath, $defaultHeader . $boostBlock . "\n");
			return;
		}

		$existing = (string) file_get_contents($filePath);
		if (str_contains($existing, '<processwire-boost-guidelines>') && str_contains($existing, '</processwire-boost-guidelines>')) {
			$pattern = '/<processwire-boost-guidelines>.*?<\/processwire-boost-guidelines>/s';
			$updated = preg_replace($pattern, $boostBlock, $existing, 1);
			file_put_contents($filePath, is_string($updated) ? $updated : $existing);
			return;
		}

		file_put_contents($filePath, rtrim($existing) . "\n\n" . $boostBlock . "\n");
	}
}

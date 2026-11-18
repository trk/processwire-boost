<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost;

use ProcessWire\Field;
use Totoglu\Console\Boost\Install\Agents\Agent;
use Totoglu\Console\Boost\Install\Agents\Amp;
use Totoglu\Console\Boost\Install\Agents\Antigravity;
use Totoglu\Console\Boost\Install\Agents\ClaudeCode;
use Totoglu\Console\Boost\Install\Agents\Codex;
use Totoglu\Console\Boost\Install\Agents\Copilot;
use Totoglu\Console\Boost\Install\Agents\Cursor;
use Totoglu\Console\Boost\Install\Agents\Factory;
use Totoglu\Console\Boost\Install\Agents\Gemini;
use Totoglu\Console\Boost\Install\Agents\GrokBuild;
use Totoglu\Console\Boost\Install\Agents\Junie;
use Totoglu\Console\Boost\Install\Agents\Kiro;
use Totoglu\Console\Boost\Install\Agents\OpenCode;
use Totoglu\Console\Boost\Install\Agents\Pi;
use Totoglu\Console\Boost\Install\Agents\Trae;
use Totoglu\Console\Boost\Install\Agents\Zed;
use Totoglu\Console\Boost\Install\GuidelineWriter;
use Totoglu\Console\Boost\Install\SkillWriter;
use Totoglu\Console\Boost\Schema\Extenders\CoreFieldSchemaExtender;
use Totoglu\Console\Boost\Schema\Extenders\RepeaterFieldSchemaExtender;
use Totoglu\Console\Boost\Schema\FieldSchemaExtender;

final class BoostManager
{
	public const VERSION = '0.3.2';

	private string $targetDir;

	/** @var array<string, class-string<Agent>> */
	private array $agents = [];

	/** @var ?array<string, array{path: string, guidelines_path: ?string, skills_path: ?string, agents_path: ?string, api_path: ?string, has_guidelines: bool, has_skills: bool}> */
	private ?array $discoveredModulesCache = null;

	private readonly string $projectRoot;

	public function __construct(?string $projectRoot = null)
	{
		if ($projectRoot === null || $projectRoot === '') {
			$pwRoot = self::readConfigPath('root');
			$projectRoot = $pwRoot !== ''
				? rtrim($pwRoot, '/')
				: (getcwd() ?: '');
		}

		$this->projectRoot = $projectRoot;
		$this->targetDir = $this->projectRoot . '/.agents';
		$this->registerBuiltInAgents();
	}

	private static function readConfigPath(string $key): string
	{
		$config = self::wireApi('config');
		$paths = is_object($config) ? ($config->paths ?? null) : null;
		if (!is_object($paths) || !method_exists($paths, 'get')) {
			return '';
		}

		$value = $paths->get($key);

		return is_string($value) ? $value : '';
	}

	/**
	 * Resolve active site/modules root directory when present.
	 */
	public static function resolveSiteModuleRootPath(string $projectRoot): string
	{
		$candidate = is_dir($projectRoot . '/site/modules')
			? $projectRoot . '/site/modules'
			: self::readConfigPath('siteModules');

		return ($candidate !== '' && !is_link($candidate) && is_dir($candidate)) ? rtrim($candidate, '/') : '';
	}

	/**
	 * Resolve active site/modules and wire/modules root directories.
	 *
	 * @return list<string>
	 */
	public static function resolveModuleRootPaths(string $projectRoot): array
	{
		$siteRoot = self::resolveSiteModuleRootPath($projectRoot);
		$wireCandidate = is_dir($projectRoot . '/wire/modules')
			? $projectRoot . '/wire/modules'
			: self::readConfigPath('modules');
		$wireRoot = ($wireCandidate !== '' && !is_link($wireCandidate) && is_dir($wireCandidate)) ? rtrim($wireCandidate, '/') : '';

		return array_values(array_filter([$siteRoot, $wireRoot], static fn(string $dir): bool => $dir !== ''));
	}

	/**
	 * Get all modules that expose Boost guidelines, skills, a root AGENTS.md, or (in site/modules) an API.md file.
	 *
	 * @return array<string, array{path: string, guidelines_path: ?string, skills_path: ?string, agents_path: ?string, api_path: ?string, has_guidelines: bool, has_skills: bool}>
	 */
	public function getDiscoverableModules(): array
	{
		if ($this->discoveredModulesCache !== null) {
			return $this->discoveredModulesCache;
		}

		$discoveries = [];
		$siteModuleRoot = self::resolveSiteModuleRootPath($this->projectRoot);

		foreach (self::resolveModuleRootPaths($this->projectRoot) as $path) {
			$isSiteRoot = $siteModuleRoot !== '' && rtrim($path, '/') === $siteModuleRoot;
			$it = new \DirectoryIterator($path);
			foreach ($it as $file) {
				if ($file->isDot() || $file->isLink() || !$file->isDir()) {
					continue;
				}

				$modulePath = $file->getPathname();

				$guidelinesDir = $this->resolveResourceDir($modulePath, 'guidelines');
				$skillsDir = $this->resolveResourceDir($modulePath, 'skills');
				$agentsCandidate = $modulePath . '/AGENTS.md';
				$hasAgentsMd = !is_link($agentsCandidate) && is_file($agentsCandidate);
				$apiCandidate = $modulePath . '/API.md';
				$hasApiMd = $isSiteRoot && !is_link($apiCandidate) && is_file($apiCandidate);

				if ($guidelinesDir !== null || $skillsDir !== null || $hasAgentsMd || $hasApiMd) {
					$discoveries[$file->getFilename()] = [
						'path' => $modulePath,
						'guidelines_path' => $guidelinesDir,
						'skills_path' => $skillsDir,
						'agents_path' => $hasAgentsMd ? $agentsCandidate : null,
						'api_path' => (!is_link($apiCandidate) && is_file($apiCandidate)) ? $apiCandidate : null,
						'has_guidelines' => $guidelinesDir !== null || $hasAgentsMd || $hasApiMd,
						'has_skills' => $skillsDir !== null,
					];
				}
			}
		}

		// Canonical site/.agents/ overrides
		$siteAgentsPath = $this->projectRoot . '/site/.agents';
		if (!is_link($siteAgentsPath) && is_dir($siteAgentsPath)) {
			$hasSiteGuidelines = !is_link($siteAgentsPath . '/guidelines') && is_dir($siteAgentsPath . '/guidelines');
			$hasSiteSkills = !is_link($siteAgentsPath . '/skills') && is_dir($siteAgentsPath . '/skills');
			if ($hasSiteGuidelines || $hasSiteSkills) {
				$discoveries['site-overrides'] = [
					'path' => $siteAgentsPath,
					'guidelines_path' => $hasSiteGuidelines ? $siteAgentsPath . '/guidelines' : null,
					'skills_path' => $hasSiteSkills ? $siteAgentsPath . '/skills' : null,
					'agents_path' => null,
					'api_path' => null,
					'has_guidelines' => $hasSiteGuidelines,
					'has_skills' => $hasSiteSkills,
				];
			}
		}

		ksort($discoveries);
		$this->discoveredModulesCache = $discoveries;

		return $this->discoveredModulesCache;
	}

	/**
	 * @param class-string<Agent> $className
	 */
	public function registerAgent(string $key, string $className): void
	{
		if (array_key_exists($key, $this->agents)) {
			throw new \InvalidArgumentException("Agent '{$key}' is already registered.");
		}

		$this->agents[$key] = $className;
	}

	/**
	 * @return array<string, class-string<Agent>>
	 */
	public function getAgents(): array
	{
		$agents = $this->agents;
		ksort($agents);

		return $agents;
	}

	/**
	 * @return array<string, Agent>
	 */
	public function instantiateAgents(): array
	{
		$instances = [];

		foreach ($this->getAgents() as $key => $className) {
			$instances[$key] = new $className();
		}

		return $instances;
	}

	/**
	 * @return list<string>
	 */
	public function detectSystemAgents(): array
	{
		$detected = [];

		foreach ($this->instantiateAgents() as $agent) {
			if ($agent->detectOnSystem()) {
				$detected[] = $agent->name();
			}
		}

		return $detected;
	}

	/**
	 * @return list<string>
	 */
	public function detectProjectAgents(string $basePath = ''): array
	{
		$targetPath = $basePath !== '' ? $basePath : $this->projectRoot;
		$detected = [];

		foreach ($this->instantiateAgents() as $agent) {
			if ($agent->detectInProject($targetPath)) {
				$detected[] = $agent->name();
			}
		}

		return $detected;
	}

	/**
	 * @return list<string>
	 */
	public function detectAvailableAgents(): array
	{
		return array_values(array_unique(array_merge(
			$this->detectProjectAgents($this->projectRoot),
			$this->detectSystemAgents()
		)));
	}

	/**
	 * @param list<string> $features
	 * @return array<string, Agent>
	 */
	public function agentOptions(array $features = []): array
	{
		$options = [];

		foreach ($this->instantiateAgents() as $agent) {
			if (!$this->supportsSelectedFeatures($agent, $features)) {
				continue;
			}

			$options[$agent->name()] = $agent;
		}

		ksort($options);

		return $options;
	}

	/**
	 * @param list<string> $agentNames
	 * @return list<Agent>
	 */
	public function resolveConfiguredAgents(array $agentNames): array
	{
		$resolved = [];

		foreach ($this->instantiateAgents() as $agent) {
			if (in_array($agent->name(), $agentNames, true) || in_array($agent->displayName(), $agentNames, true)) {
				$resolved[] = $agent;
			}
		}

		return $resolved;
	}

	/**
	 * @param list<string> $agentNames
	 * @return list<string>
	 */
	public function normalizeAgentNames(array $agentNames): array
	{
		return array_map(
			static fn(Agent $agent): string => $agent->name(),
			$this->resolveConfiguredAgents($agentNames)
		);
	}

	public function hasConfig(): bool
	{
		return is_file($this->targetDir . '/boost.json');
	}

	/**
	 * @return array{version: string, guidelines: bool, skills: bool, mcp: bool, modules: list<string>, agents: list<string>, generated_at: ?string}
	 */
	public function loadConfig(): array
	{
		$defaults = [
			'version' => self::VERSION,
			'guidelines' => false,
			'skills' => false,
			'mcp' => false,
			'modules' => [],
			'agents' => [],
			'generated_at' => null,
		];

		$configPath = $this->targetDir . '/boost.json';
		if (is_link($this->targetDir) || is_link($configPath) || !is_file($configPath)) {
			return $defaults;
		}

		$decoded = json_decode((string) file_get_contents($configPath), true);
		if (!is_array($decoded)) {
			return $defaults;
		}

		return $this->normalizeConfig($decoded, null, true);
	}

	/**
	 * @param array<string, mixed> $config
	 */
	public function saveConfig(array $config): void
	{
		$configPath = $this->targetDir . '/boost.json';
		if (is_link($this->targetDir) || is_link($configPath)) {
			throw new \RuntimeException('Refusing to write Boost configuration through a symbolic link.');
		}

		if (!is_dir($this->targetDir)) {
			mkdir($this->targetDir, 0755, true);
		}

		$normalized = $this->normalizeConfig($config, date('Y-m-d H:i:s'), false);

		file_put_contents(
			$configPath,
			json_encode(
				$normalized,
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
			) ?: '{}'
		);
	}

	/**
	 * @param array<string, mixed> $raw
	 * @return array{version: string, guidelines: bool, skills: bool, mcp: bool, modules: list<string>, agents: list<string>, generated_at: ?string}
	 */
	private function normalizeConfig(array $raw, ?string $fallbackGeneratedAt, bool $preserveVersion): array
	{
		$rawModules = is_array($raw['modules'] ?? null) ? $raw['modules'] : [];
		$rawAgents = is_array($raw['agents'] ?? null) ? $raw['agents'] : [];

		return [
			'version' => ($preserveVersion && is_string($raw['version'] ?? null)) ? $raw['version'] : self::VERSION,
			'guidelines' => (bool) ($raw['guidelines'] ?? false),
			'skills' => (bool) ($raw['skills'] ?? false),
			'mcp' => (bool) ($raw['mcp'] ?? false),
			'modules' => array_values(array_filter($rawModules, 'is_string')),
			'agents' => $this->normalizeAgentNames(array_values(array_filter($rawAgents, 'is_string'))),
			'generated_at' => is_string($raw['generated_at'] ?? null) ? $raw['generated_at'] : $fallbackGeneratedAt,
		];
	}

	/**
	 * Resolve canonical `.agents/{type}` resource directory for a module.
	 */
	private function resolveResourceDir(string $modulePath, string $resourceType): ?string
	{
		$agentsDir = $modulePath . '/.agents';
		$path = $agentsDir . '/' . $resourceType;
		if (is_link($agentsDir) || is_link($path) || !is_dir($path)) {
			return null;
		}
		return $path;
	}

	/**
	 * Apply a complete Boost configuration transition atomically:
	 * 1. Remove deselected features and agents first.
	 * 2. Install/sync active features, modules, and agents.
	 * 3. Persist normalized `.agents/boost.json`.
	 *
	 * @param list<string> $selectedFeatures
	 * @param list<string> $selectedModules
	 * @param list<string> $selectedAgentNames
	 * @return array{featuresInstalled: list<string>, featuresRemoved: list<string>, agentsInstalled: list<string>, agentsRemoved: list<string>, synced: bool}
	 */
	public function applyConfiguration(array $selectedFeatures, array $selectedModules, array $selectedAgentNames): array
	{
		$previous = $this->loadConfig();
		$previousFeatures = [
			'guidelines' => $previous['guidelines'],
			'skills' => $previous['skills'],
			'mcp' => $previous['mcp'],
		];
		$previousModules = $previous['modules'];
		$previousAgents = $previous['agents'];

		$normalizedAgents = $this->normalizeAgentNames($selectedAgentNames);
		$normalizedModules = array_values(array_unique(array_filter($selectedModules, 'is_string')));

		$featuresInstalled = [];
		$featuresRemoved = [];
		foreach (['guidelines', 'skills', 'mcp'] as $featureKey) {
			$isSelected = in_array($featureKey, $selectedFeatures, true);
			$wasInstalled = $previousFeatures[$featureKey];
			if ($isSelected && !$wasInstalled) {
				$featuresInstalled[] = $featureKey;
			} elseif (!$isSelected && $wasInstalled) {
				$featuresRemoved[] = $featureKey;
			}
		}

		$agentsInstalled = array_values(array_diff($normalizedAgents, $previousAgents));
		$agentsRemoved = array_values(array_diff($previousAgents, $normalizedAgents));
		$modulesChanged = array_diff($normalizedModules, $previousModules) !== []
			|| array_diff($previousModules, $normalizedModules) !== [];

		$previousAgentInstances = $this->resolveConfiguredAgents($previousAgents);
		foreach ($featuresRemoved as $featureKey) {
			$this->uninstallFeature($featureKey, $previousAgentInstances);
		}

		foreach ($this->resolveConfiguredAgents($agentsRemoved) as $removedAgent) {
			$this->uninstallAgent($removedAgent);
		}

		$shouldSync = $featuresInstalled !== []
			|| $featuresRemoved !== []
			|| $modulesChanged
			|| $selectedFeatures !== [];

		if ($shouldSync) {
			$activeAgentInstances = $this->resolveConfiguredAgents($normalizedAgents);
			$this->install($selectedFeatures, $normalizedModules, $activeAgentInstances);
		}

		$this->saveConfig([
			'version' => self::VERSION,
			'guidelines' => in_array('guidelines', $selectedFeatures, true),
			'skills' => in_array('skills', $selectedFeatures, true),
			'mcp' => in_array('mcp', $selectedFeatures, true),
			'modules' => $normalizedModules,
			'agents' => $normalizedAgents,
			'generated_at' => date('Y-m-d H:i:s'),
		]);

		return [
			'featuresInstalled' => $featuresInstalled,
			'featuresRemoved' => $featuresRemoved,
			'agentsInstalled' => $agentsInstalled,
			'agentsRemoved' => $agentsRemoved,
			'synced' => $shouldSync,
		];
	}

	/**
	 * Perform the installation based on choices.
	 *
	 * @param list<string> $features
	 * @param list<string> $modules
	 * @param list<Agent> $agents
	 */
	public function install(array $features, array $modules, array $agents): void
	{
		if (!is_dir($this->targetDir)) {
			mkdir($this->targetDir, 0755, true);
		}

		// 1. Mandatory Map generation
		$this->generateMap($this->targetDir . '/map.json');

		$discoverableModules = $this->getDiscoverableModules();
		if (isset($discoverableModules['site-overrides']) && !in_array('site-overrides', $modules, true)) {
			$modules[] = 'site-overrides';
		}

		// 2. Sync skills BEFORE writing guidelines so {{ SKILLS_MENU }} is populated on fresh install
		if (in_array('skills', $features, true)) {
			$skillAgents = array_values(array_filter($agents, static fn(Agent $agent): bool => $agent->supportsSkills()));
			(new SkillWriter($this->targetDir, $discoverableModules))
				->sync($modules, $skillAgents);
		}

		// 3. Write guidelines
		if (in_array('guidelines', $features, true)) {
			$guidelineAgents = array_values(array_filter($agents, static fn(Agent $agent): bool => $agent->supportsGuidelines()));
			$mcpEnabled = in_array('mcp', $features, true);
			(new GuidelineWriter($this->projectRoot, $this->targetDir, $discoverableModules))
				->write($guidelineAgents, $modules, $mcpEnabled);
		}

		// 4. Write MCP configuration
		if (in_array('mcp', $features, true)) {
			foreach ($agents as $agent) {
				if (!$agent->supportsMcp()) {
					continue;
				}

				$agent->installProjectMcp($this->projectRoot);
			}
		}
	}

	/**
	 * @param list<Agent>|null $agents
	 */
	public function uninstallFeature(string $feature, ?array $agents = null): void
	{
		if (!is_dir($this->targetDir)) {
			return;
		}

		$discoverableModules = $this->getDiscoverableModules();

		switch ($feature) {
			case 'guidelines':
				$guidelineAgents = array_values(array_filter(
					$agents ?? array_values($this->instantiateAgents()),
					static fn(Agent $agent): bool => $agent->supportsGuidelines()
				));

				(new GuidelineWriter($this->projectRoot, $this->targetDir, $discoverableModules))
					->remove($guidelineAgents);
				break;
			case 'skills':
				$skillAgents = array_values(array_filter(
					$agents ?? array_values($this->instantiateAgents()),
					static fn(Agent $agent): bool => $agent->supportsSkills()
				));
				(new SkillWriter($this->targetDir, $discoverableModules))
					->removeCoreSkills($skillAgents);
				break;
			case 'mcp':
				$mcpAgents = array_values(array_filter(
					$agents ?? array_values($this->instantiateAgents()),
					static fn(Agent $agent): bool => $agent->supportsMcp()
				));
				foreach ($mcpAgents as $agent) {
					$agent->uninstallMcp('processwire', $this->projectRoot);
				}
				break;
		}
	}

	public function uninstallAgent(Agent $agent): void
	{
		$discoverableModules = $this->getDiscoverableModules();

		if ($agent->supportsGuidelines()) {
			(new GuidelineWriter($this->projectRoot, $this->targetDir, $discoverableModules))
				->removeAgent($agent);
		}

		if ($agent->supportsSkills()) {
			(new SkillWriter($this->targetDir, $discoverableModules))
				->removeAgentSkills($agent);
		}

		if ($agent->supportsMcp()) {
			try {
				$agent->uninstallMcp('processwire', $this->projectRoot);
			} catch (\Throwable) {
				// Non-fatal: individual agent files may have permission issues
			}
		}
	}

	private function generateMap(string $path): void
	{
		if (is_link(dirname($path)) || is_link($path)) {
			throw new \RuntimeException('Refusing to write Boost schema map through a symbolic link.');
		}
		file_put_contents($path, self::encodeCompactSchemaMap($this->buildSchemaMap()));
	}

	/**
	 * Format the schema map with one entry per line so `.agents/map.json` stays compact,
	 * human-greppable, and readable within standard AI file-reading byte/line limits.
	 *
	 * @param array<string, array<string, mixed>> $map
	 */
	public static function encodeCompactSchemaMap(array $map): string
	{
		$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR;
		$sections = [];

		foreach ($map as $sectionKey => $entries) {
			$encodedKey = (string) json_encode((string) $sectionKey, $flags);
			if ($entries === []) {
				$sections[] = "\t{$encodedKey}: {}";
				continue;
			}

			$lines = [];
			foreach ($entries as $itemKey => $itemVal) {
				$k = (string) json_encode((string) $itemKey, $flags);
				$v = json_encode($itemVal, $flags) ?: 'null';
				$lines[] = "\t\t{$k}: {$v}";
			}
			$sections[] = "\t{$encodedKey}: {\n" . implode(",\n", $lines) . "\n\t}";
		}

		return "{\n" . implode(",\n", $sections) . "\n}\n";
	}

	/**
	 * Build the complete schema map (templates, fields with nested extenders, modules, roles, permissions).
	 * When `$deep` is false (default for `.agents/map.json` and overview `pw_schema_read` calls),
	 * deeply nested recursive block trees inside compound fields are summarized at depth 1 so single
	 * fields cannot bloat the payload by tens of kilobytes.
	 *
	 * @return array{templates: array<string, mixed>, fields: array<string, mixed>, modules: array<string, mixed>, roles: array<string, mixed>, permissions: array<string, mixed>}
	 */
	public function buildSchemaMap(bool $deep = false): array
	{
		$map = [
			'templates' => [],
			'fields' => [],
			'modules' => [],
			'roles' => [],
			'permissions' => [],
		];

		$templates = $this->wireApi('templates');
		if (is_iterable($templates)) {
			foreach ($templates as $template) {
				if (!is_object($template)) {
					continue;
				}
				$fieldgroup = method_exists($template, 'get')
					? $template->get('fieldgroup')
					: (property_exists($template, 'fieldgroup') ? $template->fieldgroup : null);
				$tplName = method_exists($template, 'get')
					? (string) $template->get('name')
					: (property_exists($template, 'name') ? (string) $template->name : '');
				if ($tplName === '') {
					continue;
				}
				$tplEntry = [
					'id' => method_exists($template, 'get') ? $template->get('id') : ($template->id ?? 0),
					'fields' => array_values(array_map(
						static fn(object $f): string => method_exists($f, 'get') ? (string) $f->get('name') : (property_exists($f, 'name') ? (string) $f->name : ''),
						$fieldgroup instanceof \Traversable ? iterator_to_array($fieldgroup, false) : []
					)),
				];

				if (method_exists($template, 'getPageClass')) {
					try {
						$pageClass = (string) $template->getPageClass(false);
						if ($pageClass !== '' && $pageClass !== 'Page') {
							$tplEntry['pageClass'] = $pageClass;
						}
					} catch (\Throwable) {
						// Ignore pageClass resolution errors in standalone mock objects
					}
				}

				$urlSegments = method_exists($template, 'urlSegments')
					? $template->urlSegments()
					: ($template->urlSegments ?? 0);
				if (is_array($urlSegments) && $urlSegments !== []) {
					$tplEntry['urlSegments'] = array_values(array_map('strval', $urlSegments));
				} elseif (!empty($urlSegments)) {
					$tplEntry['urlSegments'] = true;
				}

				$allowPageNum = method_exists($template, 'get') ? $template->get('allowPageNum') : ($template->allowPageNum ?? 0);
				if ((int) $allowPageNum === 1) {
					$tplEntry['allowPageNum'] = true;
				}

				$noChildren = method_exists($template, 'get') ? $template->get('noChildren') : ($template->noChildren ?? 0);
				if ((int) $noChildren === 1) {
					$tplEntry['noChildren'] = true;
				}

				$map['templates'][$tplName] = $tplEntry;
			}
		}

		$fields = $this->wireApi('fields');
		if (is_iterable($fields)) {
			$extenders = $this->resolveFieldSchemaExtenders();

			foreach ($fields as $field) {
				if (!is_object($field)) {
					continue;
				}
				$base = self::buildFieldBaseSchema($field, 'Unknown');

				$extensions = $field instanceof \ProcessWire\Field
					? $this->collectFieldSchemaExtra($field, $base, $extenders)
					: [];

				// `fields` is a reserved extension key promoted to top-level so
				// nested schemas stay ergonomic for agents and future extenders.
				if (array_key_exists('fields', $extensions)) {
					$nestedFields = is_array($extensions['fields']) ? $extensions['fields'] : [];
					$base['fields'] = $deep ? $nestedFields : self::compactNestedFields($nestedFields);
					unset($extensions['fields']);
				}

				if ($extensions !== []) {
					$base['extra'] = $extensions;
				}

				$fieldName = method_exists($field, 'get') ? (string) $field->get('name') : (property_exists($field, 'name') ? (string) $field->name : '');
				if ($fieldName !== '') {
					$map['fields'][$fieldName] = $base;
				}
			}
		}

		$modules = $this->wireApi('modules');
		if (is_object($modules) && is_iterable($modules) && method_exists($modules, 'getModuleInfo')) {
			foreach ($modules as $module) {
				if (!is_object($module) || !method_exists($module, 'className')) {
					continue;
				}
				$info = method_exists($modules, 'getModuleInfoVerbose')
					? $modules->getModuleInfoVerbose($module)
					: $modules->getModuleInfo($module);
				if (!is_array($info)) {
					$info = [];
				}
				$rawVersion = $info['version'] ?? '';
				$formattedVersion = method_exists($modules, 'formatVersion') && $rawVersion !== ''
					? (string) $modules->formatVersion($rawVersion)
					: (string) $rawVersion;
				$isCoreModule = (bool) ($info['core'] ?? false);
				$modEntry = [
					'title' => (string) ($info['title'] ?? ''),
					'version' => $formattedVersion,
				];
				if (!$isCoreModule) {
					$modEntry['site'] = true;
					$summary = (string) ($info['summary'] ?? '');
					if ($summary !== '') {
						$modEntry['summary'] = $summary;
					}
				}
				$map['modules'][(string) $module->className()] = $modEntry;
			}
		}

		$roles = $this->wireApi('roles');
		if (is_iterable($roles)) {
			foreach ($roles as $role) {
				if (!is_object($role)) {
					continue;
				}
				$rolePerms = method_exists($role, 'get')
					? $role->get('permissions')
					: (property_exists($role, 'permissions') ? $role->permissions : null);
				$roleName = method_exists($role, 'get')
					? (string) $role->get('name')
					: (property_exists($role, 'name') ? (string) $role->name : '');
				if ($roleName === '') {
					continue;
				}
				$map['roles'][$roleName] = [
					'id' => method_exists($role, 'get') ? $role->get('id') : ($role->id ?? 0),
					'permissions' => array_values(array_map(
						static fn(object $p): string => method_exists($p, 'get') ? (string) $p->get('name') : (property_exists($p, 'name') ? (string) $p->name : ''),
						$rolePerms instanceof \Traversable ? iterator_to_array($rolePerms, false) : []
					)),
				];
			}
		}

		$permissions = $this->wireApi('permissions');
		if (is_iterable($permissions)) {
			foreach ($permissions as $permission) {
				if (!is_object($permission)) {
					continue;
				}
				$permName = method_exists($permission, 'get')
					? (string) $permission->get('name')
					: (property_exists($permission, 'name') ? (string) $permission->name : '');
				if ($permName === '') {
					continue;
				}
				$permTitle = method_exists($permission, 'get')
					? $permission->get('title')
					: (property_exists($permission, 'title') ? $permission->title : '');
				$map['permissions'][$permName] = [
					'id' => method_exists($permission, 'get') ? $permission->get('id') : ($permission->id ?? 0),
					'title' => is_scalar($permTitle) || $permTitle instanceof \Stringable ? (string) $permTitle : '',
				];
			}
		}

		return $map;
	}

	/**
	 * Build the normalized base schema array for a ProcessWire Field.
	 *
	 * @return array{id: int, type: string, label: string}
	 */
	public static function buildFieldBaseSchema(object $field, string $defaultType = ''): array
	{
		$type = $field->type ?? null;

		return [
			'id' => (int) ($field->id ?? 0),
			'type' => is_object($type) && method_exists($type, 'className') ? (string) $type->className() : $defaultType,
			'label' => (string) ($field->label ?? ''),
		];
	}

	/**
	 * Keep 1st-level compound subfields intact while summarizing recursive `blocks` subtrees
	 * unless `$deep = true` (e.g. `pw_schema_read` with `field` or `deep: true`).
	 *
	 * @param array<string, mixed> $fields
	 * @return array<string, mixed>
	 */
	private static function compactNestedFields(array $fields): array
	{
		$compacted = [];
		foreach ($fields as $subKey => $subDef) {
			if (!is_array($subDef)) {
				$compacted[$subKey] = $subDef;
				continue;
			}
			if (isset($subDef['blocks']) && is_array($subDef['blocks'])) {
				$blockNames = array_keys($subDef['blocks']);
				unset($subDef['blocks']);
				$subDef['blockTypes'] = $blockNames;
				$subDef['deepHint'] = 'Pass field name or deep=true to pw_schema_read to expand nested block fields.';
			}
			$compacted[$subKey] = $subDef;
		}

		return $compacted;
	}

	private static function wireApi(string $name): mixed
	{
		if (!function_exists('\ProcessWire\wire')) {
			return null;
		}

		try {
			return \ProcessWire\wire($name);
		} catch (\Throwable) {
			return null;
		}
	}

	/**
	 * @return FieldSchemaExtender[]
	 */
	private function resolveFieldSchemaExtenders(): array
	{
		$resolved = [];
		$coreExtender = new CoreFieldSchemaExtender();
		$resolved[$coreExtender::class] = $coreExtender;
		$defaultExtender = new RepeaterFieldSchemaExtender();
		$resolved[$defaultExtender::class] = $defaultExtender;

		foreach ($this->resolveExtenderManifestPaths() as $manifestPath) {
			try {
				$manifest = require $manifestPath;
			} catch (\Throwable $e) {
				$this->logBoostMessage(sprintf(
					'Failed to load field schema extender manifest "%s": %s',
					$manifestPath,
					$e->getMessage()
				));
				continue;
			}

			if (!is_array($manifest)) {
				continue;
			}

			foreach (array_values($manifest) as $entry) {
				$extender = $this->instantiateExtenderEntry($entry);
				if ($extender !== null) {
					$resolved[$extender::class] = $extender;
				}
			}
		}

		return array_values($resolved);
	}

	/**
	 * @return list<string>
	 */
	private function resolveExtenderManifestPaths(): array
	{
		$paths = [];
		$siteManifest = $this->projectRoot . '/site/.agents/schema/field-extenders.php';
		if (is_file($siteManifest) && !is_link($siteManifest)) {
			$paths[] = $siteManifest;
		}

		foreach (self::resolveModuleRootPaths($this->projectRoot) as $moduleRoot) {
			foreach (new \DirectoryIterator($moduleRoot) as $moduleDir) {
				if ($moduleDir->isDot() || !$moduleDir->isDir() || $moduleDir->isLink()) {
					continue;
				}

				$manifest = $moduleDir->getPathname() . '/.agents/schema/field-extenders.php';
				if (is_file($manifest) && !is_link($manifest)) {
					$paths[] = $manifest;
				}
			}
		}

		return $paths;
	}

	private function instantiateExtenderEntry(mixed $entry): ?FieldSchemaExtender
	{
		if ($entry instanceof FieldSchemaExtender) {
			return $entry;
		}

		if (
			!is_string($entry)
			|| $entry === ''
			|| preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $entry) !== 1
			|| !class_exists($entry)
		) {
			return null;
		}

		try {
			$ref = new \ReflectionClass($entry);
			if (!$ref->isInstantiable() || !$ref->implementsInterface(FieldSchemaExtender::class)) {
				return null;
			}
			/** @var FieldSchemaExtender $instance */
			$instance = $ref->newInstance();
			return $instance;
		} catch (\Throwable $e) {
			$this->logBoostMessage(sprintf(
				'Failed to instantiate field schema extender "%s": %s',
				$entry,
				$e->getMessage()
			));
			return null;
		}
	}

	private function logBoostMessage(string $message): void
	{
		try {
			$log = self::wireApi('log');
			if ($log) {
				$log->save('processwire-boost', $message);
			}
		} catch (\Throwable) {
			// Keep discovery and schema generation non-fatal.
		}
	}

	/**
	 * @param array{id:int,type:string,label:string} $base
	 * @param FieldSchemaExtender[] $extenders
	 * @return array<string,mixed>
	 */
	private function collectFieldSchemaExtra(Field $field, array $base, array $extenders): array
	{
		$extra = [];

		foreach ($extenders as $extender) {
			$sanitized = [];
			try {
				if (!$extender->supports($field)) {
					continue;
				}

				$extended = $extender->extend($field, $base);
				$sanitized = $this->sanitizeFieldSchemaExtra($extended);
			} catch (\Throwable $e) {
				$this->logSchemaExtenderError($extender, $field, $e);
				continue;
			}

			if ($sanitized !== []) {
				$extra = array_merge($extra, $sanitized);
			}
		}

		return $extra;
	}

	/**
	 * @param array<string,mixed> $extra
	 * @return array<string,mixed>
	 */
	private function sanitizeFieldSchemaExtra(array $extra): array
	{
		$clean = [];

		foreach ($extra as $key => $value) {
			$sanitized = null;
			if (!$this->sanitizeFieldSchemaValue($value, $sanitized)) {
				continue;
			}

			$clean[(string) $key] = $sanitized;
		}

		return $clean;
	}

	private function sanitizeFieldSchemaValue(mixed $value, mixed &$sanitized): bool
	{
		if (is_float($value) && !is_finite($value)) {
			return false;
		}
		if (is_scalar($value) || $value === null) {
			$sanitized = $value;
			return true;
		}

		if (!is_array($value)) {
			return false;
		}

		$result = [];
		foreach ($value as $key => $item) {
			$child = null;
			if (!$this->sanitizeFieldSchemaValue($item, $child)) {
				continue;
			}
			$result[(string) $key] = $child;
		}

		$sanitized = $result;
		return true;
	}

	private function logSchemaExtenderError(FieldSchemaExtender $extender, Field $field, \Throwable $e): void
	{
		$this->logBoostMessage(sprintf(
			'Field schema extender "%s" failed for field "%s": %s',
			$extender::class,
			$field->name,
			$e->getMessage()
		));
	}

	private function registerBuiltInAgents(): void
	{
		$builtIn = [
			'amp' => Amp::class,
			'antigravity' => Antigravity::class,
			'claude_code' => ClaudeCode::class,
			'codex' => Codex::class,
			'copilot' => Copilot::class,
			'cursor' => Cursor::class,
			'factory' => Factory::class,
			'gemini' => Gemini::class,
			'grok_build' => GrokBuild::class,
			'junie' => Junie::class,
			'kiro' => Kiro::class,
			'opencode' => OpenCode::class,
			'pi' => Pi::class,
			'trae' => Trae::class,
			'zed' => Zed::class,
		];

		foreach ($builtIn as $key => $className) {
			if (!array_key_exists($key, $this->agents)) {
				$this->agents[$key] = $className;
			}
		}
	}

	/**
	 * @param list<string> $features
	 */
	private function supportsSelectedFeatures(Agent $agent, array $features): bool
	{
		if ($features === []) {
			return true;
		}

		foreach ($features as $feature) {
			$supported = match ($feature) {
				'guidelines' => $agent->supportsGuidelines(),
				'skills' => $agent->supportsSkills(),
				'mcp' => $agent->supportsMcp(),
				default => false,
			};

			if ($supported) {
				return true;
			}
		}

		return false;
	}
}

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
use Totoglu\Console\Boost\Schema\Extenders\RepeaterFieldSchemaExtender;
use Totoglu\Console\Boost\Schema\FieldSchemaExtender;

final class BoostManager
{
	public const VERSION = '0.3.0';

	private string $targetDir;

	/** @var array<string, class-string<Agent>> */
	private array $agents = [];

	/** @var ?array<string, array{path: string, guidelines_path: ?string, skills_path: ?string, has_guidelines: bool, has_skills: bool}> */
	private ?array $discoveredModulesCache = null;

	private readonly string $projectRoot;

	public function __construct(?string $projectRoot = null)
	{
		if ($projectRoot === null || $projectRoot === '') {
			$config = $this->wireApi('config');
			$projectRoot = is_object($config) && isset($config->paths->root)
				? rtrim((string) $config->paths->root, '/')
				: (getcwd() ?: '');
		}

		$this->projectRoot = $projectRoot;
		$this->targetDir = $this->projectRoot . '/.agents';
		$this->registerBuiltInAgents();
	}

	/**
	 * Resolve active site/modules and wire/modules root directories.
	 *
	 * @return list<string>
	 */
	public static function resolveModuleRootPaths(string $projectRoot): array
	{
		$config = self::wireApi('config');

		$candidates = [
			is_dir($projectRoot . '/site/modules')
				? $projectRoot . '/site/modules'
				: (string) ($config?->paths?->siteModules ?? ''),
			is_dir($projectRoot . '/wire/modules')
				? $projectRoot . '/wire/modules'
				: (string) ($config?->paths?->modules ?? ''),
		];

		return array_values(array_filter(
			$candidates,
			static fn(string $dir): bool => $dir !== '' && is_dir($dir)
		));
	}

	/**
	 * Get all modules that expose Boost guidelines, skills, or a root AGENTS.md file.
	 *
	 * @return array<string, array{path: string, guidelines_path: ?string, skills_path: ?string, has_guidelines: bool, has_skills: bool}>
	 */
	public function getDiscoverableModules(): array
	{
		if ($this->discoveredModulesCache !== null) {
			return $this->discoveredModulesCache;
		}

		$discoveries = [];

		foreach (self::resolveModuleRootPaths($this->projectRoot) as $path) {
			$it = new \DirectoryIterator($path);
			foreach ($it as $file) {
				if ($file->isDot() || !$file->isDir()) {
					continue;
				}

				$modulePath = $file->getPathname();

				// Priority chain for each resource type: boost/{type} > .agents/{type}
				$guidelinesDir = $this->resolveResourceDir($modulePath, 'guidelines');
				$skillsDir = $this->resolveResourceDir($modulePath, 'skills');
				$hasAgentsMd = is_file($modulePath . '/AGENTS.md');

				if ($guidelinesDir !== null || $skillsDir !== null || $hasAgentsMd) {
					$discoveries[$file->getFilename()] = [
						'path' => $modulePath,
						'guidelines_path' => $guidelinesDir,
						'skills_path' => $skillsDir,
						'has_guidelines' => $guidelinesDir !== null || $hasAgentsMd,
						'has_skills' => $skillsDir !== null,
					];
				}
			}
		}

		// Special site/boost/ check
		$siteBoostPath = $this->projectRoot . '/site/boost';
		if (is_dir($siteBoostPath)) {
			$hasSiteGuidelines = is_dir($siteBoostPath . '/guidelines');
			$hasSiteSkills = is_dir($siteBoostPath . '/skills');
			if ($hasSiteGuidelines || $hasSiteSkills) {
				$discoveries['site-overrides'] = [
					'path' => $siteBoostPath,
					'guidelines_path' => $hasSiteGuidelines ? $siteBoostPath . '/guidelines' : null,
					'skills_path' => $hasSiteSkills ? $siteBoostPath . '/skills' : null,
					'has_guidelines' => $hasSiteGuidelines,
					'has_skills' => $hasSiteSkills,
				];
			}
		}

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
		return array_values(array_map(
			static fn(Agent $agent): string => $agent->name(),
			$this->resolveConfiguredAgents($agentNames)
		));
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
		if (!is_file($configPath)) {
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
		if (!is_dir($this->targetDir)) {
			mkdir($this->targetDir, 0755, true);
		}

		$normalized = $this->normalizeConfig($config, date('Y-m-d H:i:s'), false);

		file_put_contents(
			$this->targetDir . '/boost.json',
			json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
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
	 * Resolve resource directory for a module with priority chain:
	 *   {module}/boost/{type} > {module}/.agents/{type}
	 */
	private function resolveResourceDir(string $modulePath, string $resourceType): ?string
	{
		$candidates = [
			$modulePath . '/boost/' . $resourceType,
			$modulePath . '/.agents/' . $resourceType,
		];

		foreach ($candidates as $path) {
			if (is_dir($path)) {
				return $path;
			}
		}

		return null;
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

		// 2. Sync skills BEFORE writing guidelines so {{ SKILLS_MENU }} is populated on fresh install
		if (in_array('skills', $features, true)) {
			$skillAgents = array_values(array_filter($agents, static fn(Agent $agent): bool => $agent->supportsSkills()));
			(new SkillWriter($this->targetDir, $discoverableModules))
				->sync($modules, $skillAgents);
		}

		// 3. Write guidelines
		if (in_array('guidelines', $features, true)) {
			$guidelineAgents = array_values(array_filter($agents, static fn(Agent $agent): bool => $agent->supportsGuidelines()));
			(new GuidelineWriter($this->projectRoot, $this->targetDir, $discoverableModules))
				->write($guidelineAgents, $modules);
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
				(new SkillWriter($this->targetDir, $discoverableModules))
					->removeCoreSkills($agents);
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
		file_put_contents($path, json_encode($this->buildSchemaMap(), JSON_PRETTY_PRINT));
	}

	/**
	 * Build the complete schema map (templates, fields with nested extenders, modules, roles, permissions).
	 *
	 * @return array{templates: array<string, mixed>, fields: array<string, mixed>, modules: array<string, mixed>, roles: array<string, mixed>, permissions: array<string, mixed>}
	 */
	public function buildSchemaMap(): array
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
				$fieldgroup = $template->fieldgroup;
				$map['templates'][$template->name] = [
					'id' => $template->id,
					'fields' => array_map(static fn(object $f): string => (string) $f->name, $fieldgroup ? iterator_to_array($fieldgroup) : []),
				];
			}
		}

		$fields = $this->wireApi('fields');
		if (is_iterable($fields)) {
			$extenders = $this->resolveFieldSchemaExtenders();

			foreach ($fields as $field) {
				$base = self::buildFieldBaseSchema($field, 'Unknown');

				$extensions = $this->collectFieldSchemaExtra($field, $base, $extenders);

				// `fields` is a reserved extension key promoted to top-level so
				// nested schemas stay ergonomic for agents and future extenders.
				if (array_key_exists('fields', $extensions)) {
					$base['fields'] = is_array($extensions['fields']) ? $extensions['fields'] : [];
					unset($extensions['fields']);
				}

				if ($extensions !== []) {
					$base['extra'] = $extensions;
				}

				$map['fields'][$field->name] = $base;
			}
		}

		$modules = $this->wireApi('modules');
		if (is_iterable($modules)) {
			foreach ($modules as $module) {
				$info = $modules->getModuleInfo($module);
				if (!is_array($info)) {
					$info = [];
				}
				$map['modules'][$module->className()] = [
					'title' => $info['title'] ?? '',
					'version' => $info['version'] ?? '',
					'summary' => $info['summary'] ?? '',
				];
			}
		}

		$roles = $this->wireApi('roles');
		if (is_iterable($roles)) {
			foreach ($roles as $role) {
				$map['roles'][$role->name] = [
					'id' => $role->id,
					'permissions' => array_map(static fn(object $p): string => (string) $p->name, iterator_to_array($role->permissions)),
				];
			}
		}

		$permissions = $this->wireApi('permissions');
		if (is_iterable($permissions)) {
			foreach ($permissions as $permission) {
				$map['permissions'][$permission->name] = [
					'id' => $permission->id,
					'title' => $permission->title,
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
		$siteManifest = $this->projectRoot . '/site/boost/schema/field-extenders.php';
		if (is_file($siteManifest)) {
			$paths[] = $siteManifest;
		}

		foreach (self::resolveModuleRootPaths($this->projectRoot) as $moduleRoot) {
			foreach (new \DirectoryIterator($moduleRoot) as $moduleDir) {
				if ($moduleDir->isDot() || !$moduleDir->isDir()) {
					continue;
				}

				$base = $moduleDir->getPathname();
				foreach ([$base . '/boost/schema/field-extenders.php', $base . '/.agents/schema/field-extenders.php'] as $manifest) {
					if (is_file($manifest)) {
						$paths[] = $manifest;
						break;
					}
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

		if (!is_string($entry) || $entry === '' || !class_exists($entry)) {
			return null;
		}

		try {
			$instance = new $entry();
		} catch (\Throwable $e) {
			$this->logBoostMessage(sprintf(
				'Failed to instantiate field schema extender "%s": %s',
				$entry,
				$e->getMessage()
			));
			return null;
		}

		return $instance instanceof FieldSchemaExtender ? $instance : null;
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

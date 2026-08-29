<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost;

use ProcessWire\Field;
use Totoglu\Console\Boost\Contracts\SupportsGuidelines;
use Totoglu\Console\Boost\Contracts\SupportsMcp;
use Totoglu\Console\Boost\Install\Agents\Amp;
use Totoglu\Console\Boost\Install\Agents\Agent;
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
use Totoglu\Console\Boost\Install\McpWriter;
use Totoglu\Console\Boost\Install\SkillWriter;
use Totoglu\Console\Boost\Schema\FieldSchemaExtender;
use Totoglu\Console\Boost\Schema\FieldSchemaExtenderDiscovery;

final class BoostManager
{
    private string $targetDir;

    /** @var array<string, class-string<Agent>> */
    private array $agents = [];

    public function __construct(
        private readonly string $projectRoot
    ) {
        $this->targetDir = $this->projectRoot . '/.agents';
        $this->registerBuiltInAgents();
    }

    /**
     * Get all modules that have boost/ directory
     * 
     * @return array<string, array{path: string, has_guidelines: bool, has_skills: bool}>
     */
    public function getDiscoverableModules(): array
    {
        $config = \ProcessWire\wire('config');
        $modulePaths = [
            'site' => $config->paths->siteModules,
            'core' => $config->paths->modules,
        ];

        $discoveries = [];

        foreach ($modulePaths as $type => $path) {
            if (!is_dir($path)) continue;

            $it = new \DirectoryIterator($path);
            foreach ($it as $file) {
                if ($file->isDot() || !$file->isDir()) continue;

                $modulePath = $file->getPathname();

                // Priority chain for each resource type: boost/{type} > .agents/{type} > {type}
                $guidelinesDir = $this->resolveResourceDir($modulePath, 'guidelines');
                $skillsDir = $this->resolveResourceDir($modulePath, 'skills');

                if ($guidelinesDir || $skillsDir) {
                    $discoveries[$file->getFilename()] = [
                        'path' => $modulePath,
                        'guidelines_path' => $guidelinesDir,
                        'skills_path' => $skillsDir,
                        'has_guidelines' => $guidelinesDir !== null,
                        'has_skills' => $skillsDir !== null,
                    ];
                }
            }
        }

        // Special site/boost/ check
        $siteBoostPath = $this->projectRoot . '/site/boost';
        if (is_dir($siteBoostPath)) {
            $discoveries['site-overrides'] = [
                'path' => $siteBoostPath,
                'guidelines_path' => is_dir($siteBoostPath . '/guidelines') ? $siteBoostPath . '/guidelines' : null,
                'skills_path' => is_dir($siteBoostPath . '/skills') ? $siteBoostPath . '/skills' : null,
                'has_guidelines' => is_dir($siteBoostPath . '/guidelines'),
                'has_skills' => is_dir($siteBoostPath . '/skills'),
            ];
        }

        return $discoveries;
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
     * Resolve resource directory for a module — standard path: {module}/.agents/{type}
     */
    private function resolveResourceDir(string $modulePath, string $resourceType): ?string
    {
        $path = $modulePath . '/.agents/' . $resourceType;

        return is_dir($path) ? $path : null;
    }

    /**
     * Perform the installation based on choices
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

        if (in_array('guidelines', $features, true)) {
            $guidelineAgents = array_values(array_filter($agents, static fn (Agent $agent): bool => $agent instanceof SupportsGuidelines));
            (new GuidelineWriter($this->projectRoot, $this->targetDir, $this->getDiscoverableModules()))
                ->write($guidelineAgents, $modules);
        }

        if (in_array('skills', $features, true)) {
            (new SkillWriter($this->targetDir, $this->getDiscoverableModules()))
                ->sync($modules);
        }

        if (in_array('mcp', $features, true)) {
            foreach ($agents as $agent) {
                if (!$agent instanceof SupportsMcp) {
                    continue;
                }

                (new McpWriter($agent, $this->projectRoot))->write();
            }
        }
    }

    private function generateMap(string $path): void
    {
        $map = [
            'templates' => [],
            'fields' => [],
            'modules' => [],
            'roles' => [],
            'permissions' => [],
        ];

        foreach (\ProcessWire\wire('templates') as $template) {
            $map['templates'][$template->name] = [
                'id' => $template->id,
                'fields' => array_map(fn($f) => $f->name, iterator_to_array($template->fields)),
            ];
        }

        $extenders = $this->resolveFieldSchemaExtenders();

        foreach (\ProcessWire\wire('fields') as $field) {
            $base = [
                'id' => $field->id,
                'type' => $field->type->className(),
                'label' => $field->label,
            ];

            $extensions = $this->collectFieldSchemaExtra($field, $base, $extenders);

            // `fields` is a reserved extension key promoted to top-level so
            // nested schemas stay ergonomic for agents and future extenders.
            if (array_key_exists('fields', $extensions)) {
                $base['fields'] = is_array($extensions['fields']) ? $extensions['fields'] : [];
                unset($extensions['fields']);
            }

            if (!empty($extensions)) {
                $base['extra'] = $extensions;
            }

            $map['fields'][$field->name] = $base;
        }

        foreach (\ProcessWire\wire('modules') as $module) {
            $info = \ProcessWire\wire('modules')->getModuleInfo($module);
            $map['modules'][$module->className()] = [
                'title' => $info['title'] ?? '',
                'version' => $info['version'] ?? '',
                'summary' => $info['summary'] ?? '',
            ];
        }

        foreach (\ProcessWire\wire('roles') as $role) {
            $map['roles'][$role->name] = [
                'id' => $role->id,
                'permissions' => array_map(fn($p) => $p->name, iterator_to_array($role->permissions))
            ];
        }

        foreach (\ProcessWire\wire('permissions') as $permission) {
            $map['permissions'][$permission->name] = [
                'id' => $permission->id,
                'title' => $permission->title,
            ];
        }

        file_put_contents($path, json_encode($map, JSON_PRETTY_PRINT));
    }

    /**
     * @return FieldSchemaExtender[]
     */
    private function resolveFieldSchemaExtenders(): array
    {
        return (new FieldSchemaExtenderDiscovery($this->projectRoot))->resolve();
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

            if (!empty($sanitized)) {
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

            $clean[(string)$key] = $sanitized;
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
            $result[(string)$key] = $child;
        }

        $sanitized = $result;
        return true;
    }

    private function logSchemaExtenderError(FieldSchemaExtender $extender, Field $field, \Throwable $e): void
    {
        $log = \ProcessWire\wire('log');
        if (!$log) {
            return;
        }

        $log->save('processwire-boost', sprintf(
            'Field schema extender "%s" failed for field "%s": %s',
            $extender::class,
            $field->name,
            $e->getMessage()
        ));
    }

    /**
     * @param list<Agent>|null $agents
     */
    public function uninstallFeature(string $feature, ?array $agents = null): void
    {
        if (!is_dir($this->targetDir)) {
            return;
        }

        switch ($feature) {
            case 'guidelines':
                $guidelineAgents = array_values(array_filter(
                    $agents ?? array_values($this->instantiateAgents()),
                    static fn (Agent $agent): bool => $agent instanceof SupportsGuidelines
                ));

                (new GuidelineWriter($this->projectRoot, $this->targetDir, $this->getDiscoverableModules()))
                    ->remove($guidelineAgents);
                break;
            case 'skills':
                (new SkillWriter($this->targetDir, $this->getDiscoverableModules()))
                    ->removeCoreSkills();
                break;
        }
    }

    public function sync(array $features, array $modules, array $agents): void
    {
        $this->install($features, $modules, $agents);
    }

    private function registerBuiltInAgents(): void
    {
        $this->agents = [
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
    }

    /**
     * @param list<string> $features
     */
    private function supportsSelectedFeatures(Agent $agent, array $features): bool
    {
        if ($features === []) {
            return true;
        }

        $contracts = [
            'guidelines' => SupportsGuidelines::class,
            'skills' => \Totoglu\Console\Boost\Contracts\SupportsSkills::class,
            'mcp' => SupportsMcp::class,
        ];

        foreach ($features as $feature) {
            if (isset($contracts[$feature]) && $agent instanceof $contracts[$feature]) {
                return true;
            }
        }

        return false;
    }
}

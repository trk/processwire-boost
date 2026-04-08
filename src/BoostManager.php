<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost;

use Totoglu\ProcessWire\Boost\Install\Agents\Agent;

final class BoostManager
{
    private string $targetDir;

    public function __construct(
        private readonly string $projectRoot
    ) {
        $this->targetDir = $this->projectRoot . '/.llms';
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

                // Priority chain for each resource type: boost/{type} > .llms/{type} > {type}
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
     * Resolve resource directory for a module — standard path: {module}/llms/{type}
     */
    private function resolveResourceDir(string $modulePath, string $resourceType): ?string
    {
        $path = $modulePath . '/llms/' . $resourceType;

        return is_dir($path) ? $path : null;
    }

    /**
     * Perform the installation based on choices
     * 
     * @param string[] $features Selected features (Guidelines, Skills, MCP)
     * @param string[] $modules Selected modules to aggregate from
     * @param Agent[] $agents Selected agent instances
     */
    public function install(array $features, array $modules, array $agents): void
    {
        if (!is_dir($this->targetDir)) {
            mkdir($this->targetDir, 0755, true);
        }

        // 1. Mandatory Map generation
        $this->generateMap($this->targetDir . '/map.json');

        // 2. Clear current context (only clear what will be repopulated)
        if (in_array('AI Guidelines', $features)) {
            // Guidelines are now compiled directly into AGENTS.md. Remove legacy directory.
            if (is_dir($this->targetDir . '/guidelines')) {
                $this->delTree($this->targetDir . '/guidelines');
            }
        }
        if (in_array('Agent Skills', $features)) {
            $this->clearDirectory($this->targetDir . '/skills');
        }

        // 3. Export Core Resources
        if (in_array('Agent Skills', $features)) {
            $this->exportCoreResources($this->targetDir . '/skills', 'skills');
        }

        // 4. Aggregate from selected modules
        $availableModules = $this->getDiscoverableModules();
        foreach ($modules as $moduleName) {
            if (isset($availableModules[$moduleName])) {
                $this->aggregateResources($moduleName, $availableModules[$moduleName], $features);
            }
        }

        // 5. Generate Agent specific files
        $this->generateAgentFiles($agents, $features, $modules);
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

        foreach (\ProcessWire\wire('fields') as $field) {
            $map['fields'][$field->name] = [
                'id' => $field->id,
                'type' => $field->type->className(),
                'label' => $field->label,
            ];
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

    private function exportCoreResources(string $target, string $type): void
    {
        $resourceDir = __DIR__ . '/../resources/boost/' . $type;
        if (is_dir($resourceDir)) {
            $this->copyDirectory($resourceDir, $target);
        }
    }

    /**
     * @param Agent[] $agents
     */
    private function generateAgentFiles(array $agents, array $features, array $modules): void
    {
        $instructionParts = [];

        $foundation = $this->renderFoundationRule();
        if ($foundation) {
            $instructionParts[] = "=== foundation rules ===\n\n" . $foundation;
        }

        // Dynamically load all core guidelines
        $guidelinesDirectories = [
            __DIR__ . '/../resources/boost/guidelines'
        ];
        
        $processedFiles = ['foundation.md']; // foundation is already processed above

        foreach ($guidelinesDirectories as $gDir) {
            if (is_dir($gDir)) {
                $files = scandir($gDir);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..' || in_array($file, $processedFiles)) continue;
                    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'md') continue;
                    
                    $filePath = $gDir . '/' . $file;
                    if (is_file($filePath)) {
                        $ruleName = str_replace(['-', '_'], ' ', pathinfo($file, PATHINFO_FILENAME));
                        $instructionParts[] = "=== {$ruleName} rules ===\n\n" . file_get_contents($filePath);
                        $processedFiles[] = $file;
                    }
                }
            }
        }

        // Aggregate module specific instructions
        if (!empty($modules)) {
            $availableModules = $this->getDiscoverableModules();
            foreach ($modules as $mName) {
                if (!isset($availableModules[$mName])) continue;
                
                $moduleInfo = $availableModules[$mName];
                $mContent = "";

                // Read from llms/guidelines/ directory
                if ($moduleInfo['guidelines_path'] && is_dir($moduleInfo['guidelines_path'])) {
                    foreach (scandir($moduleInfo['guidelines_path']) as $f) {
                        if ($f === '.' || $f === '..') continue;
                        if (strtolower(pathinfo($f, PATHINFO_EXTENSION)) !== 'md') continue;
                        $mContent .= file_get_contents($moduleInfo['guidelines_path'] . '/' . $f) . "\n\n";
                    }
                }

                // Fallback: read llms.txt at module root
                $llmsTxtPath = $moduleInfo['path'] . '/llms.txt';
                if (empty($mContent) && is_file($llmsTxtPath)) {
                    $mContent = file_get_contents($llmsTxtPath);
                }

                if ($mContent) {
                    $instructionParts[] = "=== module rule: {$mName} ===\n\n" . trim($mContent);
                }
            }
        }

        $fullInstructions = implode("\n\n", $instructionParts);
        $boostBlock = "<processwire-boost-guidelines>\n\n{$fullInstructions}\n\n</processwire-boost-guidelines>";

        // Generate unified AGENTS.md (always created as universal fallback)
        $agentsPath = $this->projectRoot . '/AGENTS.md';
        $this->writeBoostBlock($agentsPath, $boostBlock, "# Universal AI Agent Instructions\n\nGenerated for ProcessWire AI Ecosystem.\n\n");

        // Track which guidelines files have been written to avoid duplicates
        $writtenGuidelines = ['AGENTS.md'];

        // Generate agent-specific guidelines and deploy skills
        foreach ($agents as $agent) {
            $guidelinesFile = $agent->guidelinesPath();

            // Write guidelines (skip if same file already written, e.g. multiple agents use AGENTS.md)
            if (!in_array($guidelinesFile, $writtenGuidelines, true)) {
                $guidelinesFullPath = $this->projectRoot . '/' . $guidelinesFile;
                $agentHeader = "# {$agent->displayName()} Instructions\n\nGenerated for ProcessWire AI Ecosystem.\n\n";
                $this->writeBoostBlock($guidelinesFullPath, $boostBlock, $agentHeader);
                $writtenGuidelines[] = $guidelinesFile;
            }

            // Deploy skills to agent-specific directory
            if (in_array('Agent Skills', $features)) {
                $skillsTarget = $this->projectRoot . '/' . $agent->skillsPath();
                $this->deploySkillsToAgent($agent, $skillsTarget);
            }
        }
    }

    /**
     * Deploy skills from the central staging area to an agent-specific directory.
     */
    private function deploySkillsToAgent(Agent $agent, string $targetDir): void
    {
        $sourceDir = $this->targetDir . '/skills';
        if (!is_dir($sourceDir)) {
            return;
        }

        foreach (new \DirectoryIterator($sourceDir) as $skillDir) {
            if ($skillDir->isDot() || !$skillDir->isDir()) {
                continue;
            }

            $skillFile = $skillDir->getPathname() . '/SKILL.md';
            if (!file_exists($skillFile)) {
                continue;
            }

            $agent->exportSkill($skillDir->getFilename(), $skillFile, $targetDir);
        }
    }

    /**
     * Write boost guidelines into a file using a merge strategy.
     * 
     * - If file exists and contains <processwire-boost-guidelines> tags:
     *   only replace content between tags, preserving everything else.
     * - If file exists but has no tags: append tags after last line.
     * - If file does not exist: create with default header + tags.
     */
    private function writeBoostBlock(string $filePath, string $boostBlock, string $defaultHeader): void
    {
        if (file_exists($filePath)) {
            $existing = file_get_contents($filePath);

            if (str_contains($existing, '<processwire-boost-guidelines>') && str_contains($existing, '</processwire-boost-guidelines>')) {
                // Replace only the boost block, preserve everything else
                $pattern = '/<processwire-boost-guidelines>.*?<\/processwire-boost-guidelines>/s';
                $updated = preg_replace($pattern, $boostBlock, $existing, 1);
                file_put_contents($filePath, $updated);
            } else {
                // File exists but no boost tags — append after last line
                $existing = rtrim($existing) . "\n\n" . $boostBlock . "\n";
                file_put_contents($filePath, $existing);
            }
        } else {
            // File does not exist — create from scratch
            $content = $defaultHeader . $boostBlock . "\n";
            file_put_contents($filePath, $content);
        }
    }


    private function renderFoundationRule(): ?string
    {
        $candidates = [
            __DIR__ . '/../resources/boost/guidelines/foundation.md',
        ];

        $templatePath = null;
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $templatePath = $candidate;
                break;
            }
        }

        if ($templatePath === null) {
            return null;
        }

        $content = file_get_contents($templatePath);

        // Dynamic substitutions
        $replacements = [
            '{{ PHP_VERSION }}' => PHP_VERSION,
            '{{ PW_VERSION }}' => \ProcessWire\wire('config')->version,
        ];

        // Roster generation
        $roster = "- php - " . PHP_VERSION . "\n";
        $roster .= "- processwire/core - v" . \ProcessWire\wire('config')->version . "\n\n";
        $roster .= "> [!TIP]\n";
        $roster .= "> This system contains many other installed modules. You MUST use the `pw_module_list` MCP tool to discover installed ProcessWire modules before assuming existence of dependencies.\n";

        $replacements['{{ ROSTER }}'] = $roster;

        // Skills Menu
        $skillsMenu = "";
        $skillsDir = $this->targetDir . '/skills';
        if (is_dir($skillsDir)) {
            foreach (new \DirectoryIterator($skillsDir) as $file) {
                if ($file->isDot() || !$file->isDir()) continue;
                $skillFile = $file->getPathname() . '/SKILL.md';
                if (file_exists($skillFile)) {
                    $skillContent = file_get_contents($skillFile);
                    // Simple regex to grab description from frontmatter or first paragraph
                    if (preg_match('/description:\s*(.*)/', $skillContent, $matches)) {
                        $skillsMenu .= "- `{$file->getFilename()}` — {$matches[1]}\n";
                    }
                }
            }
        }
        $replacements['{{ SKILLS_MENU }}'] = $skillsMenu;

        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        return $content;
    }

    private function aggregateResources(string $moduleName, array $moduleInfo, array $features): void
    {
        if (in_array('Agent Skills', $features) && $moduleInfo['skills_path']) {
            $this->copyDirectory($moduleInfo['skills_path'], $this->targetDir . '/skills');
        }
    }

    private function copyDirectory(string $source, string $target): void
    {
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $files = scandir($source);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $srcFile = $source . '/' . $file;
            $dstFile = $target . '/' . $file;

            if (is_dir($srcFile)) {
                $this->copyDirectory($srcFile, $dstFile);
            } else {
                copy($srcFile, $dstFile);
            }
        }
    }

    private function clearDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
        }
    }

    private function delTree(string $dir): bool
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "{$dir}/{$file}";
            is_dir($path) ? $this->delTree($path) : unlink($path);
        }
        return rmdir($dir);
    }

    public function uninstallFeature(string $feature): void
    {
        if (!is_dir($this->targetDir)) {
            return;
        }

        switch ($feature) {
            case 'AI Guidelines':
                if (is_dir($this->targetDir . '/guidelines')) {
                    $this->delTree($this->targetDir . '/guidelines');
                }
                break;
            case 'Agent Skills':
                $this->clearDirectory($this->targetDir . '/skills');
                break;
        }
    }

    public function sync(array $features, array $modules, array $agents): void
    {
        $this->install($features, $modules, $agents);
    }
}

<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost;

final class BoostManager
{
    private string $targetDir;

    public function __construct(
        private readonly string $projectRoot
    ) {
        $this->targetDir = $this->projectRoot . '/.ai';
    }

    /**
     * Get all modules that have boost/ directory
     * 
     * @return array<string, array{path: string, has_guidelines: bool, has_skills: bool, has_blueprints: bool}>
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

                $boostPath = $file->getPathname() . '/boost';
                if (is_dir($boostPath)) {
                    $discoveries[$file->getFilename()] = [
                        'path' => $boostPath,
                        'has_guidelines' => is_dir($boostPath . '/guidelines') || is_dir($boostPath),
                        'has_skills' => is_dir($boostPath . '/skills'),
                        'has_blueprints' => is_dir($boostPath . '/blueprints'),
                    ];
                }
            }
        }

        // Special site/boost/ check
        $siteBoostPath = $this->projectRoot . '/site/boost';
        if (is_dir($siteBoostPath)) {
            $discoveries['site-overrides'] = [
                'path' => $siteBoostPath,
                'has_guidelines' => is_dir($siteBoostPath . '/guidelines'),
                'has_skills' => is_dir($siteBoostPath . '/skills'),
                'has_blueprints' => is_dir($siteBoostPath . '/blueprints'),
            ];
        }

        return $discoveries;
    }

    /**
     * Perform the installation based on choices
     * 
     * @param string[] $features Selected features (Guidelines, Skills, Blueprints, MCP)
     * @param string[] $modules Selected modules to aggregate from
     * @param string[] $agents Selected agents (Cursor, Gemini CLI, etc)
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
            $this->clearDirectory($this->targetDir . '/guidelines');
        }
        if (in_array('Blueprints', $features)) {
            $this->clearDirectory($this->targetDir . '/blueprints');
        }
        if (in_array('Agent Skills', $features)) {
            $this->clearDirectory($this->targetDir . '/skills');
        }

        // 3. Export Core Resources
        if (in_array('AI Guidelines', $features)) {
            $this->exportCoreResources($this->targetDir . '/guidelines', 'guidelines');
        }
        if (in_array('Blueprints', $features)) {
            $this->exportCoreResources($this->targetDir . '/blueprints', 'blueprints');
        }
        if (in_array('Agent Skills', $features)) {
            $this->exportCoreResources($this->targetDir . '/skills', 'skills');
        }

        // 4. Aggregate from selected modules
        $availableModules = $this->getDiscoverableModules();
        foreach ($modules as $moduleName) {
            if (isset($availableModules[$moduleName])) {
                $this->aggregateResources($availableModules[$moduleName]['path'], $features);
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
                'installed' => true
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

    private function generateAgentFiles(array $agents, array $features, array $modules): void
    {
        $instructionParts = [];

        $foundation = $this->renderFoundationRule();
        if ($foundation) {
            $instructionParts[] = "=== foundation rules ===\n\n" . $foundation;
        }

        $boostPath = $this->targetDir . '/guidelines/boost.md';
        if (file_exists($boostPath)) {
            $instructionParts[] = "=== boost rules ===\n\n" . file_get_contents($boostPath);
        } else {
            $fallbackBoost = __DIR__ . '/resources/boost/guidelines/boost.md';
            if (!file_exists($fallbackBoost)) {
                $fallbackBoost = __DIR__ . '/../resources/boost/guidelines/boost.md';
            }
            if (file_exists($fallbackBoost)) {
                $instructionParts[] = "=== boost rules ===\n\n" . file_get_contents($fallbackBoost);
            }
        }

        $phpPath = $this->targetDir . '/guidelines/php.md';
        if (file_exists($phpPath)) {
            $instructionParts[] = "=== php rules ===\n\n" . file_get_contents($phpPath);
        } else {
            $fallbackPhp = __DIR__ . '/resources/boost/guidelines/php.md';
            if (!file_exists($fallbackPhp)) {
                $fallbackPhp = __DIR__ . '/../resources/boost/guidelines/php.md';
            }
            if (file_exists($fallbackPhp)) {
                $instructionParts[] = "=== php rules ===\n\n" . file_get_contents($fallbackPhp);
            }
        }

        $pwCorePath = $this->targetDir . '/guidelines/pw_core.md';
        if (file_exists($pwCorePath)) {
            $instructionParts[] = "=== processwire rules ===\n\n" . file_get_contents($pwCorePath);
        }

        // Aggregate module specific instructions
        if (!empty($modules)) {
            $availableModules = $this->getDiscoverableModules();
            foreach ($modules as $mName) {
                if (isset($availableModules[$mName])) {
                    // Check if guidelines exist in .ai/
                    // Note: aggregateResources already copied them
                    $mGuidelinesDir = $this->targetDir . '/guidelines/' . $mName;
                    if (is_dir($mGuidelinesDir)) {
                        $mContent = "";
                        foreach (scandir($mGuidelinesDir) as $f) {
                            if ($f === '.' || $f === '..') continue;
                            $mContent .= file_get_contents($mGuidelinesDir . '/' . $f) . "\n\n";
                        }
                        if ($mContent) {
                            $instructionParts[] = "=== module rule: {$mName} ===\n\n" . trim($mContent);
                        }
                    }
                }
            }
        }

        $fullInstructions = implode("\n\n", $instructionParts);

        foreach ($agents as $agent) {
            $filename = strtoupper(str_replace(' ', '_', $agent)) . '.md';
            if ($agent === 'Cursor') $filename = 'CURSOR.md';
            if ($agent === 'Gemini CLI') $filename = 'GEMINI.md';
            if ($agent === 'Claude Code') $filename = 'CLAUDE.md';

            $header = "# {$agent} Instructions\n\nGenerated for ProcessWire AI Ecosystem.\n";
            $context = "## Project AI Context\n"
                . "- Primary guidance is embedded below. When you need more, read local files under .ai/.\n"
                . "- Guidelines: .ai/guidelines (foundation, boost, php, pw_core.md)\n"
                . "- Blueprints: .ai/blueprints/pw_core/*.json (class/method summaries with @since)\n"
                . "- Skills: .ai/skills/pw_core/*/SKILL.md (task playbooks)\n"
                . "- If your client supports MCP, use the ProcessWire MCP server tools to query data.\n";
            $content = "{$header}\n{$context}\n<processwire-boost-guidelines>\n\n{$fullInstructions}\n\n</processwire-boost-guidelines>\n";

            file_put_contents($this->projectRoot . '/' . $filename, $content);
        }
    }

    private function renderFoundationRule(): ?string
    {
        $templatePath = $this->targetDir . '/guidelines/foundation.md';
        if (!file_exists($templatePath)) {
            $templatePath = __DIR__ . '/resources/boost/guidelines/foundation.md';
            if (!file_exists($templatePath)) {
                $templatePath = __DIR__ . '/../resources/boost/guidelines/foundation.md';
                if (!file_exists($templatePath)) {
                    return null;
                }
            }
        }

        $content = file_get_contents($templatePath);

        // Dynamic substitutions
        $replacements = [
            '{{ PHP_VERSION }}' => PHP_VERSION,
            '{{ PW_VERSION }}' => \ProcessWire\wire('config')->version,
        ];

        // Roster generation
        $roster = "- php - " . PHP_VERSION . "\n";
        $roster .= "- processwire/core - v" . \ProcessWire\wire('config')->version . "\n";

        $m = \ProcessWire\wire('modules');
        foreach ($m->getAll() as $name => $mod) {
            $info = $m->getModuleInfo($name);
            $version = $info['versionStr'];

            // Fix: If versionStr is 0.0.0, use the formatted integer version
            if ($version === '0.0.0' || empty($version)) {
                $version = $m->formatVersion($info['version']);
            }

            $roster .= "- " . $name . " - v" . $version . "\n";
        }

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

    private function aggregateResources(string $sourcePath, array $features): void
    {
        // Aggregate Guidelines
        if (in_array('AI Guidelines', $features)) {
            $guidelinesSource = $sourcePath . '/guidelines';
            if (!is_dir($guidelinesSource)) $guidelinesSource = $sourcePath; // check root of boost dir

            if (is_dir($guidelinesSource)) {
                $targetGuidelines = $this->targetDir . '/guidelines/' . basename(dirname($sourcePath));
                $this->copyDirectory($guidelinesSource, $targetGuidelines);
            }
        }

        // Aggregate Blueprints
        if (in_array('Blueprints', $features)) {
            $blueprintsSource = $sourcePath . '/blueprints';
            if (is_dir($blueprintsSource)) {
                $targetBlueprints = $this->targetDir . '/blueprints/' . basename(dirname($sourcePath));
                $this->copyDirectory($blueprintsSource, $targetBlueprints);
            }
        }

        // Aggregate Skills
        if (in_array('Agent Skills', $features)) {
            $skillsSource = $sourcePath . '/skills';
            if (is_dir($skillsSource)) {
                $targetSkills = $this->targetDir . '/skills';
                $this->copyDirectory($skillsSource, $targetSkills);
            }
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

    private function delTree($dir)
    {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }
}

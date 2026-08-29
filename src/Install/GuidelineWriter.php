<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install;

use Totoglu\Console\Boost\Contracts\SupportsGuidelines;

final class GuidelineWriter
{
    /**
     * @param array<string, array{path: string, guidelines_path: ?string, skills_path: ?string, has_guidelines: bool, has_skills: bool}> $discoverableModules
     */
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $targetDir,
        private readonly array $discoverableModules
    ) {
    }

    /**
     * @param list<SupportsGuidelines> $agents
     * @param list<string> $modules
     */
    public function write(array $agents, array $modules): void
    {
        $instructionParts = [];

        $foundation = $this->renderFoundationRule();
        if ($foundation !== null) {
            $instructionParts[] = "=== foundation rules ===\n\n" . $foundation;
        }

        foreach ($this->loadCoreGuidelines() as $name => $content) {
            $instructionParts[] = "=== {$name} rules ===\n\n" . $content;
        }

        foreach ($this->buildModuleInstructions($modules) as $moduleInstruction) {
            $instructionParts[] = $moduleInstruction;
        }

        $fullInstructions = implode("\n\n", $instructionParts);
        $boostBlock = "<processwire-boost-guidelines>\n\n{$fullInstructions}\n\n</processwire-boost-guidelines>";

        $agentsPath = $this->projectRoot . '/AGENTS.md';
        $this->writeBoostBlock(
            $agentsPath,
            $boostBlock,
            "# Universal AI Agent Instructions\n\nGenerated for ProcessWire AI Ecosystem.\n\n"
        );

        $writtenGuidelines = ['AGENTS.md'];

        foreach ($agents as $agent) {
            $guidelinesFile = $agent->guidelinesPath();

            if (in_array($guidelinesFile, $writtenGuidelines, true)) {
                continue;
            }

            $guidelinesFullPath = $this->projectRoot . '/' . $guidelinesFile;
            $directory = dirname($guidelinesFullPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            $stub = "# {$agent->displayName()} Instructions\n\nGenerated for ProcessWire AI Ecosystem.\n\n";
            $stub .= "> [!IMPORTANT]\n";
            $stub .= "> Please strictly follow and read the primary `AGENTS.md` file located in the root directory for all system instructions, architecture rules, and processwire-boost guidelines.\n";

            $content = $agent->transformGuidelines($stub);
            if ($agent->frontmatter()) {
                $content = "---\ndescription: {$agent->displayName()} Instructions\n---\n\n" . $content;
            }

            file_put_contents($guidelinesFullPath, $content);
            $writtenGuidelines[] = $guidelinesFile;
        }
    }

    /**
     * @param list<SupportsGuidelines> $agents
     */
    public function remove(array $agents): void
    {
        $agentsPath = $this->projectRoot . '/AGENTS.md';
        if (is_file($agentsPath)) {
            $content = (string) file_get_contents($agentsPath);
            $updated = preg_replace('/\n*<processwire-boost-guidelines>.*?<\/processwire-boost-guidelines>\n*/s', "\n", $content);
            $updated = is_string($updated) ? trim($updated) : trim($content);

            if ($updated === '') {
                unlink($agentsPath);
            } else {
                file_put_contents($agentsPath, $updated . "\n");
            }
        }

        $removed = [];
        foreach ($agents as $agent) {
            $path = $agent->guidelinesPath();
            if ($path === 'AGENTS.md' || in_array($path, $removed, true)) {
                continue;
            }

            $fullPath = $this->projectRoot . '/' . $path;
            if (is_file($fullPath)) {
                unlink($fullPath);
            }
            $removed[] = $path;
        }
    }

    /**
     * @return array<string, string>
     */
    private function loadCoreGuidelines(): array
    {
        $guidelinesDir = dirname(__DIR__, 2) . '/resources/boost/guidelines';
        if (!is_dir($guidelinesDir)) {
            return [];
        }

        $guidelines = [];
        foreach (scandir($guidelinesDir) ?: [] as $file) {
            if ($file === '.' || $file === '..' || $file === 'foundation.md') {
                continue;
            }

            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'md') {
                continue;
            }

            $filePath = $guidelinesDir . '/' . $file;
            if (!is_file($filePath)) {
                continue;
            }

            $ruleName = str_replace(['-', '_'], ' ', pathinfo($file, PATHINFO_FILENAME));
            $guidelines[$ruleName] = (string) file_get_contents($filePath);
        }

        return $guidelines;
    }

    /**
     * @param list<string> $modules
     * @return list<string>
     */
    private function buildModuleInstructions(array $modules): array
    {
        if ($modules === []) {
            return [];
        }

        $instructions = [];
        $moduleDirectories = [];

        foreach ($modules as $moduleName) {
            if (!isset($this->discoverableModules[$moduleName])) {
                continue;
            }

            $module = $this->discoverableModules[$moduleName];
            $moduleContent = '';

            if (is_string($module['guidelines_path']) && is_dir($module['guidelines_path'])) {
                foreach (scandir($module['guidelines_path']) ?: [] as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }

                    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'md') {
                        continue;
                    }

                    $moduleContent .= (string) file_get_contents($module['guidelines_path'] . '/' . $file) . "\n\n";
                }
            }

            if ($moduleContent !== '') {
                $instructions[] = "=== module rule: {$moduleName} ===\n\n" . trim($moduleContent);
            }

            $agentsPath = $module['path'] . '/AGENTS.md';
            if (!is_file($agentsPath)) {
                continue;
            }

            $agentContent = (string) file_get_contents($agentsPath);
            $description = "Context and guidelines for {$moduleName}.";

            if (preg_match('/^---\s*\ndescription:\s*(.*?)\n---/s', $agentContent, $matches)) {
                $description = trim($matches[1]);
            } elseif (preg_match('/description:\s*(.*)/', $agentContent, $matches)) {
                $description = trim($matches[1]);
            }

            $relPath = str_replace($this->projectRoot . '/', '', $agentsPath);
            $moduleDirectories[] = "- **{$moduleName}**: {$description}\n  > Please use `view_file` on `{$relPath}` to read the full context when working on this module.";
        }

        if ($moduleDirectories !== []) {
            $instructions[] = "=== Module Context Index ===\n\nThe following modules provide extensive architectural guidelines. **DO NOT GUESS** their API. Use the `view_file` tool to read the corresponding path when your task explicitly involves them:\n\n" . implode("\n\n", $moduleDirectories);
        }

        return $instructions;
    }

    private function renderFoundationRule(): ?string
    {
        $templatePath = dirname(__DIR__, 2) . '/resources/boost/guidelines/foundation.md';
        if (!is_file($templatePath)) {
            return null;
        }

        $content = (string) file_get_contents($templatePath);
        $replacements = [
            '{{ PHP_VERSION }}' => PHP_VERSION,
            '{{ PW_VERSION }}' => (string) \ProcessWire\wire('config')->version,
        ];

        $roster = "- php - " . PHP_VERSION . "\n";
        $roster .= "- processwire/core - v" . \ProcessWire\wire('config')->version . "\n\n";
        $roster .= "> [!TIP]\n";
        $roster .= "> This system contains many other installed modules. You MUST use the `pw_module_list` MCP tool to discover installed ProcessWire modules before assuming existence of dependencies.\n";
        $replacements['{{ ROSTER }}'] = $roster;

        $skillsMenu = '';
        $skillsDir = $this->targetDir . '/skills';
        if (is_dir($skillsDir)) {
            foreach (new \DirectoryIterator($skillsDir) as $file) {
                if ($file->isDot() || !$file->isDir()) {
                    continue;
                }

                $skillFile = $file->getPathname() . '/SKILL.md';
                if (!is_file($skillFile)) {
                    continue;
                }

                $skillContent = (string) file_get_contents($skillFile);
                if (preg_match('/description:\s*(.*)/', $skillContent, $matches)) {
                    $skillsMenu .= "- `{$file->getFilename()}` — {$matches[1]}\n";
                }
            }
        }
        $replacements['{{ SKILLS_MENU }}'] = $skillsMenu;

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    private function writeBoostBlock(string $filePath, string $boostBlock, string $defaultHeader): void
    {
        $directory = dirname($filePath);
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

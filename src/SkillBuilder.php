<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost;

use Totoglu\ProcessWire\Boost\Install\Agents\Agent;

final class SkillBuilder
{
    public function __construct(private readonly string $projectRoot) {}

    public function buildFromSources(?array $select = null): array
    {
        $srcDir = $this->projectRoot . '/vendor/processwire/boost/resources/builder/skills';
        $fallbackDir = __DIR__ . '/../resources/builder/skills';
        $base = is_dir($srcDir) ? $srcDir : $fallbackDir;
        $files = glob($base . '/*.md') ?: [];
        $outDir = $this->projectRoot . '/.ai/skills/pw_core';
        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }
        $written = [];
        foreach ($files as $f) {
            $name = pathinfo($f, PATHINFO_FILENAME);
            if ($select && !in_array($name, $select, true)) continue;
            $sd = $outDir . '/' . $name;
            if (!is_dir($sd)) mkdir($sd, 0755, true);
            $target = $sd . '/SKILL.md';
            $content = rtrim((string)file_get_contents($f)) . "\n";
            file_put_contents($target, $content);
            $written[] = $target;
        }
        return $written;
    }

    public function exportForAgent(?Agent $agent = null, ?array $select = null, ?string $targetDir = null): array
    {
        $srcDir = $this->projectRoot . '/vendor/processwire/boost/resources/builder/skills';
        $fallbackDir = __DIR__ . '/../resources/builder/skills';
        $base = is_dir($srcDir) ? $srcDir : $fallbackDir;
        $files = glob($base . '/*.md') ?: [];

        if ($agent) {
            $defaultDir = match ($agent->name()) {
                'trae' => $this->projectRoot . '/.trae/skills',
                'opencode' => $this->projectRoot . '/.opencode/skills',
                default => $this->projectRoot . '/.ai/skills/' . $agent->name(),
            };
        } else {
            $defaultDir = $this->projectRoot . '/.ai/skills/pw_core';
        }

        $outDir = $targetDir ?: $defaultDir;
        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $written = [];
        foreach ($files as $f) {
            $name = pathinfo($f, PATHINFO_FILENAME);
            if ($select && !in_array($name, $select, true)) continue;
            if ($agent) {
                $written[] = $agent->exportSkill($name, $f, $outDir);
            } else {
                $sd = $outDir . '/' . $name;
                if (!is_dir($sd)) mkdir($sd, 0755, true);
                $target = $sd . '/SKILL.md';
                copy($f, $target);
                $written[] = $target;
            }
        }
        return $written;
    }

    public function exportForTrae(?array $select = null, ?string $targetDir = null): array
    {
        return $this->exportForAgent(null, $select, $targetDir ?: $this->projectRoot . '/.trae/skills');
    }

    public function buildSkillsFromGroups(array $index, ?string $targetDir = null): array
    {
        $outDir = $targetDir ?: ($this->projectRoot . '/.ai/skills/pw_core');
        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $groupedMethods = [];
        foreach ($index as $fqcn => $meta) {
            foreach ($meta['methods'] ?? [] as $name => $m) {
                $group = $m['pw_group'] ?? 'common';
                if (!isset($groupedMethods[$group])) {
                    $groupedMethods[$group] = [];
                }
                $groupedMethods[$group][] = [
                    'class' => $fqcn,
                    'method' => $name,
                    'summary' => $m['summary'] ?? '',
                    'since' => $m['since'] ?? null,
                    'deprecated' => $m['deprecated'] ?? false,
                ];
            }
        }

        $skillMap = [
            'retrieve' => 'Page Retrieval & Finding',
            'save' => 'Page Saving & Persistence',
            'add' => 'Page Creation & Addition',
            'trash' => 'Page Trash & Restoration',
            'delete' => 'Page Deletion',
            'move' => 'Page Moving & Sorting',
            'common' => 'Common Operations',
            'advanced' => 'Advanced Operations',
            'cache' => 'Caching Operations',
            'helpers' => 'Helper Methods',
            'hooks' => 'Hookable Methods',
        ];

        $written = [];
        foreach ($groupedMethods as $group => $methods) {
            $title = $skillMap[$group] ?? ucfirst($group) . ' Operations';
            $content = $this->generateSkillContent($group, $title, $methods);

            $skillDir = $outDir . '/' . $group;
            if (!is_dir($skillDir)) {
                mkdir($skillDir, 0755, true);
            }
            $target = $skillDir . '/SKILL.md';
            file_put_contents($target, $content);
            $written[] = $target;
        }

        return $written;
    }

    private function generateSkillContent(string $group, string $title, array $methods): string
    {
        $lines = [];
        $lines[] = '# ' . $title;
        $lines[] = '';
        $lines[] = 'description: ' . strtolower($title) . ' using ProcessWire Core API';
        $lines[] = '';

        $lines[] = '## Overview';
        $lines[] = '';
        $lines[] = 'This skill covers ' . strtolower($title) . ' operations in ProcessWire.';
        $lines[] = 'Total methods: ' . count($methods);
        $lines[] = '';

        $lines[] = '## Methods';
        $lines[] = '';

        foreach ($methods as $m) {
            $version = $m['since'] ? ' (since ' . $m['since'] . ')' : '';
            $deprecation = $m['deprecated'] ? ' ⚠️ DEPRECATED' : '';
            $lines[] = '### ' . $m['class'] . '::' . $m['method'] . $version . $deprecation;
            $lines[] = '';
            if ($m['summary']) {
                $lines[] = $m['summary'];
                $lines[] = '';
            }
            $lines[] = '```php';
            $lines[] = '$' . $this->variableFromClass($m['class']) . '->' . $m['method'] . '()';
            $lines[] = '```';
            $lines[] = '';
        }

        $lines[] = '## Usage Examples';
        $lines[] = '';

        $examples = $this->generateExamplesForGroup($group);
        $lines = array_merge($lines, $examples);

        return implode("\n", $lines);
    }

    private function variableFromClass(string $fqcn): string
    {
        $map = [
            'ProcessWire\Pages' => 'pages',
            'ProcessWire\Page' => 'page',
            'ProcessWire\Users' => 'users',
            'ProcessWire\User' => 'user',
            'ProcessWire\Templates' => 'templates',
            'ProcessWire\Template' => 'template',
            'ProcessWire\Fields' => 'fields',
            'ProcessWire\Field' => 'field',
            'ProcessWire\Modules' => 'modules',
            'ProcessWire\Module' => 'module',
            'ProcessWire\Sanitizer' => 'sanitizer',
            'ProcessWire\Session' => 'session',
            'ProcessWire\Config' => 'config',
            'ProcessWire\Wire' => 'wire',
        ];
        return $map[$fqcn] ?? 'api';
    }

    private function generateExamplesForGroup(string $group): array
    {
        $examples = [
            'retrieve' => [
                '## Example: Find pages',
                '```php',
                '// Find all published pages',
                '$pages = $pages->find("template=blog, status=published");',
                '',
                '// Find single page by ID',
                '$page = $pages->get(1234);',
                '',
                '// Find with sorting',
                '$pages = $pages->find("template=blog, sort=-created");',
                '```',
            ],
            'save' => [
                '## Example: Save page',
                '```php',
                '// Save entire page',
                '$page->title = "New Title";',
                '$page->save();',
                '',
                '// Save single field (more efficient)',
                '$pages->saveField($page, "title");',
                '```',
            ],
            'add' => [
                '## Example: Add new page',
                '```php',
                '// Create new page',
                '$newPage = $pages->add("blog-post", $parentPage, "my-post");',
                '$newPage->title = "My Post";',
                '$newPage->save();',
                '```',
            ],
            'trash' => [
                '## Example: Trash page',
                '```php',
                '// Move to trash',
                '$pages->trash($page);',
                '',
                '// Restore from trash',
                '$pages->restore($page);',
                '```',
            ],
            'delete' => [
                '## Example: Delete page',
                '```php',
                '// Delete single page',
                '$pages->delete($page);',
                '',
                '// Delete with children (recursive)',
                '$pages->delete($page, true);',
                '```',
            ],
        ];

        return $examples[$group] ?? [];
    }
}

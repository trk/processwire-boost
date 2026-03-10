<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost;

final class MasterArchitectNotes
{
    public function __construct(
        private readonly string $projectRoot,
        private array $index = [],
        private array $discoveredTags = [],
        private array $synthesizedMethods = [],
        private array $classRelationships = []
    ) {}

    public function setData(array $index, array $discoveredTags, array $synthesizedMethods, array $classRelationships): self
    {
        $this->index = $index;
        $this->discoveredTags = $discoveredTags;
        $this->synthesizedMethods = $synthesizedMethods;
        $this->classRelationships = $classRelationships;
        return $this;
    }

    public function generate(): string
    {
        $sections = [];

        $sections[] = '# Master Architect Notes';
        $sections[] = 'Generated: ' . date('Y-m-d H:i:s');
        $sections[] = '';
        $sections[] = '> This document contains the semantic mapping and architectural insights derived from ProcessWire Core source code analysis.';
        $sections[] = '';

        $sections = array_merge($sections, $this->generateSemanticMapping());
        $sections = array_merge($sections, $this->generateTagDiscovery());
        $sections = array_merge($sections, $this->generateSynthesisStrategy());
        $sections = array_merge($sections, $this->generateClassOverview());

        return implode("\n", $sections);
    }

    private function generateSemanticMapping(): array
    {
        $sections = [];
        $sections[] = '## Semantic Mapping';
        $sections[] = '';
        $sections[] = '### Cross-Class Relationships';
        $sections[] = '';

        if (empty($this->classRelationships)) {
            $sections[] = '_No cross-class relationships detected._';
        } else {
            foreach ($this->classRelationships as $fqcn => $related) {
                $shortName = $this->shortName($fqcn);
                $relatedShort = array_map([$this, 'shortName'], $related);
                $sections[] = "- **{$shortName}** → " . implode(', ', $relatedShort);
            }
        }

        $sections[] = '';
        $sections[] = '### Return Type Chains';
        $sections[] = '';

        $typeChains = [];
        foreach ($this->index as $fqcn => $meta) {
            foreach ($meta['methods'] ?? [] as $name => $m) {
                $returnClass = $m['return_class'] ?? null;
                if ($returnClass) {
                    $key = $this->shortName($fqcn) . '::' . $name;
                    $typeChains[$key] = $this->shortName($returnClass);
                }
            }
        }

        if (empty($typeChains)) {
            $sections[] = '_No return type chains detected._';
        } else {
            foreach (array_slice($typeChains, 0, 20) as $method => $return) {
                $sections[] = "- `{$method}()` → **{$return}**";
            }
            if (count($typeChains) > 20) {
                $sections[] = '- ... and ' . (count($typeChains) - 20) . ' more';
            }
        }

        $sections[] = '';
        return $sections;
    }

    private function generateTagDiscovery(): array
    {
        $sections = [];
        $sections[] = '## Tag Discovery Summary';
        $sections[] = '';

        if (empty($this->discoveredTags)) {
            $sections[] = '_No ProcessWire-specific tags detected._';
        } else {
            arsort($this->discoveredTags);
            $sections[] = '| Tag | Occurrences |';
            $sections[] = '|-----|-------------|';
            foreach ($this->discoveredTags as $tag => $count) {
                $sections[] = "| `{$tag}` | {$count} |";
            }
        }

        $sections[] = '';
        return $sections;
    }

    private function generateSynthesisStrategy(): array
    {
        $sections = [];
        $sections[] = '## Synthesis Strategy';
        $sections[] = '';

        if (empty($this->synthesizedMethods)) {
            $sections[] = '_No undocumented methods found - all methods have native documentation._';
        } else {
            $sections[] = 'The following methods lacked native documentation. [Synthesized] descriptions were generated based on method signature analysis:';
            $sections[] = '';
            $sections[] = '| Method | Summary | Params | Return |';
            $sections[] = '|--------|---------|--------|--------|';
            foreach ($this->synthesizedMethods as $m) {
                $summary = substr($m['summary'], 0, 40) . (strlen($m['summary']) > 40 ? '...' : '');
                $params = (string)$m['params'];
                $return = $m['return'] ?? 'mixed';
                $sections[] = "| `{$m['method']}()` | {$summary} | {$params} | {$return} |";
            }
        }

        $sections[] = '';
        return $sections;
    }

    private function generateClassOverview(): array
    {
        $sections = [];
        $sections[] = '## Class Overview';
        $sections[] = '';

        $classCount = count($this->index);
        $methodCounts = [];
        $internalCounts = [];

        foreach ($this->index as $fqcn => $meta) {
            $methodCounts[$fqcn] = count($meta['methods'] ?? []);
            $internalCounts[$fqcn] = count(array_filter($meta['methods'] ?? [], fn($m) => ($m['pw_internal'] ?? false) || ($m['synthesized'] ?? false)));
        }

        arsort($methodCounts);

        $sections[] = "Total Classes Analyzed: **{$classCount}**";
        $sections[] = '';
        $sections[] = '### Classes by Method Count';
        $sections[] = '';
        $sections[] = '| Class | Methods | Internal/Synthesized |';
        $sections[] = '|-------|---------|---------------------|';

        foreach (array_slice($methodCounts, 0, 15) as $fqcn => $count) {
            $shortName = $this->shortName($fqcn);
            $internal = $internalCounts[$fqcn] ?? 0;
            $sections[] = "| {$shortName} | {$count} | {$internal} |";
        }

        $sections[] = '';
        return $sections;
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }
}

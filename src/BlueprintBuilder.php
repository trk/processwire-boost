<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost;

final class BlueprintBuilder
{
    public function __construct(private readonly string $projectRoot) {}

    public function build(array $index, string $targetDir): array
    {
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $written = [];
        foreach ($index as $fqcn => $meta) {
            $name = $this->basename($fqcn);
            $bp = [
                'id' => $fqcn,
                'name' => $name,
                'kind' => 'class',
                'summary' => $meta['summary'] ?? '',
                'className' => $fqcn,
                'sourcePath' => 'wire/core/' . ($meta['file'] ?? ''),
                'since' => $meta['since'] ?? null,
                'methods' => $this->formatMethods($meta['methods'] ?? []),
            ];
            $path = rtrim($targetDir, '/') . '/' . $name . '.json';
            file_put_contents($path, json_encode($bp, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $written[] = $path;
        }
        $this->mergeSeedBlueprints($targetDir);
        return $written;
    }

    private function basename(string $fqcn): string
    {
        $p = strrpos($fqcn, '\\');
        return $p === false ? $fqcn : substr($fqcn, $p + 1);
    }

    private function formatMethods(array $methods): array
    {
        $out = [];
        foreach ($methods as $name => $m) {
            $out[] = [
                'name' => $name,
                'description' => $m['summary'] ?? '',
                'params' => $m['params'] ?? [],
                'return' => $m['return'] ?? null,
                'deprecated' => (bool)($m['deprecated'] ?? false),
                'since' => $m['since'] ?? null,
            ];
        }
        return $out;
    }

    private function mergeSeedBlueprints(string $targetDir): void
    {
        $srcDir = $this->projectRoot . '/vendor/processwire/boost/resources/builder/blueprints';
        $fallbackDir = __DIR__ . '/../resources/builder/blueprints';
        $base = is_dir($srcDir) ? $srcDir : $fallbackDir;
        if (!is_dir($base)) {
            return;
        }
        $files = glob($base . '/*.json') ?: [];
        foreach ($files as $f) {
            $name = basename($f);
            copy($f, rtrim($targetDir, '/') . '/' . $name);
        }
    }
}

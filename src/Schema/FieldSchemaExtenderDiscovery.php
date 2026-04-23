<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Schema;

use Totoglu\Console\Boost\Schema\Extenders\RepeaterFieldSchemaExtender;

final class FieldSchemaExtenderDiscovery
{
    public function __construct(private readonly string $projectRoot) {}

    /**
     * @return FieldSchemaExtender[]
     */
    public function resolve(): array
    {
        $resolved = [];

        foreach ($this->builtInExtenders() as $extender) {
            $resolved[$extender::class] = $extender;
        }

        foreach ($this->manifestPaths() as $manifestPath) {
            $entries = $this->loadManifest($manifestPath);
            foreach ($entries as $entry) {
                $extender = $this->toExtender($entry);
                if (!$extender) {
                    continue;
                }

                $resolved[$extender::class] = $extender;
            }
        }

        return array_values($resolved);
    }

    /**
     * @return FieldSchemaExtender[]
     */
    private function builtInExtenders(): array
    {
        return [
            new RepeaterFieldSchemaExtender(),
        ];
    }

    /**
     * @return string[]
     */
    private function manifestPaths(): array
    {
        $paths = [];

        $siteManifest = $this->projectRoot . '/site/boost/schema/field-extenders.php';
        if (is_file($siteManifest)) {
            $paths[] = $siteManifest;
        }

        $config = \ProcessWire\wire('config');
        $moduleRoots = [
            $config->paths->siteModules,
            $config->paths->modules,
        ];

        foreach ($moduleRoots as $moduleRoot) {
            if (!is_dir($moduleRoot)) {
                continue;
            }

            foreach (new \DirectoryIterator($moduleRoot) as $moduleDir) {
                if ($moduleDir->isDot() || !$moduleDir->isDir()) {
                    continue;
                }

                $manifest = $moduleDir->getPathname() . '/.agents/schema/field-extenders.php';
                if (is_file($manifest)) {
                    $paths[] = $manifest;
                }
            }
        }

        return $paths;
    }

    /**
     * @return array<int,mixed>
     */
    private function loadManifest(string $manifestPath): array
    {
        try {
            $manifest = require $manifestPath;
        } catch (\Throwable $e) {
            $this->logDiscoveryError(sprintf(
                'Failed to load field schema extender manifest "%s": %s',
                $manifestPath,
                $e->getMessage()
            ));
            return [];
        }

        if (!is_array($manifest)) {
            return [];
        }

        return array_values($manifest);
    }

    private function toExtender(mixed $entry): ?FieldSchemaExtender
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
            $this->logDiscoveryError(sprintf(
                'Failed to instantiate field schema extender "%s": %s',
                $entry,
                $e->getMessage()
            ));
            return null;
        }

        return $instance instanceof FieldSchemaExtender ? $instance : null;
    }

    private function logDiscoveryError(string $message): void
    {
        try {
            $log = \ProcessWire\wire('log');
            if ($log) {
                $log->save('processwire-boost', $message);
            }
        } catch (\Throwable) {
            // Discovery must stay non-fatal, including logging failures.
        }
    }
}

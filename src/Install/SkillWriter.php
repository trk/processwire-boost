<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install;

final class SkillWriter
{
    /**
     * @param array<string, array{path: string, guidelines_path: ?string, skills_path: ?string, has_guidelines: bool, has_skills: bool}> $discoverableModules
     */
    public function __construct(
        private readonly string $targetDir,
        private readonly array $discoverableModules
    ) {
    }

    /**
     * @param list<string> $modules
     */
    public function sync(array $modules): void
    {
        $target = $this->targetDir . '/skills';
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        foreach ($this->collectDesiredSkillSources($modules) as $skillName => $sourcePath) {
            $targetPath = $target . '/' . $skillName;

            if (is_dir($targetPath)) {
                $this->deleteTree($targetPath);
            } elseif (is_file($targetPath)) {
                unlink($targetPath);
            }

            $this->copyDirectory($sourcePath, $targetPath);
        }
    }

    public function removeCoreSkills(): void
    {
        foreach (array_keys($this->collectDesiredSkillSources([])) as $skillName) {
            $skillPath = $this->targetDir . '/skills/' . $skillName;
            if (is_dir($skillPath)) {
                $this->deleteTree($skillPath);
            }
        }
    }

    /**
     * @param list<string> $modules
     * @return array<string, string>
     */
    private function collectDesiredSkillSources(array $modules): array
    {
        $sources = $this->collectSkillSourcesFromDirectory(dirname(__DIR__, 2) . '/resources/boost/skills');

        foreach ($modules as $moduleName) {
            if (!isset($this->discoverableModules[$moduleName])) {
                continue;
            }

            $skillsPath = $this->discoverableModules[$moduleName]['skills_path'] ?? null;
            if (!is_string($skillsPath)) {
                continue;
            }

            $sources = array_merge($sources, $this->collectSkillSourcesFromDirectory($skillsPath));
        }

        return $sources;
    }

    /**
     * @return array<string, string>
     */
    private function collectSkillSourcesFromDirectory(string $sourceDir): array
    {
        if (!is_dir($sourceDir)) {
            return [];
        }

        $sources = [];
        foreach (new \DirectoryIterator($sourceDir) as $file) {
            if ($file->isDot() || !$file->isDir()) {
                continue;
            }

            $skillPath = $file->getPathname();
            if (!is_file($skillPath . '/SKILL.md')) {
                continue;
            }

            $sources[$file->getFilename()] = $skillPath;
        }

        return $sources;
    }

    private function copyDirectory(string $source, string $target): void
    {
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        foreach (scandir($source) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcFile = $source . '/' . $file;
            $dstFile = $target . '/' . $file;

            if (is_dir($srcFile)) {
                $this->copyDirectory($srcFile, $dstFile);
                continue;
            }

            copy($srcFile, $dstFile);
        }
    }

    private function deleteTree(string $dir): bool
    {
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteTree($path) : unlink($path);
        }

        return rmdir($dir);
    }
}

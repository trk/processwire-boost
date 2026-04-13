<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Skills\Remote;

use RuntimeException;

class GitHubSkillProvider
{
    private ?string $defaultBranch = null;
    private ?array $cachedTree = null;

    public function __construct(protected GitHubRepository $repository) {}

    public function discoverSkills(): array
    {
        $tree = $this->fetchRepositoryTree();

        if ($tree === null) {
            return [];
        }

        $basePath = $this->repository->path;

        $skillMarkers = array_filter($tree['tree'], fn(array $item): bool => 
            $item['type'] === 'blob' && in_array(basename((string) $item['path']), ['SKILL.md', 'SKILL.blade.php'], true)
        );

        if ($basePath !== '') {
            $prefix = $basePath . '/';
            $skillMarkers = array_filter($skillMarkers, function (array $item) use ($prefix): bool {
                $skillDir = dirname((string) $item['path']);
                return str_starts_with($skillDir, $prefix) && ! str_contains(substr($skillDir, strlen($prefix)), '/');
            });
        }

        $skills = [];
        foreach ($skillMarkers as $item) {
            $skills[basename(dirname((string) $item['path']))] = new RemoteSkill(
                name: basename(dirname((string) $item['path'])),
                repo: $this->repository->fullName(),
                path: dirname((string) $item['path'])
            );
        }

        return $skills;
    }

    public function downloadSkill(RemoteSkill $skill, string $targetPath): bool
    {
        $tree = $this->fetchRepositoryTree();

        if ($tree === null) {
            return false;
        }

        $skillFiles = $this->extractSkillFilesFromTree($tree['tree'], $skill->path);

        if (empty($skillFiles)) {
            return false;
        }

        if (!$this->ensureDirectoryExists($targetPath)) {
            return false;
        }

        $files = array_filter($skillFiles, fn(array $item): bool => $item['type'] === 'blob');
        $directories = array_filter($skillFiles, fn(array $item): bool => $item['type'] === 'tree');

        foreach ($directories as $dir) {
            $relativePath = $this->getRelativePath($dir['path'], $skill->path);
            if (!$this->isSafeRelativePath($relativePath)) {
                return false;
            }
            $localPath = $targetPath . '/' . $relativePath;

            if (!$this->ensureDirectoryExists($localPath)) {
                return false;
            }
        }

        return $this->downloadFiles($files, $targetPath, $skill->path);
    }

    protected function fetchRepositoryTree(): ?array
    {
        if ($this->cachedTree !== null) {
            return $this->cachedTree;
        }

        $url = sprintf(
            'https://api.github.com/repos/%s/%s/git/trees/%s?recursive=1',
            $this->repository->owner,
            $this->repository->repo,
            urlencode($this->resolveDefaultBranch())
        );

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: application/vnd.github.v3+json',
                    'User-Agent: ProcessWire-Boost'
                ],
                'timeout' => 30
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new RuntimeException('Failed to connect to GitHub API');
        }

        $tree = json_decode($response, true);

        if (!is_array($tree) || !isset($tree['tree']) || !is_array($tree['tree'])) {
            throw new RuntimeException('Invalid response structure from GitHub Tree API');
        }

        $this->cachedTree = $tree;
        return $tree;
    }

    protected function extractSkillFilesFromTree(array $tree, string $skillPath): array
    {
        $prefix = $skillPath . '/';
        return array_filter($tree, fn(array $item): bool => str_starts_with((string) $item['path'], $prefix));
    }

    protected function downloadFiles(array $files, string $targetPath, string $basePath): bool
    {
        foreach ($files as $item) {
            $url = $this->buildRawFileUrl($item['path']);
            $relativePath = $this->getRelativePath($item['path'], $basePath);
            if (!$this->isSafeRelativePath($relativePath)) {
                return false;
            }
            $localPath = $targetPath . '/' . $relativePath;

            if (!$this->ensureDirectoryExists(dirname($localPath))) {
                return false;
            }

            $content = @file_get_contents($url);
            if ($content === false) {
                return false;
            }

            if (file_put_contents($localPath, $content) === false) {
                return false;
            }
        }

        return true;
    }

    protected function isSafeRelativePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        if (str_contains($path, "\0")) {
            return false;
        }
        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:\\//', $path)) {
            return false;
        }
        if (preg_match('#(^|/)\\.\\.(/|$)#', $path)) {
            return false;
        }
        return true;
    }

    protected function buildRawFileUrl(string $path): string
    {
        return sprintf(
            'https://raw.githubusercontent.com/%s/%s/%s/%s',
            $this->repository->owner,
            $this->repository->repo,
            $this->resolveDefaultBranch(),
            ltrim($path, '/')
        );
    }

    protected function getRelativePath(string $fullPath, string $basePath): string
    {
        if (str_starts_with($fullPath, $basePath . '/')) {
            return substr($fullPath, strlen($basePath) + 1);
        }

        return basename($fullPath);
    }

    protected function ensureDirectoryExists(string $path): bool
    {
        return is_dir($path) || @mkdir($path, 0755, true);
    }

    protected function resolveDefaultBranch(): string
    {
        if ($this->defaultBranch !== null) {
            return $this->defaultBranch;
        }

        $url = sprintf(
            'https://api.github.com/repos/%s/%s',
            $this->repository->owner,
            $this->repository->repo
        );

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: application/vnd.github.v3+json',
                    'User-Agent: ProcessWire-Boost'
                ],
                'timeout' => 15
            ]
        ]);

        $response = @file_get_contents($url, false, $context);

        $branch = 'main';
        if ($response !== false) {
            $data = json_decode($response, true);
            $branch = $data['default_branch'] ?? 'main';
        }

        $this->defaultBranch = is_string($branch) ? $branch : 'main';
        return $this->defaultBranch;
    }
}

<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Skills\Remote;

use Closure;
use RuntimeException;

final class GitHubSkillProvider
{
	private ?string $defaultBranch = null;

	/** @var ?array<string, mixed> */
	private ?array $cachedTree = null;

	/** @var array<string, array<string, string>> */
	private array $cachedSkillFiles = [];

	/**
	 * @param ?Closure(string, array<string, mixed>): (string|false) $httpFetcher
	 */
	public function __construct(
		private readonly GitHubRepository $repository,
		private readonly ?Closure $httpFetcher = null
	) {
	}

	/**
	 * @return array<string, RemoteSkill>
	 */
	public function discoverSkills(): array
	{
		$tree = $this->fetchRepositoryTree();

		if ($tree === null) {
			return [];
		}

		$basePath = $this->repository->path;

		$skillMarkers = array_filter(
			$tree['tree'],
			static fn(array $item): bool => ($item['type'] ?? null) === 'blob' && basename((string) ($item['path'] ?? '')) === 'SKILL.md'
		);

		if ($basePath !== '') {
			$prefix = $basePath . '/';
			$skillMarkers = array_filter($skillMarkers, static function (array $item) use ($prefix): bool {
				$skillDir = dirname((string) $item['path']);
				return str_starts_with($skillDir, $prefix) && !str_contains(substr($skillDir, strlen($prefix)), '/');
			});
		}

		$skills = [];
		foreach ($skillMarkers as $item) {
			$skillName = basename(dirname((string) $item['path']));
			$skills[$skillName] = new RemoteSkill(
				name: $skillName,
				repo: $this->repository->fullName(),
				path: dirname((string) $item['path'])
			);
		}

		return $skills;
	}

	public function downloadSkill(RemoteSkill $skill, string $targetPath): bool
	{
		$files = $this->fetchSkillFiles($skill);
		if ($files === []) {
			return false;
		}

		if (!$this->ensureDirectoryExists($targetPath)) {
			return false;
		}

		foreach ($files as $relativePath => $content) {
			$localPath = $targetPath . '/' . $relativePath;

			if (!$this->ensureDirectoryExists(dirname($localPath))) {
				return false;
			}

			if (file_put_contents($localPath, $content) === false) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @return array<string, string>
	 */
	public function fetchSkillFiles(RemoteSkill $skill): array
	{
		$cacheKey = $skill->repo . ':' . $skill->path;
		if (array_key_exists($cacheKey, $this->cachedSkillFiles)) {
			return $this->cachedSkillFiles[$cacheKey];
		}

		$tree = $this->fetchRepositoryTree();

		if ($tree === null) {
			return [];
		}

		$skillFiles = $this->extractSkillFilesFromTree($tree['tree'], $skill->path);

		if ($skillFiles === []) {
			return [];
		}

		$files = [];

		foreach ($skillFiles as $item) {
			if (($item['type'] ?? null) !== 'blob') {
				continue;
			}

			$relativePath = $this->getRelativePath((string) $item['path'], $skill->path);
			if (!$this->isSafeRelativePath($relativePath)) {
				return [];
			}

			$url = $this->buildRawFileUrl((string) $item['path']);
			$content = $this->httpGet($url, 30, false);
			if ($content === false) {
				return [];
			}

			$files[$relativePath] = $content;
		}

		$this->cachedSkillFiles[$cacheKey] = $files;

		return $files;
	}

	/**
	 * @return ?array<string, mixed>
	 */
	private function fetchRepositoryTree(): ?array
	{
		if ($this->cachedTree !== null) {
			return $this->cachedTree;
		}

		$url = sprintf(
			'https://api.github.com/repos/%s/%s/git/trees/%s?recursive=1',
			rawurlencode($this->repository->owner),
			rawurlencode($this->repository->repo),
			rawurlencode($this->resolveDefaultBranch())
		);

		$response = $this->httpGet($url, 30, true);

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

	/**
	 * @param list<array<string, mixed>> $tree
	 * @return array<int, array<string, mixed>>
	 */
	private function extractSkillFilesFromTree(array $tree, string $skillPath): array
	{
		$prefix = $skillPath . '/';
		return array_filter($tree, static fn(array $item): bool => str_starts_with((string) ($item['path'] ?? ''), $prefix));
	}

	private function isSafeRelativePath(string $path): bool
	{
		if ($path === '') {
			return false;
		}
		if (str_contains($path, "\0")) {
			return false;
		}
		$path = str_replace('\\', '/', $path);
		if (str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:\//', $path)) {
			return false;
		}
		if (preg_match('#(^|/)\.\.(/|$)#', $path)) {
			return false;
		}
		return true;
	}

	private function buildRawFileUrl(string $path): string
	{
		$segments = array_map(
			static fn(string $segment): string => rawurlencode($segment),
			explode('/', ltrim($path, '/'))
		);

		return sprintf(
			'https://raw.githubusercontent.com/%s/%s/%s/%s',
			rawurlencode($this->repository->owner),
			rawurlencode($this->repository->repo),
			rawurlencode($this->resolveDefaultBranch()),
			implode('/', $segments)
		);
	}

	private function getRelativePath(string $fullPath, string $basePath): string
	{
		if (str_starts_with($fullPath, $basePath . '/')) {
			return substr($fullPath, strlen($basePath) + 1);
		}

		return basename($fullPath);
	}

	private function ensureDirectoryExists(string $path): bool
	{
		return is_dir($path) || @mkdir($path, 0755, true);
	}

	private function resolveDefaultBranch(): string
	{
		if ($this->defaultBranch !== null) {
			return $this->defaultBranch;
		}

		$url = sprintf(
			'https://api.github.com/repos/%s/%s',
			rawurlencode($this->repository->owner),
			rawurlencode($this->repository->repo)
		);

		$response = $this->httpGet($url, 15, true);

		$branch = 'main';
		if ($response !== false) {
			$data = json_decode($response, true);
			if (is_array($data) && is_string($data['default_branch'] ?? null) && $data['default_branch'] !== '') {
				$branch = $data['default_branch'];
			}
		}

		$this->defaultBranch = $branch;
		return $this->defaultBranch;
	}

	private function httpGet(string $url, int $timeout = 30, bool $isApi = true): string|false
	{
		$headers = ['User-Agent: ProcessWire-Boost'];
		if ($isApi) {
			array_unshift($headers, 'Accept: application/vnd.github.v3+json');
		}

		$token = getenv('GITHUB_TOKEN') ?: getenv('GH_TOKEN') ?: '';
		if (is_string($token) && $token !== '') {
			$headers[] = 'Authorization: Bearer ' . $token;
		}

		if ($this->httpFetcher !== null) {
			return ($this->httpFetcher)($url, [
				'headers' => $headers,
				'timeout' => $timeout,
			]);
		}

		$context = stream_context_create([
			'http' => [
				'method' => 'GET',
				'header' => $headers,
				'timeout' => $timeout,
			],
		]);

		return @file_get_contents($url, false, $context);
	}
}

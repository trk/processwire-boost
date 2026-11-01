<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Skills\Remote;

use InvalidArgumentException;

final readonly class GitHubRepository
{
	public function __construct(
		public string $owner,
		public string $repo,
		public string $path = ''
	) {
	}

	public static function fromInput(string $input): self
	{
		$normalized = self::normalizeUrl(trim($input));
		return self::parseOwnerRepoPath($normalized);
	}

	public function fullName(): string
	{
		return $this->owner . '/' . $this->repo;
	}

	private static function normalizeUrl(string $input): string
	{
		$isUrl = str_starts_with($input, 'http://') || str_starts_with($input, 'https://');

		if (!$isUrl) {
			return $input;
		}

		$parsed = parse_url($input);

		$host = $parsed['host'] ?? '';
		$isGitHubUrl = $host === 'github.com' || str_ends_with($host, '.github.com');

		if (!$isGitHubUrl) {
			throw new InvalidArgumentException('Only GitHub URLs are supported.');
		}

		$path = trim($parsed['path'] ?? '', '/');

		if (str_contains($path, '/tree/')) {
			$path = preg_replace('#/tree/[^/]+#', '', $path) ?? $path;
		}

		return $path;
	}

	private static function parseOwnerRepoPath(string $input): self
	{
		$parts = explode('/', $input);

		if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
			throw new InvalidArgumentException('Invalid repository format. Expected: owner/repo, owner/repo/path, or GitHub URL');
		}

		$owner = $parts[0];
		$repo = preg_replace('/\.git$/i', '', $parts[1]) ?? $parts[1];

		if (
			!preg_match('/^[A-Za-z0-9._-]+$/', $owner)
			|| !preg_match('/^[A-Za-z0-9._-]+$/', $repo)
			|| $owner === '.'
			|| $owner === '..'
			|| $repo === '.'
			|| $repo === '..'
		) {
			throw new InvalidArgumentException('Invalid repository owner or name.');
		}

		return new self(
			owner: $owner,
			repo: $repo,
			path: implode('/', array_slice($parts, 2))
		);
	}
}

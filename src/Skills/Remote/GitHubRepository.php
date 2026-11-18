<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Skills\Remote;

use InvalidArgumentException;

final readonly class GitHubRepository
{
	public function __construct(
		public string $owner,
		public string $repo,
		public string $path = '',
		public ?string $ref = null
	) {
	}

	public static function fromInput(string $input): self
	{
		[$normalized, $ref] = self::normalizeUrl(trim($input));
		return self::parseOwnerRepoPath($normalized, $ref);
	}

	public function fullName(): string
	{
		return $this->owner . '/' . $this->repo;
	}

	/**
	 * @return array{0: string, 1: ?string}
	 */
	private static function normalizeUrl(string $input): array
	{
		if (str_starts_with($input, 'http://')) {
			throw new InvalidArgumentException('Insecure HTTP GitHub URLs are not supported; use https://.');
		}

		if (!str_starts_with($input, 'https://')) {
			return [$input, null];
		}

		$parsed = parse_url($input);
		$host = strtolower((string) ($parsed['host'] ?? ''));

		if ($host !== 'github.com' && $host !== 'www.github.com') {
			throw new InvalidArgumentException('Only github.com repository URLs are supported.');
		}

		$path = trim((string) ($parsed['path'] ?? ''), '/');

		if (preg_match('#^([^/]+/[^/]+)/(?:tree|blob)/([^/]+)(?:/(.*))?$#', $path, $matches)) {
			$rawRef = rawurldecode($matches[2]);
			if ($rawRef === '' || str_contains($rawRef, '..') || str_contains($rawRef, '\\') || str_contains($rawRef, "\0") || preg_match('/\s/', $rawRef) === 1) {
				throw new InvalidArgumentException('Invalid Git reference in repository URL.');
			}
			$subPath = $matches[3] ?? '';
			return [$subPath !== '' ? $matches[1] . '/' . $subPath : $matches[1], $rawRef];
		}

		return [$path, null];
	}

	private static function parseOwnerRepoPath(string $input, ?string $ref = null): self
	{
		$decodedInput = rawurldecode($input);
		if (str_contains($decodedInput, '\\') || str_contains($decodedInput, "\0")) {
			throw new InvalidArgumentException('Invalid characters in repository path.');
		}

		$parts = explode('/', trim($decodedInput, '/'));

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

		$subSegments = array_slice($parts, 2);
		foreach ($subSegments as $segment) {
			if ($segment === '' || $segment === '.' || $segment === '..') {
				throw new InvalidArgumentException('Invalid repository subpath.');
			}
		}

		return new self(
			owner: $owner,
			repo: $repo,
			path: implode('/', $subSegments),
			ref: $ref
		);
	}
}

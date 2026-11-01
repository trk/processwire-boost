<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Agents;

final class GrokBuild extends Agent
{
	public function name(): string
	{
		return 'grok_build';
	}

	public function displayName(): string
	{
		return 'Grok Build';
	}

	public function mcpConfigPath(): ?string
	{
		return '.grok/config.toml';
	}

	public function mcpConfigKey(): string
	{
		return 'mcp_servers';
	}

	public function skillsPath(): string
	{
		return '.grok/skills';
	}

	public function systemDetectionPaths(): array
	{
		return ['~/.grok'];
	}

	public function systemDetectionBinaries(): array
	{
		return ['grok'];
	}

	public function projectDetectionPaths(): array
	{
		return ['.grok', '.grok/config.toml'];
	}
}

<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Agents;

final class Antigravity extends Agent
{
	public function name(): string
	{
		return 'antigravity';
	}

	public function displayName(): string
	{
		return 'Antigravity';
	}

	public function mcpConfigPath(): ?string
	{
		return '.agents/mcp_config.json';
	}

	public function systemDetectionBinaries(): array
	{
		return ['antigravity'];
	}

	public function projectDetectionPaths(): array
	{
		return ['.agents', '.agents/mcp_config.json'];
	}
}

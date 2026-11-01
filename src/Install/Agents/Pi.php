<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Install\Agents;

final class Pi extends Agent
{
	public function name(): string
	{
		return 'pi';
	}

	public function displayName(): string
	{
		return 'Pi';
	}

	public function skillsPath(): string
	{
		return '.pi/skills';
	}

	public function systemDetectionBinaries(): array
	{
		return ['pi'];
	}

	public function projectDetectionPaths(): array
	{
		return ['.pi', '.pi/settings.json'];
	}
}

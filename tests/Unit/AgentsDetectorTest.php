<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Tests\Unit;

use Totoglu\Console\Boost\BoostManager;
use Totoglu\Console\Boost\Install\AgentsDetector;
use Totoglu\Console\Boost\Tests\TestCase;

final class AgentsDetectorTest extends TestCase
{
	public function test_detects_project_installed_agents_accurately(): void
	{
		$manager = new BoostManager($this->tempDir);
		$detector = new AgentsDetector($manager);

		// Initially empty
		$this->assertSame([], $detector->discoverProjectInstalledAgents($this->tempDir));

		// Create .cursor
		mkdir($this->tempDir . '/.cursor', 0755, true);
		$detected = $detector->discoverProjectInstalledAgents($this->tempDir);
		$this->assertContains('cursor', $detected);

		// Create .trae
		mkdir($this->tempDir . '/.trae', 0755, true);
		$detected = $detector->discoverProjectInstalledAgents($this->tempDir);
		$this->assertContains('trae', $detected);

		// Create .claude
		mkdir($this->tempDir . '/.claude', 0755, true);
		$detected = $detector->discoverProjectInstalledAgents($this->tempDir);
		$this->assertContains('claude_code', $detected);
	}
}

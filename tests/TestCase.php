<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
	protected string $tempDir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->tempDir = sys_get_temp_dir() . '/pw_boost_test_' . bin2hex(random_bytes(6));
		mkdir($this->tempDir, 0755, true);
	}

	protected function tearDown(): void
	{
		if (is_dir($this->tempDir)) {
			$this->removeDirectory($this->tempDir);
		}
		parent::tearDown();
	}

	protected function removeDirectory(string $path): void
	{
		if (!is_dir($path)) {
			return;
		}

		foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
			$itemPath = $path . '/' . $item;
			is_dir($itemPath) ? $this->removeDirectory($itemPath) : @unlink($itemPath);
		}

		@rmdir($path);
	}
}

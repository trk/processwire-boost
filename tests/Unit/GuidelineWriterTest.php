<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Tests\Unit;

use Totoglu\Console\Boost\Install\Agents\Antigravity;
use Totoglu\Console\Boost\Install\Agents\ClaudeCode;
use Totoglu\Console\Boost\Install\Agents\Copilot;
use Totoglu\Console\Boost\Install\Agents\Cursor;
use Totoglu\Console\Boost\Install\Agents\Gemini;
use Totoglu\Console\Boost\Install\GuidelineWriter;
use Totoglu\Console\Boost\Tests\TestCase;

final class GuidelineWriterTest extends TestCase
{
	private GuidelineWriter $writer;

	protected function setUp(): void
	{
		parent::setUp();
		$this->writer = new GuidelineWriter(
			projectRoot: $this->tempDir,
			targetDir: $this->tempDir . '/.agents',
			discoverableModules: []
		);
	}

	public function test_it_writes_guidelines_to_agents_md(): void
	{
		$agents = [new Antigravity()];
		$this->writer->write($agents, []);

		$agentsMdPath = $this->tempDir . '/AGENTS.md';
		$this->assertFileExists($agentsMdPath);

		$content = (string) file_get_contents($agentsMdPath);
		$this->assertStringContainsString('<processwire-boost-guidelines>', $content);
		$this->assertStringContainsString('</processwire-boost-guidelines>', $content);
		$this->assertStringContainsString('=== php rules ===', $content);
		$this->assertStringContainsString('=== processwire core rules ===', $content);
		$this->assertStringContainsString('=== processwire selectors rules ===', $content);
		$this->assertStringContainsString('php index.php docs', $content);
	}

	public function test_it_writes_guidelines_to_other_agents_without_destroying_files(): void
	{
		$agents = [
			new Antigravity(),
			new ClaudeCode(),
			new Cursor(),
			new Copilot(),
			new Gemini(),
		];

		$this->writer->write($agents, []);

		$this->assertFileExists($this->tempDir . '/AGENTS.md');
		$this->assertFileExists($this->tempDir . '/CLAUDE.md');
		$this->assertFileExists($this->tempDir . '/.cursorrules');
		$this->assertFileExists($this->tempDir . '/.github/copilot-instructions.md');
		$this->assertFileExists($this->tempDir . '/GEMINI.md');

		// All agent files must contain the full guidelines block
		foreach (['CLAUDE.md', '.cursorrules', '.github/copilot-instructions.md', 'GEMINI.md'] as $file) {
			$content = (string) file_get_contents($this->tempDir . '/' . $file);
			$this->assertStringContainsString('<processwire-boost-guidelines>', $content, "Missing boost block in {$file}");
			$this->assertStringContainsString('</processwire-boost-guidelines>', $content, "Missing boost closing block in {$file}");
		}
	}

	public function test_it_preserves_existing_user_instructions_in_agent_files(): void
	{
		$cursorrulesPath = $this->tempDir . '/.cursorrules';
		$userInstructions = "# User Custom Rules\n\nAlways use Turkish in comments.\n";
		file_put_contents($cursorrulesPath, $userInstructions);

		$claudePath = $this->tempDir . '/CLAUDE.md';
		$claudeInstructions = "# Custom Claude Prompt\n\nFollow domain patterns.\n";
		file_put_contents($claudePath, $claudeInstructions);

		$agents = [new Cursor(), new ClaudeCode()];
		$this->writer->write($agents, []);

		$cursorContent = (string) file_get_contents($cursorrulesPath);
		$this->assertStringContainsString('Always use Turkish in comments.', $cursorContent);
		$this->assertStringContainsString('<processwire-boost-guidelines>', $cursorContent);

		$claudeContent = (string) file_get_contents($claudePath);
		$this->assertStringContainsString('Follow domain patterns.', $claudeContent);
		$this->assertStringContainsString('<processwire-boost-guidelines>', $claudeContent);
	}

	public function test_it_updates_boost_block_without_duplication_on_repeated_runs(): void
	{
		$agents = [new ClaudeCode()];
		$this->writer->write($agents, []);
		$this->writer->write($agents, []);

		$claudePath = $this->tempDir . '/CLAUDE.md';
		$content = (string) file_get_contents($claudePath);

		$count = substr_count($content, '<processwire-boost-guidelines>');
		$this->assertSame(1, $count, 'Boost block should only appear once after repeated installations');
	}

	public function test_it_removes_boost_block_and_preserves_user_content(): void
	{
		$cursorrulesPath = $this->tempDir . '/.cursorrules';
		$userInstructions = "# User Custom Rules\n\nAlways use Turkish in comments.\n";
		file_put_contents($cursorrulesPath, $userInstructions);

		$agents = [new Cursor()];
		$this->writer->write($agents, []);
		$this->assertStringContainsString('<processwire-boost-guidelines>', (string) file_get_contents($cursorrulesPath));

		$this->writer->remove($agents);

		$this->assertFileExists($cursorrulesPath);
		$cleanedContent = (string) file_get_contents($cursorrulesPath);
		$this->assertStringNotContainsString('<processwire-boost-guidelines>', $cleanedContent);
		$this->assertStringContainsString('Always use Turkish in comments.', $cleanedContent);
	}

	public function test_it_deletes_files_with_only_default_headers_on_remove(): void
	{
		$agents = [new ClaudeCode()];
		$this->writer->write($agents, []);

		$claudePath = $this->tempDir . '/CLAUDE.md';
		$this->assertFileExists($claudePath);

		$this->writer->remove($agents);
		$this->assertFileDoesNotExist($claudePath);
	}
}

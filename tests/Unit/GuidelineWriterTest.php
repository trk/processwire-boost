<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Tests\Unit;

use Totoglu\Console\Boost\Install\Agents\Amp;
use Totoglu\Console\Boost\Install\Agents\Antigravity;
use Totoglu\Console\Boost\Install\Agents\ClaudeCode;
use Totoglu\Console\Boost\Install\Agents\Copilot;
use Totoglu\Console\Boost\Install\Agents\Cursor;
use Totoglu\Console\Boost\Install\Agents\Gemini;
use Totoglu\Console\Boost\Install\Agents\Pi;
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

	public function test_it_writes_priority_ordered_guidelines_to_agents_md_without_noisy_pseudo_headers(): void
	{
		$agents = [new Antigravity()];
		$this->writer->write($agents, []);

		$agentsMdPath = $this->tempDir . '/AGENTS.md';
		$this->assertFileExists($agentsMdPath);

		$content = (string) file_get_contents($agentsMdPath);
		$this->assertStringContainsString('<processwire-boost-guidelines>', $content);
		$this->assertStringContainsString('</processwire-boost-guidelines>', $content);
		$this->assertStringContainsString('# Security & Access Control', $content);
		$this->assertStringContainsString('# ProcessWire Core API & Scoping', $content);
		$this->assertStringContainsString('# ProcessWire Selectors (Queries)', $content);
		$this->assertStringContainsString('# PHP Rules for ProcessWire', $content);
		$this->assertStringContainsString('php index.php docs', $content);
		$this->assertStringNotContainsString('=== php rules ===', $content);

		// Security rules must appear ahead of PHP stylistic rules so critical safety is never truncated
		$this->assertLessThan(
			strpos($content, '# PHP Rules for ProcessWire'),
			strpos($content, '# Security & Access Control')
		);
	}

	public function test_it_writes_only_selected_agent_targets_and_migrates_legacy_cursorrules(): void
	{
		// When only ClaudeCode is selected, AGENTS.md must NOT be forcibly created
		$this->writer->write([new ClaudeCode()], []);
		$this->assertFileExists($this->tempDir . '/CLAUDE.md');
		$this->assertFileDoesNotExist($this->tempDir . '/AGENTS.md');

		// Seed a legacy .cursorrules file with only Boost default content — writing Cursor must migrate to .cursor/rules/processwire-boost.mdc and clean up .cursorrules
		file_put_contents(
			$this->tempDir . '/.cursorrules',
			"# Cursor Instructions\n\nGenerated for ProcessWire AI Ecosystem.\n\n<processwire-boost-guidelines>\nold\n</processwire-boost-guidelines>\n"
		);

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
		$this->assertFileExists($this->tempDir . '/.cursor/rules/processwire-boost.mdc');
		$this->assertFileDoesNotExist($this->tempDir . '/.cursorrules');
		$this->assertFileExists($this->tempDir . '/.github/copilot-instructions.md');
		$this->assertFileExists($this->tempDir . '/GEMINI.md');

		$cursorMdc = (string) file_get_contents($this->tempDir . '/.cursor/rules/processwire-boost.mdc');
		$this->assertStringStartsWith("---\ndescription: Cursor Instructions\nalwaysApply: true\n---", $cursorMdc);

		foreach (['AGENTS.md', 'CLAUDE.md', '.cursor/rules/processwire-boost.mdc', '.github/copilot-instructions.md', 'GEMINI.md'] as $file) {
			$content = (string) file_get_contents($this->tempDir . '/' . $file);
			$this->assertStringContainsString('<processwire-boost-guidelines>', $content, "Missing boost block in {$file}");
			$this->assertStringContainsString('</processwire-boost-guidelines>', $content, "Missing boost closing block in {$file}");
			$this->assertLessThan(16000, strlen($content), "Guideline file {$file} exceeded compact 16 KB context budget");
		}
	}

	public function test_it_renders_agent_aware_skills_pointer_for_native_vs_file_only_agents(): void
	{
		mkdir($this->tempDir . '/.agents/skills/pw-pages', 0755, true);
		file_put_contents(
			$this->tempDir . '/.agents/skills/pw-pages/SKILL.md',
			"---\nname: pw-pages\ndescription: \"Use when constructing ProcessWire selectors or manipulating Page objects.\"\n---\n# Pages\n"
		);

		$this->writer->write([new ClaudeCode(), new Gemini(), new Amp()], []);

		$claudeContent = (string) file_get_contents($this->tempDir . '/CLAUDE.md');
		$this->assertStringContainsString('## Skills Activation (`.claude/skills`)', $claudeContent);
		$this->assertStringContainsString('`pw-pages`', $claudeContent);
		$this->assertStringNotContainsString('constructing ProcessWire selectors', $claudeContent);

		$geminiContent = (string) file_get_contents($this->tempDir . '/GEMINI.md');
		$this->assertStringContainsString('## Skills Activation (`.gemini/skills`)', $geminiContent);
		$this->assertStringContainsString('`pw-pages`', $geminiContent);

		$ampAgentsMd = (string) file_get_contents($this->tempDir . '/AGENTS.md');
		$this->assertStringContainsString('Read `.agents/skills/<skill>/SKILL.md`', $ampAgentsMd);
		$this->assertStringContainsString('- `pw-pages` — constructing ProcessWire selectors', $ampAgentsMd);
	}

	public function test_it_preserves_existing_user_instructions_in_agent_files(): void
	{
		$cursorMdcPath = $this->tempDir . '/.cursor/rules/processwire-boost.mdc';
		mkdir(dirname($cursorMdcPath), 0755, true);
		$userInstructions = "# User Custom Rules\n\nAlways preserve custom project notes.\n";
		file_put_contents($cursorMdcPath, $userInstructions);

		$claudePath = $this->tempDir . '/CLAUDE.md';
		$claudeInstructions = "# Custom Claude Prompt\n\nFollow domain patterns.\n";
		file_put_contents($claudePath, $claudeInstructions);

		$agents = [new Cursor(), new ClaudeCode()];
		$this->writer->write($agents, []);

		$cursorContent = (string) file_get_contents($cursorMdcPath);
		$this->assertStringContainsString('Always preserve custom project notes.', $cursorContent);
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
		$cursorMdcPath = $this->tempDir . '/.cursor/rules/processwire-boost.mdc';
		mkdir(dirname($cursorMdcPath), 0755, true);
		$userInstructions = "# User Custom Rules\n\nAlways preserve custom project notes.\n";
		file_put_contents($cursorMdcPath, $userInstructions);

		$agents = [new Cursor()];
		$this->writer->write($agents, []);
		$this->assertStringContainsString('<processwire-boost-guidelines>', (string) file_get_contents($cursorMdcPath));

		$this->writer->remove($agents);

		$this->assertFileExists($cursorMdcPath);
		$cleanedContent = (string) file_get_contents($cursorMdcPath);
		$this->assertStringNotContainsString('<processwire-boost-guidelines>', $cleanedContent);
		$this->assertStringContainsString('Always preserve custom project notes.', $cleanedContent);
	}

	public function test_it_deletes_files_with_only_default_headers_and_empty_directories_on_remove(): void
	{
		$agents = [new ClaudeCode(), new Cursor()];
		$this->writer->write($agents, []);

		$claudePath = $this->tempDir . '/CLAUDE.md';
		$cursorMdcPath = $this->tempDir . '/.cursor/rules/processwire-boost.mdc';
		$this->assertFileExists($claudePath);
		$this->assertFileExists($cursorMdcPath);

		$this->writer->remove($agents);
		$this->assertFileDoesNotExist($claudePath);
		$this->assertFileDoesNotExist($cursorMdcPath);
		$this->assertDirectoryDoesNotExist($this->tempDir . '/.cursor');
	}

	public function test_it_renders_project_profile_map_pointer_tooling_and_mcp_disabled_ladder(): void
	{
		mkdir($this->tempDir . '/.agents', 0755, true);
		file_put_contents($this->tempDir . '/.agents/map.json', json_encode([
			'templates' => [
				'admin' => ['id' => 2, 'fields' => ['title']],
				'user' => ['id' => 3, 'fields' => ['name']],
				'home' => ['id' => 1, 'fields' => ['title']],
				'article' => ['id' => 45, 'fields' => ['title', 'body']],
				'test_temp_fixture' => ['id' => 99, 'fields' => ['title']],
			],
			'fields' => [
				'title' => ['id' => 1, 'type' => 'FieldtypePageTitle', 'label' => 'Title'],
				'body' => ['id' => 2, 'type' => 'FieldtypeTextarea', 'label' => 'Body'],
			],
		]));
		file_put_contents($this->tempDir . '/composer.json', json_encode([
			'require-dev' => [
				'pestphp/pest' => '^3.0',
				'phpstan/phpstan' => '^2.0',
			],
		]));
		file_put_contents($this->tempDir . '/package.json', json_encode([
			'devDependencies' => [
				'vite' => '^6.0',
				'typescript' => '^5.0',
				'@playwright/test' => '^1.50',
			],
			'scripts' => [
				'build' => 'vite build',
				'typecheck' => 'tsc --noEmit',
			],
		]));

		// Write with MCP disabled (and Pi, which does not support MCP)
		$this->writer->write([new Pi(), new ClaudeCode()], [], false);

		$agentsContent = (string) file_get_contents($this->tempDir . '/AGENTS.md');
		$claudeContent = (string) file_get_contents($this->tempDir . '/CLAUDE.md');

		$this->assertStringContainsString('## Active Project Profile', $agentsContent);
		$this->assertStringContainsString('**Schema Snapshot (`.agents/map.json`):** 5 templates, 2 fields — site templates: `article`, `home`.', $agentsContent);
		$this->assertStringNotContainsString('`test_temp_fixture`', $agentsContent);
		$this->assertStringContainsString('`Pest`, `PHPStan`, `Vite`, `TypeScript`, `Playwright`, npm scripts (`build`, `typecheck`)', $agentsContent);
		$this->assertStringContainsString('**Schema Map & CLI Runtime:** Inspect `.agents/map.json`', $claudeContent);
		$this->assertStringNotContainsString('Call `pw_schema_read` and `pw_module_list`', $claudeContent);
		$this->assertStringContainsString('getLanguageValue', $claudeContent);
		$this->assertStringContainsString('getUnformatted(\'image\')->first()', $claudeContent);
	}
}

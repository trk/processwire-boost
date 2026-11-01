<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Tests\Unit;

use Totoglu\Console\Boost\BoostManager;
use Totoglu\Console\Boost\Install\Agents\ClaudeCode;
use Totoglu\Console\Boost\Install\Agents\Cursor;
use Totoglu\Console\Boost\Tests\TestCase;

final class BoostManagerTest extends TestCase
{
	public function test_install_syncs_skills_before_guidelines_so_skills_menu_is_populated_on_fresh_install(): void
	{
		$manager = new BoostManager($this->tempDir);
		$agents = [new Cursor(), new ClaudeCode()];

		$manager->install(['guidelines', 'skills'], [], $agents);

		$agentsMdPath = $this->tempDir . '/AGENTS.md';
		$this->assertFileExists($agentsMdPath);

		$content = (string) file_get_contents($agentsMdPath);
		$this->assertStringContainsString('- `pw-pages` —', $content);
		$this->assertStringContainsString('- `pw-module-development` —', $content);
		$this->assertStringNotContainsString('{{ SKILLS_MENU }}', $content);
	}

	public function test_load_and_save_config_roundtrip(): void
	{
		$manager = new BoostManager($this->tempDir);

		$this->assertFalse($manager->hasConfig());
		$defaultConfig = $manager->loadConfig();
		$this->assertSame(BoostManager::VERSION, $defaultConfig['version']);
		$this->assertFalse($defaultConfig['guidelines']);
		$this->assertFalse($defaultConfig['skills']);
		$this->assertFalse($defaultConfig['mcp']);

		$manager->saveConfig([
			'guidelines' => true,
			'skills' => true,
			'mcp' => false,
			'modules' => ['Htmx'],
			'agents' => ['Cursor', 'Claude Code'],
		]);

		$this->assertTrue($manager->hasConfig());
		$loaded = $manager->loadConfig();
		$this->assertSame(BoostManager::VERSION, $loaded['version']);
		$this->assertTrue($loaded['guidelines']);
		$this->assertTrue($loaded['skills']);
		$this->assertFalse($loaded['mcp']);
		$this->assertSame(['Htmx'], $loaded['modules']);
		$this->assertSame(['claude_code', 'cursor'], $loaded['agents']);
		$this->assertIsString($loaded['generated_at']);
	}

	public function test_discoverable_modules_supports_boost_and_agents_dirs_and_root_agents_md_without_bare_fallback(): void
	{
		$siteModules = $this->tempDir . '/site/modules';

		// 1. Module with boost/guidelines
		mkdir($siteModules . '/ModBoost/boost/guidelines', 0755, true);
		file_put_contents($siteModules . '/ModBoost/boost/guidelines/rule.md', '# Rule');

		// 2. Module with .agents/skills
		mkdir($siteModules . '/ModAgents/.agents/skills/my-skill', 0755, true);
		file_put_contents($siteModules . '/ModAgents/.agents/skills/my-skill/SKILL.md', "---\nname: my-skill\ndescription: desc\n---");

		// 3. Module with root AGENTS.md only
		mkdir($siteModules . '/ModRootMd', 0755, true);
		file_put_contents($siteModules . '/ModRootMd/AGENTS.md', "---\ndescription: Root MD module\n---\n# Docs");

		// 4. Module with bare skills/ dir (must NOT be discovered)
		mkdir($siteModules . '/ModBareFallback/skills/some-skill', 0755, true);
		file_put_contents($siteModules . '/ModBareFallback/skills/some-skill/SKILL.md', '# Bare');

		$manager = new BoostManager($this->tempDir);
		$discovered = $manager->getDiscoverableModules();

		$this->assertArrayHasKey('ModBoost', $discovered);
		$this->assertTrue($discovered['ModBoost']['has_guidelines']);
		$this->assertFalse($discovered['ModBoost']['has_skills']);

		$this->assertArrayHasKey('ModAgents', $discovered);
		$this->assertFalse($discovered['ModAgents']['has_guidelines']);
		$this->assertTrue($discovered['ModAgents']['has_skills']);

		$this->assertArrayHasKey('ModRootMd', $discovered);
		$this->assertTrue($discovered['ModRootMd']['has_guidelines']);
		$this->assertNull($discovered['ModRootMd']['guidelines_path']);

		$this->assertArrayNotHasKey('ModBareFallback', $discovered);
	}

	public function test_uninstall_agent_cleans_up_agent_files_and_preserves_universal_agents_md(): void
	{
		$manager = new BoostManager($this->tempDir);
		$cursor = new Cursor();
		$claude = new ClaudeCode();

		$manager->install(['guidelines', 'skills', 'mcp'], [], [$cursor, $claude]);

		$this->assertFileExists($this->tempDir . '/AGENTS.md');
		$this->assertFileExists($this->tempDir . '/.cursorrules');
		$this->assertFileExists($this->tempDir . '/CLAUDE.md');
		$this->assertDirectoryExists($this->tempDir . '/.cursor/skills/pw-pages');
		$this->assertFileExists($this->tempDir . '/.cursor/mcp.json');

		$manager->uninstallAgent($cursor);

		$this->assertFileExists($this->tempDir . '/AGENTS.md');
		$this->assertFileExists($this->tempDir . '/CLAUDE.md');
		$this->assertFileDoesNotExist($this->tempDir . '/.cursorrules');
		$this->assertDirectoryDoesNotExist($this->tempDir . '/.cursor/skills');

		$cursorMcp = json_decode((string) file_get_contents($this->tempDir . '/.cursor/mcp.json'), true);
		$this->assertArrayNotHasKey('processwire', $cursorMcp['mcpServers']);
	}

	public function test_detects_project_installed_agents_accurately(): void
	{
		$manager = new BoostManager($this->tempDir);

		$this->assertSame([], $manager->detectProjectAgents($this->tempDir));

		mkdir($this->tempDir . '/.cursor', 0755, true);
		$detected = $manager->detectProjectAgents($this->tempDir);
		$this->assertContains('cursor', $detected);

		mkdir($this->tempDir . '/.trae', 0755, true);
		$detected = $manager->detectProjectAgents($this->tempDir);
		$this->assertContains('trae', $detected);

		mkdir($this->tempDir . '/.claude', 0755, true);
		$detected = $manager->detectProjectAgents($this->tempDir);
		$this->assertContains('claude_code', $detected);
	}
}

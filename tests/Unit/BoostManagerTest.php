<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Tests\Unit;

use Totoglu\Console\Boost\BoostManager;
use Totoglu\Console\Boost\Install\Agents\Antigravity;
use Totoglu\Console\Boost\Install\Agents\ClaudeCode;
use Totoglu\Console\Boost\Install\Agents\Cursor;
use Totoglu\Console\Boost\Install\Agents\Gemini;
use Totoglu\Console\Boost\Tests\TestCase;

final class BoostManagerTest extends TestCase
{
	public function test_install_syncs_skills_before_guidelines_so_skills_menu_is_populated_on_fresh_install(): void
	{
		$manager = new BoostManager($this->tempDir);
		$agents = [new Antigravity(), new Cursor(), new ClaudeCode(), new Gemini()];

		$manager->install(['guidelines', 'skills'], [], $agents);

		$agentsMdPath = $this->tempDir . '/AGENTS.md';
		$this->assertFileExists($agentsMdPath);

		$content = (string) file_get_contents($agentsMdPath);
		$this->assertStringContainsString('`pw-pages`', $content);
		$this->assertStringContainsString('`pw-module-development`', $content);
		$this->assertStringNotContainsString('{{ SKILLS_SECTION }}', $content);

		$geminiContent = (string) file_get_contents($this->tempDir . '/GEMINI.md');
		$this->assertStringContainsString('## Skills Activation (`.gemini/skills`)', $geminiContent);
		$this->assertFileExists($this->tempDir . '/.gemini/skills/pw-pages/SKILL.md');
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
			'modules' => ['MockModule'],
			'agents' => ['Cursor', 'Claude Code'],
		]);

		$this->assertTrue($manager->hasConfig());
		$loaded = $manager->loadConfig();
		$this->assertSame(BoostManager::VERSION, $loaded['version']);
		$this->assertTrue($loaded['guidelines']);
		$this->assertTrue($loaded['skills']);
		$this->assertFalse($loaded['mcp']);
		$this->assertSame(['MockModule'], $loaded['modules']);
		$this->assertSame(['claude_code', 'cursor'], $loaded['agents']);
		$this->assertIsString($loaded['generated_at']);
	}

	public function test_discoverable_modules_supports_canonical_agents_dirs_and_root_agents_md(): void
	{
		$siteModules = $this->tempDir . '/site/modules';

		// 1. Module with .agents/guidelines
		mkdir($siteModules . '/ModGuidelines/.agents/guidelines', 0755, true);
		file_put_contents($siteModules . '/ModGuidelines/.agents/guidelines/rule.md', '# Rule');

		// 2. Module with .agents/skills
		mkdir($siteModules . '/ModAgents/.agents/skills/my-skill', 0755, true);
		file_put_contents($siteModules . '/ModAgents/.agents/skills/my-skill/SKILL.md', "---\nname: my-skill\ndescription: desc\n---");

		// 3. Module with root AGENTS.md only
		mkdir($siteModules . '/ModRootMd', 0755, true);
		file_put_contents($siteModules . '/ModRootMd/AGENTS.md', "---\ndescription: Root MD module\n---\n# Docs");

		// 4. Module with non-canonical boost/ or bare skills/ dir (must NOT be discovered)
		mkdir($siteModules . '/ModNonCanonicalBoost/boost/guidelines', 0755, true);
		file_put_contents($siteModules . '/ModNonCanonicalBoost/boost/guidelines/rule.md', '# NonCanonical');
		mkdir($siteModules . '/ModBareDir/skills/some-skill', 0755, true);
		file_put_contents($siteModules . '/ModBareDir/skills/some-skill/SKILL.md', '# Bare');

		// 5. Canonical site/.agents/ overrides
		mkdir($this->tempDir . '/site/.agents/guidelines', 0755, true);
		file_put_contents($this->tempDir . '/site/.agents/guidelines/site.md', '# Site');

		$manager = new BoostManager($this->tempDir);
		$discovered = $manager->getDiscoverableModules();

		$this->assertArrayHasKey('ModGuidelines', $discovered);
		$this->assertTrue($discovered['ModGuidelines']['has_guidelines']);
		$this->assertFalse($discovered['ModGuidelines']['has_skills']);

		$this->assertArrayHasKey('ModAgents', $discovered);
		$this->assertFalse($discovered['ModAgents']['has_guidelines']);
		$this->assertTrue($discovered['ModAgents']['has_skills']);

		$this->assertArrayHasKey('ModRootMd', $discovered);
		$this->assertTrue($discovered['ModRootMd']['has_guidelines']);
		$this->assertNull($discovered['ModRootMd']['guidelines_path']);

		$this->assertArrayHasKey('site-overrides', $discovered);
		$this->assertTrue($discovered['site-overrides']['has_guidelines']);

		$this->assertArrayNotHasKey('ModNonCanonicalBoost', $discovered);
		$this->assertArrayNotHasKey('ModBareDir', $discovered);
	}

	public function test_uninstall_agent_cleans_up_agent_files_and_preserves_other_agents(): void
	{
		$manager = new BoostManager($this->tempDir);
		$antigravity = new Antigravity();
		$cursor = new Cursor();
		$claude = new ClaudeCode();

		$manager->install(['guidelines', 'skills', 'mcp'], [], [$antigravity, $cursor, $claude]);

		$this->assertFileExists($this->tempDir . '/AGENTS.md');
		$this->assertFileExists($this->tempDir . '/.cursor/rules/processwire-boost.mdc');
		$this->assertFileExists($this->tempDir . '/CLAUDE.md');
		$this->assertDirectoryExists($this->tempDir . '/.cursor/skills/pw-pages');
		$this->assertFileExists($this->tempDir . '/.cursor/mcp.json');

		$manager->uninstallAgent($cursor);

		$this->assertFileExists($this->tempDir . '/AGENTS.md');
		$this->assertFileExists($this->tempDir . '/CLAUDE.md');
		$this->assertFileDoesNotExist($this->tempDir . '/.cursor/rules/processwire-boost.mdc');
		$this->assertDirectoryDoesNotExist($this->tempDir . '/.cursor/rules');
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

		mkdir($this->tempDir . '/.agents/skills', 0755, true);
		$detected = $manager->detectProjectAgents($this->tempDir);
		$this->assertNotContains('antigravity', $detected);

		file_put_contents($this->tempDir . '/.agents/mcp_config.json', '{}');
		$detected = $manager->detectProjectAgents($this->tempDir);
		$this->assertContains('antigravity', $detected);
	}

	public function test_uninstall_agent_without_mcp_removes_empty_parent_directory(): void
	{
		$manager = new BoostManager($this->tempDir);
		$cursor = new Cursor();
		$copilot = new \Totoglu\Console\Boost\Install\Agents\Copilot();

		$manager->install(['guidelines', 'skills'], [], [$cursor, $copilot]);
		$this->assertDirectoryExists($this->tempDir . '/.cursor/skills/pw-pages');
		$this->assertFileExists($this->tempDir . '/.github/copilot-instructions.md');
		$this->assertDirectoryExists($this->tempDir . '/.github/skills/pw-pages');

		$manager->uninstallAgent($cursor);
		$manager->uninstallAgent($copilot);

		$this->assertDirectoryDoesNotExist($this->tempDir . '/.cursor');
		$this->assertDirectoryDoesNotExist($this->tempDir . '/.github');
		$this->assertSame([], $manager->detectProjectAgents($this->tempDir));
	}

	public function test_instantiate_extender_entry_validates_concrete_field_schema_extender_via_reflection(): void
	{
		$manager = new BoostManager($this->tempDir);
		$method = new \ReflectionMethod(BoostManager::class, 'instantiateExtenderEntry');

		$this->assertNull($method->invoke($manager, ''));
		$this->assertNull($method->invoke($manager, 'Invalid-Class-Name!'));
		$this->assertNull($method->invoke($manager, \stdClass::class));
		$this->assertNull($method->invoke($manager, \Totoglu\Console\Boost\Schema\FieldSchemaExtender::class));

		$valid = $method->invoke($manager, \Totoglu\Console\Boost\Schema\Extenders\RepeaterFieldSchemaExtender::class);
		$this->assertInstanceOf(\Totoglu\Console\Boost\Schema\FieldSchemaExtender::class, $valid);

		$coreExtender = $method->invoke($manager, \Totoglu\Console\Boost\Schema\Extenders\CoreFieldSchemaExtender::class);
		$this->assertInstanceOf(\Totoglu\Console\Boost\Schema\FieldSchemaExtender::class, $coreExtender);
	}

	public function test_encode_compact_schema_map_produces_valid_one_entry_per_line_json(): void
	{
		$sampleMap = [
			'templates' => [
				'home' => ['id' => 1, 'pageClass' => 'HomePage', 'fields' => ['title', 'image']],
				'article' => ['id' => 29, 'pageClass' => 'DefaultPage', 'urlSegments' => true, 'allowPageNum' => true, 'fields' => ['title']],
			],
			'fields' => [
				'image' => [
					'id' => 612,
					'type' => 'FieldtypeImage',
					'label' => 'Image',
					'extra' => ['maxFiles' => 1, 'outputFormat' => 'auto', 'valueType' => 'Pageimage|null'],
				],
			],
			'modules' => [],
		];

		$encoded = BoostManager::encodeCompactSchemaMap($sampleMap);
		$decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

		$this->assertSame($sampleMap, $decoded);
		$this->assertLessThan(15, substr_count($encoded, "\n"));
		$this->assertStringContainsString('"image": {"id":612,"type":"FieldtypeImage"', $encoded);
	}

}

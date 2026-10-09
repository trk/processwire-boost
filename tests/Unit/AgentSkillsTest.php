<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Tests\Unit;

use Totoglu\Console\Boost\Install\Agents\Antigravity;
use Totoglu\Console\Boost\Install\Agents\ClaudeCode;
use Totoglu\Console\Boost\Install\Agents\Copilot;
use Totoglu\Console\Boost\Install\Agents\Cursor;
use Totoglu\Console\Boost\Install\Agents\Factory;
use Totoglu\Console\Boost\Install\Agents\GrokBuild;
use Totoglu\Console\Boost\Install\Agents\Junie;
use Totoglu\Console\Boost\Install\Agents\Kiro;
use Totoglu\Console\Boost\Install\Agents\OpenCode;
use Totoglu\Console\Boost\Install\Agents\Pi;
use Totoglu\Console\Boost\Install\Agents\Trae;
use Totoglu\Console\Boost\Install\Agents\Zed;
use Totoglu\Console\Boost\Tests\TestCase;

final class AgentSkillsTest extends TestCase
{
	public function test_agents_implementing_skills_have_correct_skills_path(): void
	{
		$expectations = [
			Antigravity::class => '.agents/skills',
			ClaudeCode::class => '.claude/skills',
			Copilot::class => '.github/skills',
			Cursor::class => '.cursor/skills',
			Factory::class => '.factory/skills',
			GrokBuild::class => '.grok/skills',
			Junie::class => '.junie/skills',
			Kiro::class => '.kiro/skills',
			OpenCode::class => '.opencode/skills',
			Pi::class => '.pi/skills',
			Trae::class => '.trae/skills',
			Zed::class => '.zed/skills',
		];

		foreach ($expectations as $agentClass => $expectedPath) {
			$agent = new $agentClass();
			$this->assertSame(
				$expectedPath,
				$agent->skillsPath(),
				sprintf('%s returned unexpected skillsPath()', $agentClass)
			);
		}
	}

	public function test_agent_export_skill_copies_full_directory(): void
	{
		$sourceDir = $this->tempDir . '/source-skill';
		mkdir($sourceDir . '/scripts', 0755, true);
		file_put_contents($sourceDir . '/SKILL.md', "# Test Skill\ndescription: A test skill body");
		file_put_contents($sourceDir . '/scripts/run.sh', "#!/bin/sh\necho 'hello'");

		$targetBase = $this->tempDir . '/agent-skills';
		mkdir($targetBase, 0755, true);

		$agent = new Cursor();
		$exportedFile = $agent->exportSkill('test-skill', $sourceDir, $targetBase);

		$this->assertSame($targetBase . '/test-skill/SKILL.md', $exportedFile);
		$this->assertFileExists($targetBase . '/test-skill/SKILL.md');
		$this->assertFileExists($targetBase . '/test-skill/scripts/run.sh');
		$this->assertStringContainsString('A test skill body', (string) file_get_contents($exportedFile));
	}

	public function test_trae_export_skill_adds_frontmatter_from_directory(): void
	{
		$sourceDir = $this->tempDir . '/source-skill';
		mkdir($sourceDir, 0755, true);
		file_put_contents($sourceDir . '/SKILL.md', "# Sample Trae Skill\ndescription: Sample description for Trae\n\nContent here");

		$targetBase = $this->tempDir . '/agent-skills';
		mkdir($targetBase, 0755, true);

		$agent = new Trae();
		$exportedFile = $agent->exportSkill('sample-skill', $sourceDir, $targetBase);

		$this->assertSame($targetBase . '/sample-skill/SKILL.md', $exportedFile);
		$this->assertFileExists($exportedFile);

		$content = (string) file_get_contents($exportedFile);
		$this->assertStringContainsString('---', $content);
		$this->assertStringContainsString('name: "sample-skill"', $content);
		$this->assertStringContainsString('description: "Sample description for Trae"', $content);
		$this->assertStringContainsString('Content here', $content);
	}

	public function test_trae_preserves_existing_frontmatter_without_duplication(): void
	{
		$sourceDir = $this->tempDir . '/source-with-frontmatter';
		mkdir($sourceDir, 0755, true);
		file_put_contents($sourceDir . '/SKILL.md', "---\nname: existing-skill\ndescription: Already formatted\n---\n\n# Body");

		$targetBase = $this->tempDir . '/trae-skills';
		mkdir($targetBase, 0755, true);

		$agent = new Trae();
		$exportedFile = $agent->exportSkill('existing-skill', $sourceDir, $targetBase);

		$content = (string) file_get_contents($exportedFile);
		$this->assertSame(2, substr_count($content, '---'), 'Should contain exactly opening and closing frontmatter markers');
		$this->assertStringContainsString('name: existing-skill', $content);
	}

	public function test_opencode_preserves_existing_frontmatter_and_injects_compatibility(): void
	{
		$sourceDir = $this->tempDir . '/source-with-frontmatter-opencode';
		mkdir($sourceDir, 0755, true);
		file_put_contents($sourceDir . '/SKILL.md', "---\nname: existing-skill\ndescription: Already formatted\n---\n\n# Body");

		$targetBase = $this->tempDir . '/opencode-skills';
		mkdir($targetBase, 0755, true);

		$agent = new OpenCode();
		$exportedFile = $agent->exportSkill('existing-skill', $sourceDir, $targetBase);

		$content = (string) file_get_contents($exportedFile);
		$this->assertSame(2, substr_count($content, '---'), 'Should contain exactly opening and closing frontmatter markers');
		$this->assertStringContainsString('compatibility: opencode', $content);
		$this->assertStringContainsString('name: existing-skill', $content);
	}
}

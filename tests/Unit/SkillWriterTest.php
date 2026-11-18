<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Tests\Unit;

use Totoglu\Console\Boost\Install\Agents\Antigravity;
use Totoglu\Console\Boost\Install\Agents\ClaudeCode;
use Totoglu\Console\Boost\Install\Agents\Cursor;
use Totoglu\Console\Boost\Install\Agents\Trae;
use Totoglu\Console\Boost\Install\Agents\Zed;
use Totoglu\Console\Boost\Install\SkillWriter;
use Totoglu\Console\Boost\Tests\TestCase;

final class SkillWriterTest extends TestCase
{
	public function test_sync_installs_canonical_skills_and_exports_to_agent_dirs_never_project_root(): void
	{
		$projectRoot = $this->tempDir;
		$targetDir = $projectRoot . '/.agents';
		mkdir($targetDir, 0755, true);

		$writer = new SkillWriter($targetDir, []);
		$agents = [
			new Cursor(),
			new ClaudeCode(),
			new Trae(),
			new Antigravity(),
		];

		$writer->sync([], $agents);

		// 1. Verify canonical skills exist under .agents/skills
		$this->assertDirectoryExists($targetDir . '/skills');
		$this->assertFileExists($targetDir . '/skills/pw-pages/SKILL.md');
		$this->assertFileExists($targetDir . '/skills/pw-expert/SKILL.md');

		// 2. Verify agent-specific exports exist in .{ide-editor}/skills
		$this->assertDirectoryExists($projectRoot . '/.cursor/skills/pw-pages');
		$this->assertFileExists($projectRoot . '/.cursor/skills/pw-pages/SKILL.md');

		$this->assertDirectoryExists($projectRoot . '/.claude/skills/pw-pages');
		$this->assertFileExists($projectRoot . '/.claude/skills/pw-pages/SKILL.md');

		$this->assertDirectoryExists($projectRoot . '/.trae/skills/pw-pages');
		$this->assertFileExists($projectRoot . '/.trae/skills/pw-pages/SKILL.md');

		// 3. CRITICAL: Verify NO skill directories were created in the project root
		$rootItems = scandir($projectRoot) ?: [];
		$forbiddenSkillDirs = ['pw-pages', 'pw-expert', 'pw-admin-ui', 'pw-migrations', 'pw-maintenance'];
		foreach ($forbiddenSkillDirs as $forbidden) {
			$this->assertNotContains(
				$forbidden,
				$rootItems,
				sprintf('Skill directory "%s" was unexpectedly created in project root!', $forbidden)
			);
			$this->assertFalse(
				is_dir($projectRoot . '/' . $forbidden),
				sprintf('Path "%s/%s" must NOT exist in project root!', $projectRoot, $forbidden)
			);
		}
	}

	public function test_remove_core_skills_cleans_canonical_and_agent_directories(): void
	{
		$projectRoot = $this->tempDir;
		$targetDir = $projectRoot . '/.agents';
		mkdir($targetDir, 0755, true);

		$writer = new SkillWriter($targetDir, []);
		$agents = [
			new Cursor(),
			new ClaudeCode(),
		];

		$writer->sync([], $agents);

		$this->assertFileExists($targetDir . '/skills/pw-pages/SKILL.md');
		$this->assertFileExists($projectRoot . '/.cursor/skills/pw-pages/SKILL.md');
		$this->assertFileExists($projectRoot . '/.claude/skills/pw-pages/SKILL.md');

		// Now remove
		$writer->removeCoreSkills($agents);

		$this->assertFalse(is_dir($targetDir . '/skills/pw-pages'));
		$this->assertFalse(is_dir($projectRoot . '/.cursor/skills'));
		$this->assertFalse(is_dir($projectRoot . '/.claude/skills'));
	}

	public function test_sync_includes_discoverable_modules(): void
	{
		$projectRoot = $this->tempDir;
		$targetDir = $projectRoot . '/.agents';
		mkdir($targetDir, 0755, true);

		$mockModuleSkillsDir = $this->tempDir . '/mock-module/skills';
		mkdir($mockModuleSkillsDir . '/custom-module-skill', 0755, true);
		file_put_contents(
			$mockModuleSkillsDir . '/custom-module-skill/SKILL.md',
			"# Custom Module Skill\ndescription: A mock module skill"
		);

		$discoverable = [
			'MockModule' => [
				'path' => $this->tempDir . '/mock-module',
				'guidelines_path' => null,
				'skills_path' => $mockModuleSkillsDir,
				'has_guidelines' => false,
				'has_skills' => true,
			],
		];

		$writer = new SkillWriter($targetDir, $discoverable);
		$agents = [new Cursor()];

		$writer->sync(['MockModule'], $agents);

		$this->assertFileExists($targetDir . '/skills/custom-module-skill/SKILL.md');
		$this->assertFileExists($projectRoot . '/.cursor/skills/custom-module-skill/SKILL.md');
		$this->assertFalse(is_dir($projectRoot . '/custom-module-skill'));

		// Place a remote skill in .agents/skills and deselect MockModule:
		// MockModule's skill is pruned while the remote skill is preserved and exported to .cursor/skills.
		mkdir($targetDir . '/skills/remote-custom-skill', 0755, true);
		file_put_contents(
			$targetDir . '/skills/remote-custom-skill/SKILL.md',
			"# Remote Custom Skill\ndescription: Installed via boost:add-skill"
		);

		$writer->sync([], $agents);

		$this->assertFalse(is_dir($targetDir . '/skills/custom-module-skill'));
		$this->assertFalse(is_dir($projectRoot . '/.cursor/skills/custom-module-skill'));
		$this->assertFileExists($targetDir . '/skills/remote-custom-skill/SKILL.md');
		$this->assertFileExists($projectRoot . '/.cursor/skills/remote-custom-skill/SKILL.md');
	}

	public function test_delete_tree_unlinks_directory_symlinks_without_deleting_external_targets(): void
	{
		$externalDir = $this->tempDir . '/external-keep';
		mkdir($externalDir, 0755, true);
		file_put_contents($externalDir . '/precious.txt', 'keep-me');

		$skillDir = $this->tempDir . '/removable-skill';
		mkdir($skillDir, 0755, true);
		symlink($externalDir, $skillDir . '/linked-external');

		SkillWriter::deleteTree($skillDir);

		$this->assertDirectoryDoesNotExist($skillDir);
		$this->assertFileExists($externalDir . '/precious.txt');
	}

	public function test_sanitize_skill_name_and_extract_frontmatter_description(): void
	{
		$this->assertSame('', SkillWriter::sanitizeSkillName('..'));
		$this->assertSame('', SkillWriter::sanitizeSkillName('...'));
		$this->assertSame('pw-pages', SkillWriter::sanitizeSkillName('../pw-pages/'));

		$markdown = "---\nname: pw-demo\ndescription: \"Use when testing \\\"quoted\\\" YAML descriptions.\"\n---\n# Demo\n";
		$this->assertSame(
			'Use when testing "quoted" YAML descriptions.',
			SkillWriter::extractFrontmatterDescription($markdown)
		);
	}

	public function test_remove_agent_skills_preserves_user_authored_skills_and_zed_claude_omit_cwd(): void
	{
		$projectRoot = $this->tempDir;
		$targetDir = $projectRoot . '/.agents';
		mkdir($targetDir, 0755, true);

		$claude = new ClaudeCode();
		$zed = new Zed();

		$this->assertArrayNotHasKey('cwd', $claude->mcpServerConfig('php', ['vendor/bin/wire', 'boost:mcp'], [], '/tmp/project'));
		$this->assertArrayNotHasKey('cwd', $zed->mcpServerConfig('php', ['vendor/bin/wire', 'boost:mcp'], [], '/tmp/project'));

		$writer = new SkillWriter($targetDir, []);
		$writer->sync([], [$claude]);

		mkdir($projectRoot . '/.claude/skills/my-team-custom-skill', 0755, true);
		file_put_contents($projectRoot . '/.claude/skills/my-team-custom-skill/SKILL.md', "# Custom\n");

		$writer->removeAgentSkills($claude);

		$this->assertDirectoryDoesNotExist($projectRoot . '/.claude/skills/pw-pages');
		$this->assertFileExists($projectRoot . '/.claude/skills/my-team-custom-skill/SKILL.md');
	}
}

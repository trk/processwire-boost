<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Tests\Unit;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Totoglu\Console\Boost\BoostManager;
use Totoglu\Console\Boost\Commands\BoostInstallCommand;
use Totoglu\Console\Boost\Commands\BoostUpdateCommand;
use Totoglu\Console\Boost\Install\Agents\Cursor;
use Totoglu\Console\Boost\Install\GuidelineWriter;
use Totoglu\Console\Boost\Install\Mcp\FileWriter;
use Totoglu\Console\Boost\Skills\Remote\GitHubRepository;
use Totoglu\Console\Boost\Skills\Remote\GitHubSkillProvider;
use Totoglu\Console\Boost\Skills\Remote\RemoteSkill;
use Totoglu\Console\Boost\Skills\Remote\Risk;
use Totoglu\Console\Boost\Skills\Remote\SkillAuditor;
use Totoglu\Console\Boost\Tests\TestCase;

final class BoostSecurityAndCommandsTest extends TestCase
{
	public function test_skill_auditor_flags_trojan_source_zero_width_obfuscation_binary_and_empty_fetch(): void
	{
		$repo = GitHubRepository::fromInput('acme/skills');

		$provider = new GitHubSkillProvider($repo, static function (string $url) {
			if (str_ends_with($url, '/repos/acme/skills')) {
				return json_encode(['default_branch' => 'main']);
			}
			if (str_contains($url, '/git/trees/')) {
				return json_encode([
					'truncated' => false,
					'tree' => [
						['type' => 'blob', 'path' => 'bidi-skill/SKILL.md', 'size' => 50],
						['type' => 'blob', 'path' => 'zw-skill/SKILL.md', 'size' => 50],
						['type' => 'blob', 'path' => 'b64-skill/SKILL.md', 'size' => 50],
						['type' => 'blob', 'path' => 'bin-skill/SKILL.md', 'size' => 50],
					],
				]);
			}
			if (str_ends_with($url, '/bidi-skill/SKILL.md')) {
				return "# Title\nSafe looking text \u{202E}hidden";
			}
			if (str_ends_with($url, '/zw-skill/SKILL.md')) {
				return "# Title\nZero\u{200B}width";
			}
			if (str_ends_with($url, '/b64-skill/SKILL.md')) {
				return "<?php base64_decode('cGF5bG9hZA==');";
			}
			if (str_ends_with($url, '/bin-skill/SKILL.md')) {
				return "\xFF\xFE\x00\x01";
			}

			return false;
		});

		$auditor = new SkillAuditor();
		$skills = [
			'bidi-skill' => new RemoteSkill('bidi-skill', 'acme/skills', 'bidi-skill'),
			'zw-skill' => new RemoteSkill('zw-skill', 'acme/skills', 'zw-skill'),
			'b64-skill' => new RemoteSkill('b64-skill', 'acme/skills', 'b64-skill'),
			'bin-skill' => new RemoteSkill('bin-skill', 'acme/skills', 'bin-skill'),
			'missing-skill' => new RemoteSkill('missing-skill', 'acme/skills', 'missing-skill'),
		];

		$results = $auditor->audit($provider, $skills);

		$this->assertSame(Risk::Critical, $results['bidi-skill']->risk);
		$this->assertSame(Risk::Medium, $results['zw-skill']->risk);
		$this->assertSame(Risk::High, $results['b64-skill']->risk);
		$this->assertSame(Risk::High, $results['bin-skill']->risk);
		$this->assertSame(Risk::High, $results['missing-skill']->risk);
	}

	public function test_symlink_rejection_in_file_writer_boost_manager_and_guideline_writer(): void
	{
		$realTarget = $this->tempDir . '/secret.txt';
		file_put_contents($realTarget, 'DO_NOT_OVERWRITE');

		$mcpLink = $this->tempDir . '/linked-mcp.json';
		symlink($realTarget, $mcpLink);

		$writer = new FileWriter($mcpLink);
		$writer->addServerConfig('processwire', ['command' => 'php', 'args' => ['vendor/bin/wire', 'boost:mcp']]);
		$this->assertFalse($writer->save());
		$this->assertSame('DO_NOT_OVERWRITE', file_get_contents($realTarget));

		mkdir($this->tempDir . '/.agents', 0755, true);
		$boostJsonLink = $this->tempDir . '/.agents/boost.json';
		symlink($realTarget, $boostJsonLink);

		$manager = new BoostManager($this->tempDir);
		$threwOnBoostJson = false;
		try {
			$manager->saveConfig(['guidelines' => true]);
		} catch (RuntimeException $e) {
			$threwOnBoostJson = true;
			$this->assertStringContainsString('symbolic link', $e->getMessage());
		}
		$this->assertTrue($threwOnBoostJson, 'Expected RuntimeException when .agents/boost.json is a symlink.');
		$this->assertSame('DO_NOT_OVERWRITE', file_get_contents($realTarget));

		unlink($boostJsonLink);
		$agentsLink = $this->tempDir . '/AGENTS.md';
		symlink($realTarget, $agentsLink);

		$guidelineWriter = new GuidelineWriter(
			$this->tempDir,
			$this->tempDir . '/.agents',
			[]
		);

		$threwOnAgentsMd = false;
		try {
			$guidelineWriter->write([new \Totoglu\Console\Boost\Install\Agents\Antigravity()], []);
		} catch (RuntimeException $e) {
			$threwOnAgentsMd = true;
			$this->assertStringContainsString('symbolic link', $e->getMessage());
		}
		$this->assertTrue($threwOnAgentsMd, 'Expected RuntimeException when AGENTS.md is a symlink.');
		$this->assertSame('DO_NOT_OVERWRITE', file_get_contents($realTarget));
	}

	public function test_boost_commands_return_structured_json_envelopes_in_temp_workspace(): void
	{
		$previousCwd = getcwd();
		$this->assertIsString($previousCwd);
		chdir($this->tempDir);

		try {
			$app = new Application();
			$updateCmd = new BoostUpdateCommand();
			$installCmd = new BoostInstallCommand();
			$app->addCommands([$updateCmd, $installCmd]);

			$updateTester = new CommandTester($updateCmd);
			$exit = $updateTester->execute(['--json' => true], ['interactive' => false]);
			$this->assertSame(Command::FAILURE, $exit);
			$decoded = json_decode($updateTester->getDisplay(), true);
			$this->assertIsArray($decoded);
			$this->assertFalse($decoded['ok']);
			$this->assertSame('NOT_CONFIGURED', $decoded['error']['code']);

			$exitConflict = $updateTester->execute([
				'--discover' => true,
				'--no-discover' => true,
				'--json' => true,
			], ['interactive' => false]);
			$this->assertSame(Command::FAILURE, $exitConflict);
			$decodedConflict = json_decode($updateTester->getDisplay(), true);
			$this->assertIsArray($decodedConflict);
			$this->assertSame('INVALID_OPTIONS', $decodedConflict['error']['code']);

			$installTester = new CommandTester($installCmd);
			$exitPartialInvalidAgent = $installTester->execute([
				'--guidelines' => true,
				'--agents' => 'cursor,nonexistent_agent',
				'--json' => true,
			], ['interactive' => false]);
			$this->assertSame(Command::FAILURE, $exitPartialInvalidAgent);
			$decodedInvalid = json_decode($installTester->getDisplay(), true);
			$this->assertIsArray($decodedInvalid);
			$this->assertSame('INVALID_AGENTS', $decodedInvalid['error']['code']);

			$exitInvalidModule = $installTester->execute([
				'--guidelines' => true,
				'--agents' => 'cursor',
				'--modules' => 'NonExistentModule',
				'--json' => true,
			], ['interactive' => false]);
			$this->assertSame(Command::FAILURE, $exitInvalidModule);
			$decodedMod = json_decode($installTester->getDisplay(), true);
			$this->assertIsArray($decodedMod);
			$this->assertSame('INVALID_MODULES', $decodedMod['error']['code']);

			$exitInstall = $installTester->execute([
				'--guidelines' => true,
				'--skills' => true,
				'--agents' => 'cursor',
				'--json' => true,
			], ['interactive' => false]);
			$this->assertSame(Command::SUCCESS, $exitInstall);
			$decodedInstall = json_decode($installTester->getDisplay(), true);
			$this->assertIsArray($decodedInstall);
			$this->assertTrue($decodedInstall['ok']);
			$this->assertFileExists($this->tempDir . '/.agents/boost.json');
			$this->assertFileExists($this->tempDir . '/.cursor/rules/processwire-boost.mdc');
			$this->assertFileDoesNotExist($this->tempDir . '/AGENTS.md');
		} finally {
			chdir($previousCwd);
		}
	}

	public function test_github_repository_ref_preservation_encoded_traversal_rejection_and_toml_multiline_cleanup(): void
	{
		$repo = GitHubRepository::fromInput('https://github.com/acme/skills/tree/v2.1.0/nested');
		$this->assertSame('acme', $repo->owner);
		$this->assertSame('skills', $repo->repo);
		$this->assertSame('nested', $repo->path);
		$this->assertSame('v2.1.0', $repo->ref);

		$threwTraversal = false;
		try {
			GitHubRepository::fromInput('acme/skills/%2e%2e/etc');
		} catch (InvalidArgumentException) {
			$threwTraversal = true;
		}
		$this->assertTrue($threwTraversal, 'URL-encoded .. segment must be rejected.');

		$requestedUrls = [];
		$provider = new GitHubSkillProvider($repo, static function (string $url) use (&$requestedUrls) {
			$requestedUrls[] = $url;
			if (str_contains($url, '/git/trees/v2.1.0')) {
				return json_encode([
					'truncated' => false,
					'tree' => [
						['type' => 'blob', 'mode' => '100644', 'path' => 'nested/clean-skill/SKILL.md', 'size' => 20],
						['type' => 'blob', 'mode' => '120000', 'path' => 'nested/clean-skill/linked.md', 'size' => 10],
					],
				]);
			}
			if (str_ends_with($url, '/v2.1.0/nested/clean-skill/SKILL.md')) {
				return "# Clean\n";
			}

			return false;
		});

		$discovered = $provider->discoverSkills();
		$this->assertArrayHasKey('clean-skill', $discovered);
		$files = $provider->fetchSkillFiles($discovered['clean-skill']);
		$this->assertSame(['SKILL.md' => "# Clean\n"], $files);
		$this->assertNotEmpty($requestedUrls);

		$blobRepo = GitHubRepository::fromInput('https://github.com/acme/skills/blob/v2.1.0/nested/clean-skill/SKILL.md');
		$blobProvider = new GitHubSkillProvider($blobRepo, static function (string $url) {
			if (str_contains($url, '/git/trees/v2.1.0')) {
				return json_encode([
					'truncated' => false,
					'tree' => [
						['type' => 'blob', 'mode' => '100644', 'path' => 'nested/clean-skill/SKILL.md', 'size' => 20],
						['type' => 'blob', 'mode' => '100644', 'path' => 'nested/other-skill/SKILL.md', 'size' => 20],
					],
				]);
			}

			return false;
		});
		$blobSkills = $blobProvider->discoverSkills();
		$this->assertSame(['clean-skill'], array_keys($blobSkills));

		$tomlPath = $this->tempDir . '/config.toml';
		file_put_contents(
			$tomlPath,
			"[mcp_servers.processwire]\ncommand = \"php\"\nargs = [\n  \"vendor/bin/wire\",\n  \"boost:mcp\"\n]\n\n[mcp_servers.processwire.env]\nFOO = \"bar\"\n\n[projects.\"/Users/me/my-app\"]\ntrust_level = \"trusted\"\n\n[[profiles]]\nname = \"default\"\n"
		);

		$writer = new FileWriter($tomlPath);
		$this->assertTrue($writer->configKey('mcp_servers')->remove('processwire'));

		$remaining = (string) file_get_contents($tomlPath);
		$this->assertStringNotContainsString('processwire', $remaining);
		$this->assertStringNotContainsString('boost:mcp', $remaining);
		$this->assertStringContainsString('[projects."/Users/me/my-app"]', $remaining);
		$this->assertStringContainsString('[[profiles]]', $remaining);
	}
}

<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Tests\Unit;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Totoglu\Console\Boost\BoostManager;
use Totoglu\Console\Boost\Commands\BoostValidateCommand;
use Totoglu\Console\Boost\Install\Agents\Antigravity;
use Totoglu\Console\Boost\Schema\Extenders\RepeaterFieldSchemaExtender;
use Totoglu\Console\Boost\Tests\TestCase;
use Totoglu\Console\Boost\Validator\ModuleResourceValidator;

final class ModuleResourceValidatorTest extends TestCase
{
	public function test_discovers_site_module_with_only_api_md_and_ignores_core_wire_api_md(): void
	{
		mkdir($this->tempDir . '/site/modules/AssetBundler', 0755, true);
		file_put_contents(
			$this->tempDir . '/site/modules/AssetBundler/API.md',
			"# AssetBundler\n\nResolves versioned frontend asset manifests and SRI attributes.\n"
		);

		mkdir($this->tempDir . '/site/modules/ShopCatalog', 0755, true);
		file_put_contents(
			$this->tempDir . '/site/modules/ShopCatalog/AGENTS.md',
			"---\ndescription: \"Internal catalog domain architecture.\"\n---\n\n# ShopCatalog Architecture\n"
		);
		file_put_contents(
			$this->tempDir . '/site/modules/ShopCatalog/API.md',
			"# ShopCatalog\n\nPublic catalog query API.\n"
		);

		mkdir($this->tempDir . '/wire/modules/FieldtypeText', 0755, true);
		file_put_contents($this->tempDir . '/wire/modules/FieldtypeText/API.md', "# FieldtypeText\n\nCore text field.\n");

		$manager = new BoostManager($this->tempDir);
		$discovered = $manager->getDiscoverableModules();

		$this->assertArrayHasKey('AssetBundler', $discovered);
		$this->assertArrayHasKey('ShopCatalog', $discovered);
		$this->assertArrayNotHasKey('FieldtypeText', $discovered);

		$manager->install(['guidelines'], ['AssetBundler', 'ShopCatalog'], [new Antigravity()]);

		$agentsMd = (string) file_get_contents($this->tempDir . '/AGENTS.md');
		$this->assertStringContainsString('| **AssetBundler** | Resolves versioned frontend asset manifests and SRI attributes. | API: `site/modules/AssetBundler/API.md` |', $agentsMd);
		$this->assertStringContainsString('| **ShopCatalog** | Internal catalog domain architecture. | API: `site/modules/ShopCatalog/API.md`<br>Architecture: `site/modules/ShopCatalog/AGENTS.md` |', $agentsMd);
	}

	public function test_validator_passes_compliant_module_with_zero_issues(): void
	{
		$modDir = $this->tempDir . '/site/modules/ShopCatalog';
		mkdir($modDir . '/.agents/guidelines', 0755, true);
		mkdir($modDir . '/.agents/skills/pw-shop-catalog/references', 0755, true);
		mkdir($modDir . '/.agents/schema', 0755, true);

		file_put_contents(
			$modDir . '/API.md',
			"# ShopCatalog\n\nCatalog service reference.\n\n```php\n<?php\n\ndeclare(strict_types=1);\n\nif (\$ok) {\n\t\$catalog->sync();\n}\n```\n"
		);
		file_put_contents(
			$modDir . '/AGENTS.md',
			"---\ndescription: \"Architecture guide for ShopCatalog internals.\"\n---\n\n# ShopCatalog Internals\n"
		);
		file_put_contents(
			$modDir . '/.agents/guidelines/global.md',
			"# Global Catalog Rule\n\nAlways resolve prices through `CatalogPrice`.\n"
		);
		file_put_contents(
			$modDir . '/.agents/skills/pw-shop-catalog/SKILL.md',
			"---\nname: pw-shop-catalog\ndescription: \"Use when querying products, prices, or inventory via ShopCatalog.\"\n---\n\n# Shop Catalog\n\n```php\n<?php\n\ndeclare(strict_types=1);\n\nif (\$ready) {\n\t\$items = \$wire->catalog->find('limit=10');\n}\n```\n"
		);
		file_put_contents(
			$modDir . '/.agents/skills/pw-shop-catalog/references/pricing.md',
			"# Pricing Reference\n\n```php\n<?php\n\nif (\$taxed) {\n\t\$total = 100;\n}\n```\n"
		);
		file_put_contents(
			$modDir . '/.agents/schema/field-extenders.php',
			"<?php\n\ndeclare(strict_types=1);\n\nreturn [\n\t\\" . RepeaterFieldSchemaExtender::class . "::class,\n];\n"
		);

		$validator = new ModuleResourceValidator($this->tempDir);
		$report = $validator->validate(null, true);

		$this->assertTrue($report['valid']);
		$this->assertSame(1, $report['checked']);
		$this->assertSame(0, $report['errors']);
		$this->assertSame(0, $report['warnings']);
	}

	public function test_validator_and_cli_command_enforce_warnings_strict_mode_and_hard_errors(): void
	{
		$warnMod = $this->tempDir . '/site/modules/WarnModule';
		mkdir($warnMod . '/.agents/guidelines', 0755, true);
		mkdir($warnMod . '/.agents/skills/custom-skill', 0755, true);

		file_put_contents($warnMod . '/AGENTS.md', "# WarnModule Without Frontmatter\n\nInternal notes.\n");
		file_put_contents(
			$warnMod . '/.agents/guidelines/bloated.md',
			implode("\n", array_fill(0, 35, '- Rule line item'))
		);
		file_put_contents(
			$warnMod . '/.agents/skills/custom-skill/SKILL.md',
			"---\nname: custom-skill\ndescription: \"Builds widgets.\"\n---\n\n# Custom Skill\n"
		);

		$prevCwd = getcwd() ?: '.';
		chdir($this->tempDir);
		try {
			$app = new Application();
			$command = new BoostValidateCommand();
			$app->addCommands([$command]);

			$tester = new CommandTester($command);

			// Non-strict mode: warnings pass with ok=true
			$exit = $tester->execute(['--module' => 'WarnModule', '--json' => true]);
			$this->assertSame(Command::SUCCESS, $exit);
			$payload = json_decode($tester->getDisplay(), true);
			$this->assertTrue($payload['ok']);
			$this->assertSame(0, $payload['data']['errors']);
			$this->assertGreaterThanOrEqual(4, $payload['data']['warnings']);

			// Strict mode: warnings become errors and return FAILURE
			$strictExit = $tester->execute(['--module' => 'WarnModule', '--strict' => true, '--json' => true]);
			$this->assertSame(Command::FAILURE, $strictExit);
			$strictPayload = json_decode($tester->getDisplay(), true);
			$this->assertFalse($strictPayload['ok']);
			$this->assertSame('VALIDATION_FAILED', $strictPayload['error']['code']);

			// Hard errors: missing H1 in API.md, mismatched skill name, space-indented PHP, Trojan Unicode, symlink, invalid extender
			$badMod = $this->tempDir . '/site/modules/BrokenModule';
			mkdir($badMod . '/.agents/skills/pw-broken', 0755, true);
			mkdir($badMod . '/.agents/schema', 0755, true);

			file_put_contents($badMod . '/API.md', "No H1 heading here.\n");
			file_put_contents(
				$badMod . '/.agents/skills/pw-broken/SKILL.md',
				"---\nname: pw-wrong-name\ndescription: \"Use when testing broken skills.\"\n---\n\n```php\n<?php\n    \$spaceIndented = true;\n```\n"
			);
			file_put_contents($badMod . '/AGENTS.md', "---\ndescription: \"Use safely\"\n---\nHidden \u{202E} bidi text");
			file_put_contents($badMod . '/.agents/schema/field-extenders.php', "<?php\nreturn [\\stdClass::class];\n");

			$secretTarget = $this->tempDir . '/secret.md';
			file_put_contents($secretTarget, '# Secret');
			mkdir($badMod . '/.agents/guidelines', 0755, true);
			symlink($secretTarget, $badMod . '/.agents/guidelines/linked.md');

			$badExit = $tester->execute(['--module' => 'BrokenModule', '--json' => true]);
			$this->assertSame(Command::FAILURE, $badExit);
			$badPayload = json_decode($tester->getDisplay(), true);
			$this->assertFalse($badPayload['ok']);
			$codes = array_column($badPayload['data']['targets'][0]['issues'], 'code');
			$this->assertContains('API_MISSING_H1', $codes);
			$this->assertContains('SKILL_NAME_MISMATCH', $codes);
			$this->assertContains('PHP_SPACE_INDENTATION', $codes);
			$this->assertContains('TROJAN_UNICODE', $codes);
			$this->assertContains('EXTENDER_INVALID_CLASS', $codes);
			$this->assertContains('SYMLINK_FORBIDDEN', $codes);
		} finally {
			chdir($prevCwd);
		}
	}
}

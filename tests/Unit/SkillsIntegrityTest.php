<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Tests\Unit;

use Totoglu\Console\Boost\Tests\TestCase;

final class SkillsIntegrityTest extends TestCase
{
	private string $skillsDir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->skillsDir = dirname(__DIR__, 2) . '/resources/boost/skills';
	}

	public function test_all_18_skills_exist_with_valid_frontmatter(): void
	{
		$this->assertDirectoryExists($this->skillsDir);

		$expectedSkills = [
			'pw-admin-ui',
			'pw-brainstorming',
			'pw-custom-page-classes',
			'pw-docs-architect',
			'pw-expert',
			'pw-maintenance',
			'pw-migrations',
			'pw-module-development',
			'pw-pages',
			'pw-queue',
			'pw-scheduling',
			'pw-security-audit',
			'pw-seeder',
			'pw-systematic-debugging',
			'pw-test',
			'pw-url-routing',
			'pw-writing-plans',
			'pw-writing-skills',
		];

		$actualSkills = array_values(array_filter(
			scandir($this->skillsDir) ?: [],
			fn(string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($this->skillsDir . '/' . $entry)
		));
		sort($actualSkills);
		$this->assertSame($expectedSkills, $actualSkills);

		foreach ($expectedSkills as $skillName) {
			$skillPath = $this->skillsDir . '/' . $skillName;
			$this->assertDirectoryExists($skillPath, "Missing skill folder: {$skillName}");

			$skillFile = $skillPath . '/SKILL.md';
			$this->assertFileExists($skillFile, "Missing SKILL.md for {$skillName}");

			$content = (string) file_get_contents($skillFile);

			// Check YAML frontmatter
			$this->assertMatchesRegularExpression('/^---\s*\n.*?\n---/s', $content, "Missing YAML frontmatter in {$skillName}/SKILL.md");
			$this->assertMatchesRegularExpression('/^name:\s*' . preg_quote($skillName, '/') . '$/m', $content, "Missing or mismatched 'name' in {$skillName}/SKILL.md");
			$this->assertMatchesRegularExpression('/^description:\s*.+$/m', $content, "Missing 'description' in {$skillName}/SKILL.md");
		}
	}

	public function test_no_skills_contain_broken_tabs_in_code_blocks(): void
	{
		$skills = scandir($this->skillsDir) ?: [];
		foreach ($skills as $skill) {
			if ($skill === '.' || $skill === '..' || !is_dir($this->skillsDir . '/' . $skill)) {
				continue;
			}

			$file = $this->skillsDir . '/' . $skill . '/SKILL.md';
			$content = (string) file_get_contents($file);

			$this->assertStringNotContainsString("Laravel\\Prompts\t", $content, "Broken tab in Laravel Prompts in {$skill}");
		}
	}

	public function test_pw_module_development_enforces_scoped_api_access(): void
	{
		$skillFile = $this->skillsDir . '/pw-module-development/SKILL.md';
		$content = (string) file_get_contents($skillFile);

		$this->assertStringNotContainsString('use direct property access like $this->pages', $content);
		$this->assertStringContainsString('$this->wire()->pages', $content);
		$this->assertStringContainsString('findMany', $content);
		$this->assertStringContainsString('classLoader->addNamespace', $content);
		$this->assertStringContainsString('ReflectionClass', $content);
		$this->assertStringContainsString('references/fieldtype-inputfield.md', $content);
		$this->assertStringContainsString('references/process.md', $content);
		$this->assertStringContainsString('references/textformatter.md', $content);
		$this->assertStringContainsString('references/filevalidator.md', $content);
	}

	public function test_pw_module_fieldtype_inputfield_reference_includes_companion_field_class(): void
	{
		$refFile = $this->skillsDir . '/pw-module-development/references/fieldtype-inputfield.md';
		$this->assertFileExists($refFile);
		$content = (string) file_get_contents($refFile);

		$this->assertStringContainsString('getFieldClass', $content);
		$this->assertStringContainsString('CustomField extends Field', $content);
		$this->assertStringContainsString('getMaxIndexLength', $content);
		$this->assertStringNotContainsString("require_once __DIR__ . '/CustomField.php'", $content);
		$this->assertStringContainsString('API.md', $content);
	}

	public function test_pw_pages_includes_modern_page_methods(): void
	{
		$skillFile = $this->skillsDir . '/pw-pages/SKILL.md';
		$content = (string) file_get_contents($skillFile);

		$this->assertStringContainsString('findOne', $content);
		$this->assertStringContainsString('findMany', $content);
		$this->assertStringContainsString('has(', $content);
		$this->assertStringContainsString('setAndSave', $content);
		$this->assertStringContainsString('include=trash', $content);
		$this->assertStringContainsString('setLanguageValue', $content);
		$this->assertStringContainsString('localUrl', $content);
	}

	public function test_docs_architect_has_correct_companion_class(): void
	{
		$skillFile = $this->skillsDir . '/pw-docs-architect/SKILL.md';
		$content = (string) file_get_contents($skillFile);

		$this->assertStringNotContainsString('class FieldtypeMyCustomField extends Field', $content);
		$this->assertStringContainsString('class MyCustomField extends Field', $content);
		$this->assertStringContainsString('php index.php docs', $content);
	}
	public function test_no_skills_contain_copy_paste_prompts_bloat(): void
	{
		$skills = scandir($this->skillsDir) ?: [];
		foreach ($skills as $skill) {
			if ($skill === '.' || $skill === '..' || !is_dir($this->skillsDir . '/' . $skill)) {
				continue;
			}

			$file = $this->skillsDir . '/' . $skill . '/SKILL.md';
			$content = (string) file_get_contents($file);

			$this->assertStringNotContainsString('## Copy-Paste Prompts', $content, "Disallowed 'Copy-Paste Prompts' found in {$skill}");
		}
	}

	public function test_pw_module_filevalidator_reference_extends_core_file_validator_module(): void
	{
		$refFile = $this->skillsDir . '/pw-module-development/references/filevalidator.md';
		$this->assertFileExists($refFile);
		$content = (string) file_get_contents($refFile);

		$this->assertStringContainsString('FileValidatorModule', $content);
		$this->assertStringContainsString('isValidFile', $content);
		$this->assertStringContainsString('validates', $content);
		$this->assertStringContainsString('wire/core/Module/FileValidatorModule/API.md', $content);
	}

	public function test_pw_module_textformatter_reference_implements_core_signatures(): void
	{
		$refFile = $this->skillsDir . '/pw-module-development/references/textformatter.md';
		$this->assertFileExists($refFile);
		$content = (string) file_get_contents($refFile);

		$this->assertStringContainsString('extends Textformatter', $content);
		$this->assertStringContainsString('format(&$str)', $content);
		$this->assertStringContainsString('formatValue', $content);
		$this->assertStringContainsString('wire/core/Module/Textformatter', $content);
	}

	public function test_pw_module_process_reference_references_core_process_api(): void
	{
		$refFile = $this->skillsDir . '/pw-module-development/references/process.md';
		$this->assertFileExists($refFile);
		$content = (string) file_get_contents($refFile);

		$this->assertStringContainsString('extends Process', $content);
		$this->assertStringContainsString('___execute', $content);
		$this->assertStringContainsString('wire/core/Module/Process/API.md', $content);
	}

	public function test_pw_admin_ui_implements_canonical_design_system(): void
	{
		$skillFile = $this->skillsDir . '/pw-admin-ui/SKILL.md';
		$content = (string) file_get_contents($skillFile);

		$this->assertStringContainsString('pw-module-workspace', $content);
		$this->assertStringContainsString('KONKAT_THEME.md', $content);
		$this->assertStringContainsString('PageListActions actions', $content);
		$this->assertStringNotContainsString('pw-pagelist-actions', $content);
		$this->assertStringContainsString('Inputfields', $content);
		$this->assertStringContainsString('InputfieldForm', $content);
		$this->assertStringContainsString('--pw-main-color', $content);
		$this->assertStringContainsString('--pw-border-color', $content);
		$this->assertStringContainsString('pw-button-dropdown-wrap', $content);
	}

	public function test_all_skills_use_cso_trigger_descriptions_and_tab_indented_php_blocks(): void
	{
		$skills = scandir($this->skillsDir) ?: [];
		foreach ($skills as $skill) {
			if ($skill === '.' || $skill === '..' || !is_dir($this->skillsDir . '/' . $skill)) {
				continue;
			}

			$file = $this->skillsDir . '/' . $skill . '/SKILL.md';
			$content = (string) file_get_contents($file);

			$this->assertMatchesRegularExpression(
				'/^description:\s*"?Use when\s+/m',
				$content,
				"Skill {$skill} description must start with 'Use when '"
			);

			if (preg_match_all('/```php\n(.*?)\n```/s', $content, $matches)) {
				foreach ($matches[1] as $blockIndex => $phpBlock) {
					foreach (explode("\n", $phpBlock) as $lineNum => $line) {
						$this->assertDoesNotMatchRegularExpression(
							'/^ +(?!\*)/',
							$line,
							sprintf('Space-indented PHP line in %s block #%d line %d: %s', $skill, $blockIndex + 1, $lineNum + 1, $line)
						);
					}
				}
			}

			$refsDir = $this->skillsDir . '/' . $skill . '/references';
			if (is_dir($refsDir)) {
				foreach (scandir($refsDir) ?: [] as $refName) {
					if (!str_ends_with($refName, '.md')) {
						continue;
					}
					$refContent = (string) file_get_contents($refsDir . '/' . $refName);
					if (preg_match_all('/```php\n(.*?)\n```/s', $refContent, $refMatches)) {
						foreach ($refMatches[1] as $blockIndex => $phpBlock) {
							foreach (explode("\n", $phpBlock) as $lineNum => $line) {
								$this->assertDoesNotMatchRegularExpression(
									'/^ +(?!\*)/',
									$line,
									sprintf('Space-indented PHP line in %s/references/%s block #%d line %d: %s', $skill, $refName, $blockIndex + 1, $lineNum + 1, $line)
								);
							}
						}
					}
				}
			}
		}
	}

	public function test_skills_align_with_processwire_console_runtime_contracts(): void
	{
		$maintenance = (string) file_get_contents($this->skillsDir . '/pw-maintenance/SKILL.md');
		$this->assertStringContainsString("\$config->paths->site . 'down.json'", $maintenance);
		$this->assertStringNotContainsString("\$config->paths->assets . 'down.json'", $maintenance);

		$queue = (string) file_get_contents($this->skillsDir . '/pw-queue/SKILL.md');
		$this->assertStringContainsString('queue_jobs', $queue);
		$this->assertStringContainsString('failed_jobs', $queue);
		$this->assertStringNotContainsString('file-based queues', $queue);

		$plans = (string) file_get_contents($this->skillsDir . '/pw-writing-plans/SKILL.md');
		$this->assertStringContainsString('wire()->fields', $plans);
		$this->assertStringNotContainsString('$this->wire()->fields->get(\'summary\')', $plans);

		$expert = (string) file_get_contents($this->skillsDir . '/pw-expert/SKILL.md');
		$this->assertStringContainsString('outputJsonSuccess(OutputInterface $output, array $data = []): int', $expert);
		$this->assertStringContainsString('outputJsonError(OutputInterface $output, string $code, string $message): int', $expert);
		$this->assertStringContainsString('/^[A-Za-z][A-Za-z0-9_]*$/', $expert);
		$this->assertStringContainsString('clampSelectorTopLevelLimit', $expert);
		$this->assertStringContainsString('findNamedSystemPage', $expert);
		$this->assertStringContainsString('isProtectedSystemPage', $expert);
		$this->assertStringContainsString('normalizeLogChannel', $expert);

		$pwTest = (string) file_get_contents($this->skillsDir . '/pw-test/SKILL.md');
		$this->assertStringContainsString('wire test:e2e', $pwTest);
		$this->assertStringContainsString('--e2e', $pwTest);
		$this->assertStringContainsString('getAdminUrl', $pwTest);
		$this->assertStringContainsString('attachRuntimeErrorTracker', $pwTest);
		$this->assertStringContainsString('<?php // __(file-not-translatable)', $pwTest);

		$docsArchitect = (string) file_get_contents($this->skillsDir . '/pw-docs-architect/SKILL.md');
		$this->assertStringContainsString('.agents/schema/field-extenders.php', $docsArchitect);
		$this->assertStringContainsString('FieldSchemaExtender', $docsArchitect);

		$securityAudit = (string) file_get_contents($this->skillsDir . '/pw-security-audit/SKILL.md');
		$this->assertStringContainsString('ctype_digit', $securityAudit);
		$this->assertStringContainsString('flagSystem', $securityAudit);

		$migrations = (string) file_get_contents($this->skillsDir . '/pw-migrations/SKILL.md');
		$this->assertStringContainsString('beginTransaction', $migrations);
		$this->assertStringContainsString('There is no active transaction', $migrations);

		$debugging = (string) file_get_contents($this->skillsDir . '/pw-systematic-debugging/SKILL.md');
		$this->assertStringNotContainsString('template?->name', $debugging);
		$this->assertStringContainsString('getUnformatted(\'image\')->first()', $debugging);
		$this->assertStringContainsString('ctype_digit', $debugging);

		$pageClasses = (string) file_get_contents($this->skillsDir . '/pw-custom-page-classes/SKILL.md');
		$this->assertStringContainsString('extends DefaultPage', $pageClasses);
		$this->assertStringContainsString('getUnformatted(\'featured_image\')', $pageClasses);
		$this->assertStringContainsString('$files->render(', $pageClasses);
		$this->assertStringContainsString('useMarkupRegions', $pageClasses);

		$pwPages = (string) file_get_contents($this->skillsDir . '/pw-pages/SKILL.md');
		$this->assertStringContainsString('Sanitizer::translate', $pwPages);
		$this->assertStringContainsString('deleteAll()', $pwPages);

		$writingSkills = (string) file_get_contents($this->skillsDir . '/pw-writing-skills/SKILL.md');
		$this->assertStringContainsString('## Anti-Patterns & Loophole Closing', $writingSkills);
		$this->assertStringContainsString('--module=<Module|site-overrides>', $writingSkills);

		$fieldtypeRef = (string) file_get_contents($this->skillsDir . '/pw-module-development/references/fieldtype-inputfield.md');
		$this->assertStringContainsString('Multi-column schema note', $fieldtypeRef);

		$urlRouting = (string) file_get_contents($this->skillsDir . '/pw-url-routing/SKILL.md');
		$this->assertStringContainsString('JSON_THROW_ON_ERROR', $urlRouting);
		$this->assertStringContainsString('65536', $urlRouting);

		$seeder = (string) file_get_contents($this->skillsDir . '/pw-seeder/SKILL.md');
		$this->assertStringContainsString('uncacheAll()', $seeder);

		foreach (scandir($this->skillsDir) ?: [] as $skill) {
			if ($skill === '.' || $skill === '..' || !is_dir($this->skillsDir . '/' . $skill)) {
				continue;
			}
			$skillContent = (string) file_get_contents($this->skillsDir . '/' . $skill . '/SKILL.md');
			$lineCount = substr_count($skillContent, "\n") + 1;
			$this->assertLessThanOrEqual(500, $lineCount, "Skill {$skill}/SKILL.md exceeds 500 lines ({$lineCount})");
		}

		$boostGuideline = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/boost/guidelines/boost.md');
		$this->assertStringContainsString('`test:e2e`', $boostGuideline);

		$guidelinesDir = dirname(__DIR__, 2) . '/resources/boost/guidelines';
		foreach (scandir($guidelinesDir) ?: [] as $gFile) {
			if (!str_ends_with($gFile, '.md')) {
				continue;
			}
			$gContent = (string) file_get_contents($guidelinesDir . '/' . $gFile);
			if (preg_match_all('/```php\n(.*?)\n```/s', $gContent, $matches)) {
				foreach ($matches[1] as $blockIndex => $phpBlock) {
					foreach (explode("\n", $phpBlock) as $lineNum => $line) {
						$this->assertDoesNotMatchRegularExpression(
							'/^ +(?!\*)/',
							$line,
							sprintf('Space-indented PHP line in guideline %s block #%d line %d: %s', $gFile, $blockIndex + 1, $lineNum + 1, $line)
						);
					}
				}
			}
		}
	}
}

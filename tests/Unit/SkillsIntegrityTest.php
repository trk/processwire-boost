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

	public function test_all_23_skills_exist_with_valid_frontmatter(): void
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
			'pw-module-fieldtype-inputfield',
			'pw-module-filevalidator',
			'pw-module-markup',
			'pw-module-process',
			'pw-module-textformatter',
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
	}

	public function test_pw_module_fieldtype_inputfield_includes_companion_field_class(): void
	{
		$skillFile = $this->skillsDir . '/pw-module-fieldtype-inputfield/SKILL.md';
		$content = (string) file_get_contents($skillFile);

		$this->assertStringContainsString('getFieldClass', $content);
		$this->assertStringContainsString('CustomField extends Field', $content);
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

			$this->assertStringNotContainsString('## Copy-Paste Prompts', $content, "Deprecated 'Copy-Paste Prompts' found in {$skill}");
		}
	}

	public function test_pw_module_filevalidator_extends_core_file_validator_module(): void
	{
		$skillFile = $this->skillsDir . '/pw-module-filevalidator/SKILL.md';
		$content = (string) file_get_contents($skillFile);

		$this->assertStringContainsString('FileValidatorModule', $content);
		$this->assertStringContainsString('isValidFile', $content);
		$this->assertStringContainsString('validates', $content);
		$this->assertStringContainsString('wire/core/Module/FileValidatorModule/API.md', $content);
	}

	public function test_pw_module_textformatter_implements_core_signatures(): void
	{
		$skillFile = $this->skillsDir . '/pw-module-textformatter/SKILL.md';
		$content = (string) file_get_contents($skillFile);

		$this->assertStringContainsString('extends Textformatter', $content);
		$this->assertStringContainsString('format(&$str)', $content);
		$this->assertStringContainsString('formatValue', $content);
		$this->assertStringContainsString('wire/core/Module/Textformatter', $content);
	}

	public function test_pw_module_process_references_core_process_api(): void
	{
		$skillFile = $this->skillsDir . '/pw-module-process/SKILL.md';
		$content = (string) file_get_contents($skillFile);

		$this->assertStringContainsString('extends Process', $content);
		$this->assertStringContainsString('___execute', $content);
		$this->assertStringContainsString('wire/core/Module/Process/API.md', $content);
	}

	public function test_pw_admin_ui_implements_canonical_design_system(): void
	{
		$skillFile = $this->skillsDir . '/pw-admin-ui/SKILL.md';
		$content = (string) file_get_contents($skillFile);

		$this->assertStringContainsString('pw-module-workspace', $content);
		$this->assertStringContainsString('Inputfields', $content);
		$this->assertStringContainsString('InputfieldForm', $content);
		$this->assertStringContainsString('--pw-main-color', $content);
		$this->assertStringContainsString('--pw-border-color', $content);
		$this->assertStringContainsString('pw-button-dropdown-wrap', $content);
	}
}

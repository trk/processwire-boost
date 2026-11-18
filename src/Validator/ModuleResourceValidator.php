<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Validator;

use Totoglu\Console\Boost\BoostManager;
use Totoglu\Console\Boost\Install\SkillWriter;
use Totoglu\Console\Boost\Schema\FieldSchemaExtender;

final class ModuleResourceValidator
{
	private const GUIDELINE_MAX_NON_EMPTY_LINES = 30;
	private const SKILL_MAX_LINES = 500;
	private const SKILL_DESCRIPTION_MAX_LENGTH = 500;

	public function __construct(
		private readonly string $projectRoot
	) {
	}

	/**
	 * Validate Boost & AI context resources across `site/modules/*` and `site/.agents/`.
	 *
	 * @return array{
	 *   valid: bool,
	 *   checked: int,
	 *   errors: int,
	 *   warnings: int,
	 *   targets: list<array{
	 *     name: string,
	 *     path: string,
	 *     artifacts: list<string>,
	 *     issues: list<array{severity: 'error'|'warning', code: string, file: string, message: string}>
	 *   }>
	 * }
	 */
	public function validate(?string $moduleFilter = null, bool $strict = false): array
	{
		$candidates = $this->collectCandidateTargets($moduleFilter);
		$targets = [];
		$errorCount = 0;
		$warningCount = 0;

		foreach ($candidates as $name => $dirPath) {
			$result = $this->validateTarget($name, $dirPath, $strict);
			if ($result['artifacts'] === [] && $result['issues'] === [] && $moduleFilter === null) {
				continue;
			}

			foreach ($result['issues'] as $issue) {
				if ($issue['severity'] === 'error') {
					$errorCount++;
				} else {
					$warningCount++;
				}
			}

			$targets[] = $result;
		}

		return [
			'valid' => $errorCount === 0,
			'checked' => count($targets),
			'errors' => $errorCount,
			'warnings' => $warningCount,
			'targets' => $targets,
		];
	}

	/**
	 * @return array<string, string>
	 */
	private function collectCandidateTargets(?string $moduleFilter): array
	{
		$root = rtrim($this->projectRoot, '/');
		$siteModules = BoostManager::resolveSiteModuleRootPath($root);

		if ($moduleFilter !== null && $moduleFilter !== '') {
			$trimmed = trim($moduleFilter);
			if (preg_match('/^[A-Za-z0-9_-]+$/', $trimmed) !== 1) {
				throw new \InvalidArgumentException("Invalid module identifier '{$trimmed}'.");
			}

			if ($trimmed === 'site-overrides') {
				$siteAgents = $root . '/site/.agents';
				if (!is_dir($siteAgents) && !is_link($siteAgents)) {
					throw new \InvalidArgumentException("Site overrides directory 'site/.agents' does not exist.");
				}
				return ['site-overrides' => $siteAgents];
			}

			if ($siteModules === '') {
				throw new \InvalidArgumentException("Directory 'site/modules' was not found in '{$root}'.");
			}

			$target = $siteModules . '/' . $trimmed;
			if (!is_dir($target) && !is_link($target)) {
				throw new \InvalidArgumentException("Module '{$trimmed}' was not found in '{$siteModules}'.");
			}

			return [$trimmed => $target];
		}

		$candidates = [];
		if ($siteModules !== '' && is_dir($siteModules)) {
			foreach (scandir($siteModules) ?: [] as $entry) {
				if ($entry === '.' || $entry === '..') {
					continue;
				}
				$full = $siteModules . '/' . $entry;
				if (is_dir($full) || is_link($full)) {
					$candidates[$entry] = $full;
				}
			}
		}

		$siteAgents = $root . '/site/.agents';
		if (is_dir($siteAgents) || is_link($siteAgents)) {
			$candidates['site-overrides'] = $siteAgents;
		}

		ksort($candidates);

		return $candidates;
	}

	/**
	 * @return array{
	 *   name: string,
	 *   path: string,
	 *   artifacts: list<string>,
	 *   issues: list<array{severity: 'error'|'warning', code: string, file: string, message: string}>
	 * }
	 */
	private function validateTarget(string $name, string $dirPath, bool $strict): array
	{
		$artifacts = [];
		$issues = [];
		$relDir = $this->toRelativePath($dirPath);

		if (is_link($dirPath)) {
			$issues[] = $this->issue('error', 'SYMLINK_FORBIDDEN', $relDir, 'Target directory must not be a symbolic link.');
			return [
				'name' => $name,
				'path' => $relDir,
				'artifacts' => $artifacts,
				'issues' => $issues,
			];
		}

		$isSiteOverrides = ($name === 'site-overrides');
		$agentsContainer = $isSiteOverrides ? $dirPath : ($dirPath . '/.agents');

		if (!$isSiteOverrides) {
			$apiPath = $dirPath . '/API.md';
			if (is_link($apiPath)) {
				$artifacts[] = 'API.md';
				$issues[] = $this->issue('error', 'SYMLINK_FORBIDDEN', $this->toRelativePath($apiPath), 'API.md must not be a symbolic link.');
			} elseif (is_file($apiPath)) {
				$artifacts[] = 'API.md';
				$this->validateApiMarkdown($apiPath, $strict, $issues);
			}

			$agentsMdPath = $dirPath . '/AGENTS.md';
			if (is_link($agentsMdPath)) {
				$artifacts[] = 'AGENTS.md';
				$issues[] = $this->issue('error', 'SYMLINK_FORBIDDEN', $this->toRelativePath($agentsMdPath), 'AGENTS.md must not be a symbolic link.');
			} elseif (is_file($agentsMdPath)) {
				$artifacts[] = 'AGENTS.md';
				$this->validateAgentsMarkdown($agentsMdPath, $strict, $issues);
			}
		}

		if (is_link($agentsContainer)) {
			$artifacts[] = '.agents';
			$issues[] = $this->issue('error', 'SYMLINK_FORBIDDEN', $this->toRelativePath($agentsContainer), '.agents directory must not be a symbolic link.');
			return [
				'name' => $name,
				'path' => $relDir,
				'artifacts' => $artifacts,
				'issues' => $issues,
			];
		}

		if (is_dir($agentsContainer)) {
			$guidelinesDir = $agentsContainer . '/guidelines';
			if (is_link($guidelinesDir)) {
				$artifacts[] = '.agents/guidelines';
				$issues[] = $this->issue('error', 'SYMLINK_FORBIDDEN', $this->toRelativePath($guidelinesDir), 'guidelines directory must not be a symbolic link.');
			} elseif (is_dir($guidelinesDir)) {
				$artifacts[] = '.agents/guidelines';
				$this->validateGuidelinesDir($guidelinesDir, $strict, $issues);
			}

			$skillsDir = $agentsContainer . '/skills';
			if (is_link($skillsDir)) {
				$artifacts[] = '.agents/skills';
				$issues[] = $this->issue('error', 'SYMLINK_FORBIDDEN', $this->toRelativePath($skillsDir), 'skills directory must not be a symbolic link.');
			} elseif (is_dir($skillsDir)) {
				$artifacts[] = '.agents/skills';
				$this->validateSkillsDir($skillsDir, $strict, $issues);
			}

			$extenderFile = $agentsContainer . '/schema/field-extenders.php';
			$schemaDir = $agentsContainer . '/schema';
			if (is_link($schemaDir) || is_link($extenderFile)) {
				$artifacts[] = '.agents/schema/field-extenders.php';
				$issues[] = $this->issue('error', 'SYMLINK_FORBIDDEN', $this->toRelativePath($extenderFile), 'field-extenders.php or schema directory must not be a symbolic link.');
			} elseif (is_file($extenderFile)) {
				$artifacts[] = '.agents/schema/field-extenders.php';
				$this->validateFieldExtendersManifest($extenderFile, $issues);
			}
		}

		return [
			'name' => $name,
			'path' => $relDir,
			'artifacts' => $artifacts,
			'issues' => $issues,
		];
	}

	/**
	 * @param list<array{severity: 'error'|'warning', code: string, file: string, message: string}> $issues
	 */
	private function validateApiMarkdown(string $path, bool $strict, array &$issues): void
	{
		$rel = $this->toRelativePath($path);
		$content = (string) file_get_contents($path);
		if (!$this->validateUtf8AndUnicode($content, $rel, $issues)) {
			return;
		}

		if (trim($content) === '') {
			$issues[] = $this->issue('error', 'API_EMPTY', $rel, 'API.md must not be empty.');
			return;
		}

		if (preg_match('/^#\s+\S+/m', $content) !== 1) {
			$issues[] = $this->issue('error', 'API_MISSING_H1', $rel, 'API.md must include a top-level "# ClassName" H1 heading.');
		}

		$this->validatePhpCodeBlocks($content, $rel, $strict ? 'error' : 'warning', $issues);
	}

	/**
	 * @param list<array{severity: 'error'|'warning', code: string, file: string, message: string}> $issues
	 */
	private function validateAgentsMarkdown(string $path, bool $strict, array &$issues): void
	{
		$rel = $this->toRelativePath($path);
		$content = (string) file_get_contents($path);
		if (!$this->validateUtf8AndUnicode($content, $rel, $issues)) {
			return;
		}

		if (trim($content) === '') {
			$issues[] = $this->issue('error', 'AGENTS_EMPTY', $rel, 'AGENTS.md must not be empty.');
			return;
		}

		$hasFrontmatter = preg_match('/\A---\s*\R.*?\R---/s', $content) === 1;
		$description = $hasFrontmatter ? SkillWriter::extractFrontmatterDescription($content) : '';
		if ($description === '') {
			$issues[] = $this->issue(
				$strict ? 'error' : 'warning',
				'AGENTS_MISSING_DESCRIPTION',
				$rel,
				'AGENTS.md should begin with YAML frontmatter defining a non-empty "description:" summary for the Module Context Index.'
			);
		}

		$this->validatePhpCodeBlocks($content, $rel, $strict ? 'error' : 'warning', $issues);
	}

	/**
	 * @param list<array{severity: 'error'|'warning', code: string, file: string, message: string}> $issues
	 */
	private function validateGuidelinesDir(string $guidelinesDir, bool $strict, array &$issues): void
	{
		foreach (scandir($guidelinesDir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			$full = $guidelinesDir . '/' . $entry;
			$rel = $this->toRelativePath($full);

			if (is_link($full)) {
				$issues[] = $this->issue('error', 'SYMLINK_FORBIDDEN', $rel, 'Guideline file must not be a symbolic link.');
				continue;
			}
			if (!is_file($full) || strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'md') {
				continue;
			}

			$content = (string) file_get_contents($full);
			if (!$this->validateUtf8AndUnicode($content, $rel, $issues)) {
				continue;
			}

			if (trim($content) === '') {
				$issues[] = $this->issue('error', 'GUIDELINE_EMPTY', $rel, 'Guideline file must not be empty.');
				continue;
			}

			$nonEmptyLines = count(array_filter(
				preg_split('/\R/', $content) ?: [],
				static fn(string $line): bool => trim($line) !== ''
			));
			if ($nonEmptyLines > self::GUIDELINE_MAX_NON_EMPTY_LINES) {
				$issues[] = $this->issue(
					$strict ? 'error' : 'warning',
					'GUIDELINE_BUDGET_EXCEEDED',
					$rel,
					sprintf(
						'Always-on guideline has %d non-empty lines (recommended <= %d). Move detailed workflows to .agents/skills/ or AGENTS.md.',
						$nonEmptyLines,
						self::GUIDELINE_MAX_NON_EMPTY_LINES
					)
				);
			}

			$this->validatePhpCodeBlocks($content, $rel, 'error', $issues);
		}
	}

	/**
	 * @param list<array{severity: 'error'|'warning', code: string, file: string, message: string}> $issues
	 */
	private function validateSkillsDir(string $skillsDir, bool $strict, array &$issues): void
	{
		foreach (scandir($skillsDir) ?: [] as $skillFolder) {
			if ($skillFolder === '.' || $skillFolder === '..') {
				continue;
			}
			$skillDir = $skillsDir . '/' . $skillFolder;
			$relDir = $this->toRelativePath($skillDir);

			if (is_link($skillDir)) {
				$issues[] = $this->issue('error', 'SYMLINK_FORBIDDEN', $relDir, 'Skill directory must not be a symbolic link.');
				continue;
			}
			if (!is_dir($skillDir)) {
				continue;
			}

			if (!str_starts_with($skillFolder, 'pw-')) {
				$issues[] = $this->issue(
					$strict ? 'error' : 'warning',
					'SKILL_PREFIX',
					$relDir,
					"ProcessWire module skill directory '{$skillFolder}' should start with the 'pw-' prefix."
				);
			}

			$skillFile = $skillDir . '/SKILL.md';
			$relFile = $this->toRelativePath($skillFile);
			if (is_link($skillFile)) {
				$issues[] = $this->issue('error', 'SYMLINK_FORBIDDEN', $relFile, 'SKILL.md must not be a symbolic link.');
				continue;
			}
			if (!is_file($skillFile)) {
				$issues[] = $this->issue('error', 'SKILL_MISSING_FILE', $relDir, "Skill directory '{$skillFolder}' is missing SKILL.md.");
				continue;
			}

			$content = (string) file_get_contents($skillFile);
			if (!$this->validateUtf8AndUnicode($content, $relFile, $issues)) {
				continue;
			}

			if (preg_match('/\A---\s*\R(.*?)\R---/s', $content, $fmMatches) !== 1) {
				$issues[] = $this->issue('error', 'SKILL_FRONTMATTER_MISSING', $relFile, 'SKILL.md must begin with YAML frontmatter (--- ... ---).');
			} else {
				$frontmatter = $fmMatches[1];
				$declaredName = '';
				if (preg_match('/^name:\s*(.+)$/m', $frontmatter, $nameMatches) === 1) {
					$declaredName = trim($nameMatches[1], " \t\"'");
				}
				if ($declaredName !== $skillFolder) {
					$issues[] = $this->issue(
						'error',
						'SKILL_NAME_MISMATCH',
						$relFile,
						"Frontmatter 'name: {$declaredName}' must match directory name '{$skillFolder}'."
					);
				}

				$description = SkillWriter::extractFrontmatterDescription($content);
				if ($description === '') {
					$issues[] = $this->issue('error', 'SKILL_DESCRIPTION_MISSING', $relFile, 'SKILL.md frontmatter must define a non-empty "description:".');
				} else {
					if (!str_starts_with($description, 'Use when ')) {
						$issues[] = $this->issue(
							$strict ? 'error' : 'warning',
							'SKILL_DESCRIPTION_TRIGGER',
							$relFile,
							'Skill description should start with "Use when " to optimize agent trigger accuracy.'
						);
					}
					if (strlen($description) > self::SKILL_DESCRIPTION_MAX_LENGTH) {
						$issues[] = $this->issue(
							$strict ? 'error' : 'warning',
							'SKILL_DESCRIPTION_LENGTH',
							$relFile,
							sprintf('Skill description is %d bytes (recommended <= %d).', strlen($description), self::SKILL_DESCRIPTION_MAX_LENGTH)
						);
					}
				}
			}

			$lineCount = count(preg_split('/\R/', $content) ?: []);
			if ($lineCount > self::SKILL_MAX_LINES) {
				$issues[] = $this->issue(
					$strict ? 'error' : 'warning',
					'SKILL_TOO_LONG',
					$relFile,
					sprintf('SKILL.md is %d lines (recommended <= %d). Extract reference tables into references/*.md.', $lineCount, self::SKILL_MAX_LINES)
				);
			}

			$this->validatePhpCodeBlocks($content, $relFile, 'error', $issues);
			$this->validateSkillReferenceFiles($skillDir . '/references', $issues);
		}
	}

	/**
	 * @param list<array{severity: 'error'|'warning', code: string, file: string, message: string}> $issues
	 */
	private function validateSkillReferenceFiles(string $refsDir, array &$issues): void
	{
		if (is_link($refsDir)) {
			$issues[] = $this->issue('error', 'SYMLINK_FORBIDDEN', $this->toRelativePath($refsDir), 'references directory must not be a symbolic link.');
			return;
		}
		if (!is_dir($refsDir)) {
			return;
		}

		foreach (scandir($refsDir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}
			$full = $refsDir . '/' . $entry;
			$rel = $this->toRelativePath($full);
			if (is_link($full)) {
				$issues[] = $this->issue('error', 'SYMLINK_FORBIDDEN', $rel, 'Reference file must not be a symbolic link.');
				continue;
			}
			if (!is_file($full) || strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'md') {
				continue;
			}

			$content = (string) file_get_contents($full);
			if (!$this->validateUtf8AndUnicode($content, $rel, $issues)) {
				continue;
			}
			$this->validatePhpCodeBlocks($content, $rel, 'error', $issues);
		}
	}

	/**
	 * @param list<array{severity: 'error'|'warning', code: string, file: string, message: string}> $issues
	 */
	private function validateFieldExtendersManifest(string $manifestPath, array &$issues): void
	{
		$rel = $this->toRelativePath($manifestPath);
		$content = (string) file_get_contents($manifestPath);
		if (!$this->validateUtf8AndUnicode($content, $rel, $issues)) {
			return;
		}

		$output = [];
		$exitCode = 0;
		exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($manifestPath) . ' 2>&1', $output, $exitCode);
		if ($exitCode !== 0) {
			$issues[] = $this->issue('error', 'EXTENDER_SYNTAX_ERROR', $rel, 'PHP syntax error in field-extenders.php: ' . implode(' ', $output));
			return;
		}

		try {
			$entries = static function (string $file): mixed {
				return require $file;
			};
			$loaded = $entries($manifestPath);
		} catch (\Throwable $e) {
			$issues[] = $this->issue('error', 'EXTENDER_LOAD_ERROR', $rel, 'Failed to load field-extenders.php: ' . $e->getMessage());
			return;
		}

		if (!is_array($loaded)) {
			$issues[] = $this->issue('error', 'EXTENDER_INVALID_RETURN', $rel, 'field-extenders.php must return an array of FieldSchemaExtender class names or instances.');
			return;
		}

		foreach ($loaded as $idx => $entry) {
			if ($entry instanceof FieldSchemaExtender) {
				continue;
			}
			if (
				!is_string($entry)
				|| preg_match('/^\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $entry) !== 1
			) {
				$issues[] = $this->issue('error', 'EXTENDER_INVALID_ENTRY', $rel, "Entry #{$idx} in field-extenders.php is not a valid class name or FieldSchemaExtender instance.");
				continue;
			}
			if (class_exists($entry, false) || class_exists($entry)) {
				$ref = new \ReflectionClass($entry);
				if (!$ref->isInstantiable() || !$ref->implementsInterface(FieldSchemaExtender::class)) {
					$issues[] = $this->issue('error', 'EXTENDER_INVALID_CLASS', $rel, "Class '{$entry}' must be a concrete implementation of FieldSchemaExtender.");
				}
			}
		}
	}

	/**
	 * @param list<array{severity: 'error'|'warning', code: string, file: string, message: string}> $issues
	 */
	private function validateUtf8AndUnicode(string $content, string $relFile, array &$issues): bool
	{
		if (preg_match('//u', $content) !== 1) {
			$issues[] = $this->issue('error', 'INVALID_UTF8', $relFile, 'File contains invalid UTF-8 or binary bytes.');
			return false;
		}

		if (preg_match('/[\x{202A}-\x{202E}\x{2066}-\x{2069}\x{061C}\x{E0000}-\x{E007F}\x{200B}-\x{200D}\x{2060}]/u', $content) === 1) {
			$issues[] = $this->issue('error', 'TROJAN_UNICODE', $relFile, 'File contains invisible zero-width, bidi-override, or Unicode tag-smuggling characters.');
		}

		return true;
	}

	/**
	 * @param 'error'|'warning' $severity
	 * @param list<array{severity: 'error'|'warning', code: string, file: string, message: string}> $issues
	 */
	private function validatePhpCodeBlocks(string $markdown, string $relFile, string $severity, array &$issues): void
	{
		if (!preg_match_all('/```php\r?\n(.*?)\r?\n```/s', $markdown, $matches)) {
			return;
		}

		foreach ($matches[1] as $blockIdx => $phpBlock) {
			foreach (preg_split('/\R/', $phpBlock) ?: [] as $lineIdx => $line) {
				if (preg_match('/^ +(?!\*)/', $line) === 1) {
					$issues[] = $this->issue(
						$severity,
						'PHP_SPACE_INDENTATION',
						$relFile,
						sprintf('PHP code block #%d line %d uses space indentation instead of tabs.', $blockIdx + 1, $lineIdx + 1)
					);
					return;
				}
			}
		}
	}

	/**
	 * @param 'error'|'warning' $severity
	 * @return array{severity: 'error'|'warning', code: string, file: string, message: string}
	 */
	private function issue(string $severity, string $code, string $file, string $message): array
	{
		return [
			'severity' => $severity,
			'code' => $code,
			'file' => $file,
			'message' => $message,
		];
	}

	private function toRelativePath(string $path): string
	{
		$prefix = rtrim($this->projectRoot, '/') . '/';
		return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
	}
}

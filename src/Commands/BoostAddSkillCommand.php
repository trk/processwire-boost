<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Boost\Install\SkillWriter;
use Totoglu\Console\Boost\Skills\Remote\AuditResult;
use Totoglu\Console\Boost\Skills\Remote\GitHubRepository;
use Totoglu\Console\Boost\Skills\Remote\GitHubSkillProvider;
use Totoglu\Console\Boost\Skills\Remote\RemoteSkill;
use Totoglu\Console\Boost\Skills\Remote\SkillAuditor;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

final class BoostAddSkillCommand extends Command
{
	use InteractWithProcessWire;

	private InputInterface $currentInput;

	/** @var list<string> */
	private array $skippedSkills = [];

	protected function configure(): void
	{
		$this
			->setName('boost:add-skill')
			->setDescription('Add skills from a remote GitHub repository')
			->addArgument('repo', InputArgument::OPTIONAL, 'GitHub repository (owner/repo or full URL)')
			->addOption('list', 'l', InputOption::VALUE_NONE, 'List available skills')
			->addOption('all', 'a', InputOption::VALUE_NONE, 'Install all skills')
			->addOption('skill', 's', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Specific skills to install')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing skills')
			->addOption('skip-audit', null, InputOption::VALUE_NONE, 'Skip heuristic security audit')
			->addOption('skip-update', null, InputOption::VALUE_NONE, 'Skip running boost:update after installation')
			->addOption('json', null, InputOption::VALUE_NONE, 'Output structured JSON');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->currentInput = $input;
		$this->skippedSkills = [];
		$asJson = (bool) $input->getOption('json');

		if (!$asJson) {
			$output->writeln("<fg=cyan>ProcessWire Boost :: Add Skill</>\n");
		}

		$projectRoot = getcwd() ?: '.';

		$repo = $this->parseRepository($input, $output, $asJson);
		if ($repo === null) {
			return Command::FAILURE;
		}

		$provider = new GitHubSkillProvider($repo);

		$skills = [];
		try {
			if (!$asJson) {
				info("Fetching skills from {$repo->fullName()}...");
			}
			$skills = $provider->discoverSkills();
		} catch (\RuntimeException $e) {
			if ($asJson) {
				return $this->outputJsonError($output, 'FETCH_FAILED', $e->getMessage());
			}
			$output->writeln("<error>{$e->getMessage()}</error>");
			return Command::FAILURE;
		}

		if ($skills === []) {
			$msg = 'No valid skills were found in the repository.';
			if ($asJson) {
				return $this->outputJsonError($output, 'NO_SKILLS_FOUND', $msg);
			}
			$output->writeln("<error>{$msg}</error>");
			return Command::FAILURE;
		}

		if ($input->getOption('list')) {
			$skillNames = array_map('strval', array_keys($skills));
			if ($asJson) {
				return $this->outputJsonSuccess($output, [
					'repository' => $repo->fullName(),
					'skills' => $skillNames,
				]);
			}
			note('Found ' . count($skillNames) . ' available skills:');
			foreach ($skillNames as $skillName) {
				$output->writeln("  • {$skillName}");
			}
			return Command::SUCCESS;
		}

		$requestedSkillNames = array_values(array_filter(array_map('strval', (array) $input->getOption('skill')), static fn(string $s): bool => trim($s) !== ''));
		$selectedSkills = $this->selectSkills($skills, $input, $asJson);

		if ($selectedSkills === []) {
			if ($requestedSkillNames !== []) {
				$missingList = implode(', ', $requestedSkillNames);
				return $this->failCommand($output, $asJson, 'SKILL_NOT_FOUND', "Requested skill(s) not found in repository: {$missingList}");
			}
			if ($asJson || !$input->isInteractive()) {
				return $this->failCommand($output, $asJson, 'NO_SKILLS_SELECTED', 'Specify --all or --skill=<name> when running non-interactively.');
			}
			$output->writeln('<comment>No skills were selected.</comment>');
			return Command::SUCCESS;
		}

		$skillsToInstall = $this->skillsToInstall($selectedSkills, $projectRoot, (bool) $input->getOption('force'), $asJson);
		if ($skillsToInstall === []) {
			if ($asJson) {
				return $this->outputJsonSuccess($output, [
					'installed' => [],
					'skipped' => $this->skippedSkills,
					'failed' => [],
				]);
			}
			$output->writeln('<comment>No new skills were selected for installation.</comment>');
			return Command::SUCCESS;
		}

		if (!$this->runAuditBeforeInstall($provider, $skillsToInstall, $output, $asJson)) {
			if ($asJson) {
				return $this->outputJsonError($output, 'AUDIT_BLOCKED', 'Security audit detected medium or higher risk signals. Re-run interactively or pass --skip-audit if trusted.');
			}
			$output->writeln('<comment>Installation canceled.</comment>');
			return Command::FAILURE;
		}

		$results = $this->downloadSkills($provider, $skillsToInstall, $projectRoot);

		if ($results['installed'] !== [] && !$input->getOption('skip-update')) {
			$this->runBoostUpdate();
		}

		if ($asJson) {
			if ($results['installed'] === [] && $results['failed'] !== []) {
				return $this->outputJsonError($output, 'INSTALL_FAILED', implode('; ', $results['failed']));
			}
			return $this->outputJsonSuccess($output, [
				'installed' => $results['installed'],
				'skipped' => $this->skippedSkills,
				'failed' => $results['failed'],
			]);
		}

		if ($results['installed'] !== []) {
			$output->writeln("\n  <info>Skills installed:</info>");
			foreach ($results['installed'] as $skillName) {
				$output->writeln("  • {$skillName}");
			}
		}

		if ($this->skippedSkills !== []) {
			$output->writeln("\n  <comment>Skipped existing skills:</comment>");
			foreach ($this->skippedSkills as $skillName) {
				$output->writeln("  • {$skillName}");
			}
		}

		if ($results['failed'] !== []) {
			$output->writeln("\n  <error>Some skills failed to install:</error>");
			foreach ($results['failed'] as $skillName => $reason) {
				$output->writeln("  • {$skillName}: {$reason}");
			}
			if ($results['installed'] === []) {
				return Command::FAILURE;
			}
		}

		outro('Done');
		return Command::SUCCESS;
	}

	private function parseRepository(InputInterface $input, OutputInterface $output, bool $asJson): ?GitHubRepository
	{
		$repoArg = $input->getArgument('repo');

		if (!is_string($repoArg) || $repoArg === '') {
			if (!$input->isInteractive() || $asJson) {
				$msg = 'Repository argument is required (e.g. owner/repo).';
				if ($asJson) {
					$this->outputJsonError($output, 'INVALID_REPOSITORY', $msg);
				} else {
					$output->writeln("<error>{$msg}</error>");
				}
				return null;
			}

			$repoArg = text(
				label: 'Which GitHub repository would you like to fetch skills from?',
				placeholder: 'owner/repo or GitHub URL',
				required: true
			);
		}

		try {
			return GitHubRepository::fromInput($repoArg);
		} catch (\InvalidArgumentException $e) {
			if ($asJson) {
				$this->outputJsonError($output, 'INVALID_REPOSITORY', $e->getMessage());
			} else {
				$output->writeln("<error>{$e->getMessage()}</error>");
			}
			return null;
		}
	}

	/**
	 * @param array<string, RemoteSkill> $skills
	 * @return array<string, RemoteSkill>
	 */
	private function selectSkills(array $skills, InputInterface $input, bool $asJson): array
	{
		$skillOptions = $input->getOption('skill');

		if ($input->getOption('all')) {
			return $skills;
		}

		if (is_array($skillOptions) && $skillOptions !== []) {
			return array_filter(
				$skills,
				static fn(RemoteSkill $skill): bool => in_array($skill->name, $skillOptions, true)
			);
		}

		if (!$input->isInteractive() || $asJson) {
			return [];
		}

		$selected = multiselect(
			label: 'Which skills would you like to install?',
			options: array_combine(
				array_keys($skills),
				array_keys($skills)
			),
			required: true
		);

		return array_filter(
			$skills,
			static fn(RemoteSkill $skill): bool => in_array($skill->name, $selected, true)
		);
	}

	/**
	 * @param array<string, RemoteSkill> $skills
	 * @return array{installed: list<string>, failed: array<string, string>}
	 */
	private function downloadSkills(GitHubSkillProvider $provider, array $skills, string $projectRoot): array
	{
		$skillsPath = $projectRoot . '/.agents/skills';
		if (!is_dir($skillsPath)) {
			@mkdir($skillsPath, 0755, true);
		}

		$results = ['installed' => [], 'failed' => []];

		foreach ($skills as $skill) {
			$safeSkillName = SkillWriter::sanitizeSkillName(basename(str_replace('\\', '/', $skill->name)));
			if ($safeSkillName === '' || $safeSkillName === '-' || $safeSkillName === '.') {
				$results['failed'][$skill->name] = 'Invalid skill name';
				continue;
			}

			$targetPath = $skillsPath . '/' . $safeSkillName;
			if (is_dir($targetPath)) {
				SkillWriter::deleteTree($targetPath);
			}

			try {
				if ($provider->downloadSkill($skill, $targetPath)) {
					$results['installed'][] = $skill->name;
				} else {
					$results['failed'][$skill->name] = 'Download failed';
				}
			} catch (\Throwable $e) {
				$results['failed'][$skill->name] = $e->getMessage();
			}
		}

		return $results;
	}

	/**
	 * @param array<string, RemoteSkill> $skills
	 * @return array<string, RemoteSkill>
	 */
	private function skillsToInstall(array $skills, string $projectRoot, bool $force, bool $asJson): array
	{
		$existing = array_filter(
			$skills,
			static fn(RemoteSkill $skill): bool => is_dir(
				$projectRoot . '/.agents/skills/' . SkillWriter::sanitizeSkillName(basename(str_replace('\\', '/', $skill->name)))
			)
		);

		if ($existing === [] || $force) {
			return $skills;
		}

		if (!$this->currentInput->isInteractive() || $asJson) {
			$this->skippedSkills = array_values(array_map(
				static fn(RemoteSkill $skill): string => $skill->name,
				$existing
			));
			return array_diff_key($skills, $existing);
		}

		$shouldUpdate = confirm(
			label: sprintf('Update %d existing skill(s)?', count($existing)),
			default: false
		);

		if ($shouldUpdate) {
			return $skills;
		}

		$this->skippedSkills = array_values(array_map(
			static fn(RemoteSkill $skill): string => $skill->name,
			$existing
		));

		return array_diff_key($skills, $existing);
	}

	/**
	 * @param array<string, RemoteSkill> $skills
	 */
	private function runAuditBeforeInstall(GitHubSkillProvider $provider, array $skills, OutputInterface $output, bool $asJson): bool
	{
		if ((bool) $this->currentInput->getOption('skip-audit')) {
			return true;
		}

		$auditResults = $asJson
			? (new SkillAuditor())->audit($provider, $skills)
			: spin(
				callback: static fn(): array => (new SkillAuditor())->audit($provider, $skills),
				message: 'Running heuristic security audit...'
			);

		if (!$this->hasRiskySkills($auditResults)) {
			return true;
		}

		if (!$asJson) {
			$this->displayAuditResults($output, $auditResults);
		}

		if (!$this->currentInput->isInteractive() || $asJson) {
			return false;
		}

		return confirm(
			label: 'Risk signals were found. Continue installing these skills?',
			default: false
		);
	}

	/**
	 * @param array<string, AuditResult> $auditResults
	 */
	private function hasRiskySkills(array $auditResults): bool
	{
		foreach ($auditResults as $result) {
			if ($result->risk->weight() >= 3) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, AuditResult> $auditResults
	 */
	private function displayAuditResults(OutputInterface $output, array $auditResults): void
	{
		note('Security Audit');

		foreach ($auditResults as $skillName => $result) {
			$signals = $result->signals === []
				? 'No suspicious patterns detected'
				: implode('; ', $result->signals);

			$output->writeln("  • {$skillName} [{$result->risk->label()}] {$signals}");
		}
	}

	private function runBoostUpdate(): void
	{
		$application = $this->getApplication();
		if ($application === null) {
			return;
		}

		$command = $application->find('boost:update');
		$updateInput = new ArrayInput([
			'--no-discover' => true,
			'--json' => true,
		]);
		$updateInput->setInteractive(false);
		$command->run($updateInput, new NullOutput());
	}
}

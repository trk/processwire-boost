<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Boost\Validator\ModuleResourceValidator;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

final class BoostValidateCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('boost:validate')
			->setDescription('Validate ProcessWire module and site Boost resources (API.md, AGENTS.md, .agents/skills, .agents/guidelines, field-extenders)')
			->addOption('module', 'm', InputOption::VALUE_REQUIRED, 'Validate only a specific module in site/modules/ (or site-overrides)')
			->addOption('strict', null, InputOption::VALUE_NONE, 'Treat warnings (missing descriptions, context budget limits, non-tabbed API.md blocks) as errors')
			->addOption('json', null, InputOption::VALUE_NONE, 'Output structured JSON');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');
		$strict = (bool) $input->getOption('strict');
		$rawModule = $input->getOption('module');
		$moduleFilter = ($rawModule !== null && $rawModule !== '') ? trim((string) $rawModule) : null;

		if (!$asJson) {
			intro('ProcessWire Boost :: Validate Module & Site Resources');
		}

		$projectRoot = getcwd() ?: '.';
		$validator = new ModuleResourceValidator($projectRoot);

		try {
			$report = $validator->validate($moduleFilter, $strict);
		} catch (\InvalidArgumentException $e) {
			return $this->failCommand($output, $asJson, 'INVALID_TARGET', $e->getMessage());
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'VALIDATION_CRASHED', $e->getMessage());
		}

		if ($asJson) {
			if (!$report['valid']) {
				$output->writeln((string) json_encode([
					'ok' => false,
					'error' => [
						'code' => 'VALIDATION_FAILED',
						'message' => sprintf('Found %d error(s) and %d warning(s) across %d target(s).', $report['errors'], $report['warnings'], $report['checked']),
					],
					'data' => $report,
				], JSON_UNESCAPED_SLASHES));
				return Command::FAILURE;
			}

			return $this->outputJsonSuccess($output, $report);
		}

		if ($report['checked'] === 0) {
			note('No Boost-aware modules or site/.agents resources found to validate.');
			return Command::SUCCESS;
		}

		$rows = [];
		foreach ($report['targets'] as $target) {
			$errCount = count(array_filter($target['issues'], static fn(array $i): bool => $i['severity'] === 'error'));
			$warnCount = count(array_filter($target['issues'], static fn(array $i): bool => $i['severity'] === 'warning'));
			$status = $errCount > 0 ? "FAIL ({$errCount} err, {$warnCount} warn)" : ($warnCount > 0 ? "WARN ({$warnCount} warn)" : 'PASS');
			$rows[] = [
				$target['name'],
				implode(', ', $target['artifacts']),
				$status,
			];
		}

		table(['Target', 'Discovered Artifacts', 'Status'], $rows);

		foreach ($report['targets'] as $target) {
			foreach ($target['issues'] as $issue) {
				$line = "[{$issue['code']}] {$issue['file']}: {$issue['message']}";
				if ($issue['severity'] === 'error') {
					error($line);
				} else {
					warning($line);
				}
			}
		}

		if (!$report['valid']) {
			error(sprintf('Validation failed with %d error(s) and %d warning(s).', $report['errors'], $report['warnings']));
			return Command::FAILURE;
		}

		info(sprintf('Validated %d target(s) successfully (%d warning(s)).', $report['checked'], $report['warnings']));
		return Command::SUCCESS;
	}
}

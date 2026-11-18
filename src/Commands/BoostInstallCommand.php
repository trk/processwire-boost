<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Boost\BoostManager;
use Totoglu\Console\Boost\Install\Agents\Agent;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;

final class BoostInstallCommand extends Command
{
	use InteractWithProcessWire;

	private const FEATURES = [
		'guidelines' => 'AI Guidelines',
		'skills' => 'Agent Skills',
		'mcp' => 'Boost MCP Server Configuration',
	];

	protected function configure(): void
	{
		$this
			->setName('boost:install')
			->setDescription('Manage AI helper setup (ProcessWire Boost). Select features to install/update, deselect to remove.')
			->addOption('guidelines', null, InputOption::VALUE_NONE, 'Install AI Guidelines')
			->addOption('skills', null, InputOption::VALUE_NONE, 'Install Agent Skills')
			->addOption('mcp', null, InputOption::VALUE_NONE, 'Install MCP Server Configuration')
			->addOption('modules', 'm', InputOption::VALUE_OPTIONAL, 'Comma-separated modules to install')
			->addOption('agents', 'a', InputOption::VALUE_OPTIONAL, 'Comma-separated agents to configure')
			->addOption('json', null, InputOption::VALUE_NONE, 'Output structured JSON');
	}

	private function hasExplicitFeatureFlags(InputInterface $input): bool
	{
		return (bool) $input->getOption('guidelines')
			|| (bool) $input->getOption('skills')
			|| (bool) $input->getOption('mcp');
	}

	private function isExplicitFlagMode(InputInterface $input, bool $asJson): bool
	{
		return $asJson
			|| $this->hasExplicitFeatureFlags($input)
			|| $input->getOption('modules') !== null
			|| $input->getOption('agents') !== null
			|| !$input->isInteractive();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');

		if (!$asJson) {
			$this->displayBanner($output);
			intro('Managing ProcessWire Boost installation...');
		}

		$projectRoot = getcwd() ?: '.';
		$manager = new BoostManager($projectRoot);
		$config = $manager->loadConfig();

		$installedFeatures = [
			'guidelines' => $config['guidelines'],
			'skills' => $config['skills'],
			'mcp' => $config['mcp'],
		];
		$installedModules = $config['modules'];
		$installedAgents = $config['agents'];

		$explicitMode = $this->isExplicitFlagMode($input, $asJson);

		if ($explicitMode) {
			$selectedFeatures = [];
			if ($this->hasExplicitFeatureFlags($input)) {
				foreach (array_keys(self::FEATURES) as $key) {
					if ((bool) $input->getOption($key)) {
						$selectedFeatures[] = $key;
					}
				}
			} else {
				foreach (array_keys(self::FEATURES) as $key) {
					if ($installedFeatures[$key]) {
						$selectedFeatures[] = $key;
					}
				}
				if ($selectedFeatures === [] && !$manager->hasConfig()) {
					$selectedFeatures = array_keys(self::FEATURES);
				}
			}
		} else {
			$defaults = [];
			foreach (self::FEATURES as $key => $_label) {
				if ($installedFeatures[$key]) {
					$defaults[] = $key;
				}
			}
			if ($defaults === []) {
				$defaults = array_keys(self::FEATURES);
			}
			/** @var list<string> $selectedFeatures */
			$selectedFeatures = array_values(multiselect(
				label: 'Which Boost features would you like to configure?',
				options: self::FEATURES,
				default: $defaults,
				hint: 'This will configure the selected features',
			));
		}

		$availableModules = $manager->getDiscoverableModules();
		$moduleChoices = array_keys($availableModules);

		$selectedModules = [];
		$modulesOpt = $input->getOption('modules');
		if ($modulesOpt !== null) {
			$selectedModules = array_values(array_filter(array_map('trim', explode(',', (string) $modulesOpt))));
			$unknownModules = array_values(array_diff($selectedModules, $moduleChoices));
			if ($unknownModules !== []) {
				return $this->failCommand(
					$output,
					$asJson,
					'INVALID_MODULES',
					'Unknown or non-discoverable module(s) in --modules: ' . implode(', ', $unknownModules)
				);
			}
		} elseif ($explicitMode) {
			$selectedModules = $installedModules;
		} elseif ($moduleChoices !== []) {
			$moduleOptions = [];
			foreach ($moduleChoices as $m) {
				$moduleOptions[$m] = $m;
			}
			/** @var list<string> $selectedModules */
			$selectedModules = array_values(multiselect(
				label: 'Which third-party AI guidelines/skills would you like to install?',
				options: $moduleOptions,
				default: $installedModules,
				hint: 'Select to install, deselect to remove.',
				required: false
			));
		} else {
			note('No third-party modules with Boost resources detected.');
		}

		$agentOptions = $manager->agentOptions($selectedFeatures);
		$selectedAgents = [];
		$agentsOpt = $input->getOption('agents');
		if ($agentsOpt !== null) {
			$rawAgentNames = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $agentsOpt)))));
			$unknownAgents = [];
			foreach ($rawAgentNames as $rawAgentName) {
				if ($manager->normalizeAgentNames([$rawAgentName]) === []) {
					$unknownAgents[] = $rawAgentName;
				}
			}
			if ($unknownAgents !== []) {
				return $this->failCommand(
					$output,
					$asJson,
					'INVALID_AGENTS',
					'Invalid agent name(s) in --agents: ' . implode(', ', $unknownAgents)
				);
			}
			$selectedAgents = $manager->normalizeAgentNames($rawAgentNames);
		} elseif ($explicitMode) {
			$selectedAgents = $installedAgents !== []
				? $installedAgents
				: $manager->detectAvailableAgents();
		} elseif ($agentOptions !== []) {
			$defaultAgents = $installedAgents !== []
				? $installedAgents
				: $manager->detectAvailableAgents();

			$defaultAgents = array_values(array_filter(
				$defaultAgents,
				static fn(string $agentName): bool => array_key_exists($agentName, $agentOptions)
			));

			$promptOptions = [];
			foreach ($agentOptions as $agent) {
				$promptOptions[$agent->name()] = $agent->displayName();
			}

			/** @var list<string> $selectedAgents */
			$selectedAgents = array_values(multiselect(
				label: 'Which AI agents would you like to configure?',
				options: $promptOptions,
				default: $defaultAgents,
				required: false
			));
		}

		if (!$asJson) {
			$output->writeln("\n  <fg=yellow>Processing changes...</>\n");
		}

		try {
			$transition = $manager->applyConfiguration($selectedFeatures, $selectedModules, $selectedAgents);
			$savedConfig = $manager->loadConfig();
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'INSTALL_FAILED', $e->getMessage());
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'config' => $savedConfig,
				'changes' => $transition,
			]);
		}

		foreach ($transition['featuresRemoved'] as $featureKey) {
			$output->writeln('  <fg=red>✗ Removed ' . self::FEATURES[$featureKey] . '</>');
		}
		foreach ($transition['featuresInstalled'] as $featureKey) {
			$output->writeln('  <fg=green>✓ Installed ' . self::FEATURES[$featureKey] . '</>');
		}
		if ($transition['synced']) {
			info('Syncing Boost configuration...');
			$output->writeln("  <fg=green>✓ Sync complete</>\n");
		}
		foreach ($manager->resolveConfiguredAgents($transition['agentsInstalled']) as $agent) {
			$output->writeln("  <fg=green>✓ Configured {$agent->displayName()} ({$agent->guidelinesPath()})</>");
		}
		foreach ($manager->resolveConfiguredAgents($transition['agentsRemoved']) as $agent) {
			$output->writeln("  <fg=red>✗ Removed {$agent->displayName()} ({$agent->guidelinesPath()})</>");
		}

		$this->displaySummary($output, $manager, $projectRoot, $selectedFeatures, $savedConfig['agents'], $savedConfig['modules']);

		$output->writeln("\n  ┌─────────────────────────────────────────────────────────────────┐");
		$output->writeln("  │  Enjoy the boost. Check your AI agent's MD file in project root.│");
		$output->writeln("  │  https://github.com/trk/processwire-boost/                      │");
		$output->writeln("  └─────────────────────────────────────────────────────────────────┘\n");
		return Command::SUCCESS;
	}

	/**
	 * @param list<string> $selectedFeatures
	 * @param list<string> $selectedAgents
	 * @param list<string> $selectedModules
	 */
	private function displaySummary(
		OutputInterface $output,
		BoostManager $manager,
		string $projectRoot,
		array $selectedFeatures,
		array $selectedAgents,
		array $selectedModules
	): void {
		$output->writeln("\n  ┌─────────────────────────────────────────────────────────────┐");
		$output->writeln("  │                    Installation Summary                    │");
		$output->writeln("  └─────────────────────────────────────────────────────────────┘\n");

		$resolvedAgents = $manager->resolveConfiguredAgents($selectedAgents);
		$guidelineCount = (is_file($projectRoot . '/AGENTS.md') ? 1 : 0)
			+ count(array_unique(array_filter(array_map(
				static fn(Agent $agent): ?string => $agent->guidelinesPath() !== 'AGENTS.md' ? $agent->guidelinesPath() : null,
				$resolvedAgents
			))));
		$skillCount = count(glob($projectRoot . '/.agents/skills/*/SKILL.md') ?: []);

		$featureLabels = [];
		foreach ($selectedFeatures as $key) {
			if (isset(self::FEATURES[$key])) {
				$featureLabels[] = self::FEATURES[$key];
			}
		}
		$output->writeln('  <fg=yellow>Installed Features:</> ' . implode(', ', $featureLabels) . "\n");

		if (in_array('guidelines', $selectedFeatures, true)) {
			$output->writeln("  <fg=green>✓ {$guidelineCount}</> guidelines installed");
		}

		if (in_array('skills', $selectedFeatures, true)) {
			$output->writeln("  <fg=green>✓ {$skillCount}</> skills synced");
		}

		if (in_array('mcp', $selectedFeatures, true)) {
			$output->writeln('  <fg=green>✓</> MCP servers configured');
		}

		if ($selectedModules !== []) {
			$output->writeln("\n  <fg=yellow>Third-party modules:</> " . implode(', ', $selectedModules));
		}

		if ($resolvedAgents !== []) {
			$agents = array_map(
				static fn(Agent $agent): string => $agent->displayName(),
				$resolvedAgents
			);

			$output->writeln("\n  <fg=yellow>AI Agents:</> " . implode(', ', $agents));
		}

		$output->writeln('');
	}

	private function displayBanner(OutputInterface $output): void
	{
		$output->writeln('<fg=cyan>██████╗  ██████╗  ██████╗ ███████╗████████╗</>');
		$output->writeln('<fg=cyan>██╔══██╗██╔═══██╗██╔═══██╗██╔════╝╚══██╔══╝</>');
		$output->writeln('<fg=cyan>██████╔╝██║   ██║██║   ██║███████╗   ██║   </>');
		$output->writeln('<fg=cyan>██╔══██╗██║   ██║██║   ██║╚════██║   ██║   </>');
		$output->writeln('<fg=cyan>██████╔╝╚██████╔╝╚██████╔╝███████║   ██║   </>');
		$output->writeln("<fg=cyan>╚═════╝  ╚═════╝  ╚═════╝ ╚══════╝   ╚═╝   </>\n");
		$output->writeln(" <fg=cyan>ProcessWire Boost :: Install</>\n");
	}
}

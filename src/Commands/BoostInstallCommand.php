<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Boost\BoostManager;
use Totoglu\Console\Boost\Install\Agents\Agent;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;

final class BoostInstallCommand extends Command
{
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
			->addOption('agents', 'a', InputOption::VALUE_OPTIONAL, 'Comma-separated agents to configure');
	}

	private function isExplicitFlagMode(InputInterface $input): bool
	{
		return (bool) $input->getOption('guidelines')
			|| (bool) $input->getOption('skills')
			|| (bool) $input->getOption('mcp')
			|| $input->getOption('modules') !== null
			|| $input->getOption('agents') !== null
			|| !$input->isInteractive();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$this->displayBanner();

		intro('Managing ProcessWire Boost installation...');

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

		$explicitMode = $this->isExplicitFlagMode($input);

		if ($explicitMode) {
			$selectedFeatures = [];
			foreach (array_keys(self::FEATURES) as $key) {
				if ($installedFeatures[$key] || (bool) $input->getOption($key)) {
					$selectedFeatures[] = $key;
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
		if (is_string($modulesOpt) && $modulesOpt !== '') {
			$selectedModules = array_values(array_filter(array_map('trim', explode(',', $modulesOpt))));
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
		if (is_string($agentsOpt) && $agentsOpt !== '') {
			$selectedAgents = $manager->normalizeAgentNames(
				array_values(array_filter(array_map('trim', explode(',', $agentsOpt))))
			);
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

		$output->writeln("\n  <fg=yellow>Processing changes...</>\n");

		$toInstall = [];
		$toRemove = [];
		foreach (self::FEATURES as $key => $_label) {
			$isSelected = in_array($key, $selectedFeatures, true);
			$isInstalled = $installedFeatures[$key];
			if ($isSelected && !$isInstalled) {
				$toInstall[] = $key;
			} elseif (!$isSelected && $isInstalled) {
				$toRemove[] = $key;
			}
		}

		$modulesToInstall = array_diff($selectedModules, $installedModules);
		$modulesToRemove = array_diff($installedModules, $selectedModules);
		$agentsToInstall = array_values(array_diff($selectedAgents, $installedAgents));
		$agentsToRemove = array_values(array_diff($installedAgents, $selectedAgents));

		$installedAgentInstances = $manager->resolveConfiguredAgents($installedAgents);

		foreach ($toRemove as $featureKey) {
			$output->writeln('  <fg=red>✗ Removing ' . self::FEATURES[$featureKey] . '...</>');
			$manager->uninstallFeature($featureKey, $installedAgentInstances);
		}

		foreach ($toInstall as $featureKey) {
			$output->writeln('  <fg=green>✓ Installing ' . self::FEATURES[$featureKey] . '...</>');
		}

		$shouldSync = $toInstall !== []
			|| $toRemove !== []
			|| $modulesToInstall !== []
			|| $modulesToRemove !== []
			|| $selectedFeatures !== [];

		if ($shouldSync) {
			info('Syncing Boost configuration...');
			$agents = $manager->resolveConfiguredAgents($selectedAgents);
			$manager->install($selectedFeatures, $selectedModules, $agents);
			$output->writeln("  <fg=green>✓ Sync complete</>\n");
		}

		foreach ($manager->resolveConfiguredAgents($agentsToInstall) as $agent) {
			$output->writeln("  <fg=green>✓ Configured {$agent->displayName()} ({$agent->guidelinesPath()})</>");
		}

		foreach ($manager->resolveConfiguredAgents($agentsToRemove) as $agent) {
			$manager->uninstallAgent($agent);
			$output->writeln("  <fg=red>✗ Removed {$agent->displayName()} ({$agent->guidelinesPath()})</>");
		}

		$manager->saveConfig([
			'version' => BoostManager::VERSION,
			'guidelines' => in_array('guidelines', $selectedFeatures, true),
			'skills' => in_array('skills', $selectedFeatures, true),
			'mcp' => in_array('mcp', $selectedFeatures, true),
			'modules' => array_values($selectedModules),
			'agents' => array_values($selectedAgents),
			'generated_at' => date('Y-m-d H:i:s'),
		]);

		$this->displaySummary($output, $manager, $projectRoot, $selectedFeatures, $selectedAgents, $selectedModules);

		$output->writeln("\n  ┌─────────────────────────────────────────────────────────────────┐");
		$output->writeln("  │  Enjoy the boost 🚀 Check your AI agent's MD file in root.      │");
		$output->writeln("  │  📦 https://github.com/trk/processwire-boost/                  │");
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
			+ count(array_filter(array_map(
				static fn(Agent $agent): ?string => $agent->guidelinesPath() !== 'AGENTS.md' ? $agent->guidelinesPath() : null,
				$resolvedAgents
			)));
		$skillCount = count(glob($projectRoot . '/.agents/skills/*/SKILL.md') ?: []);

		$featureLabels = [];
		foreach ($selectedFeatures as $key) {
			if (isset(self::FEATURES[$key])) {
				$featureLabels[] = self::FEATURES[$key];
			}
		}
		$output->writeln('  📋 <fg=yellow>Installed Features:</> ' . implode(', ', $featureLabels) . "\n");

		if (in_array('guidelines', $selectedFeatures, true)) {
			$output->writeln("  ✅ <fg=green>{$guidelineCount}</> guidelines installed");
		}

		if (in_array('skills', $selectedFeatures, true)) {
			$output->writeln("  ✅ <fg=green>{$skillCount}</> skills synced");
		}

		if (in_array('mcp', $selectedFeatures, true)) {
			$output->writeln('  ✅ MCP servers configured');
		}

		if ($selectedModules !== []) {
			$output->writeln("\n  📦 <fg=yellow>Third-party modules:</> " . implode(', ', $selectedModules));
		}

		if ($resolvedAgents !== []) {
			$agents = array_map(
				static fn(Agent $agent): string => $agent->displayName(),
				$resolvedAgents
			);

			$output->writeln("\n  🤖 <fg=yellow>AI Agents:</> " . implode(', ', $agents));
		}

		$output->writeln('');
	}

	private function displayBanner(): void
	{
		$gradient = [
			"\033[38;5;90m",
			"\033[38;5;90m",
			"\033[38;5;96m",
			"\033[38;5;102m",
			"\033[38;5;109m",
			"\033[38;5;109m",
		];
		echo $gradient[0] . "██████╗  ██████╗  ██████╗ ███████╗████████╗\n";
		echo $gradient[1] . "██╔══██╗██╔═══██╗██╔═══██╗██╔════╝╚══██╔══╝\n";
		echo $gradient[2] . "██████╔╝██║   ██║██║   ██║███████╗   ██║   \n";
		echo $gradient[3] . "██╔══██╗██║   ██║██║   ██║╚════██║   ██║   \n";
		echo $gradient[4] . "██████╔╝╚██████╔╝╚██████╔╝███████║   ██║   \n";
		echo $gradient[5] . "╚═════╝  ╚═════╝  ╚═════╝ ╚══════╝   ╚═╝   \n";
		echo "\033[0m";
		echo "\n \033[38;5;109m✦ ProcessWire Boost :: Install :: We Must Ship ✦\033[0m\n\n";
	}
}

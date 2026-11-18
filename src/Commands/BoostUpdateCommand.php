<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Boost\BoostManager;
use Totoglu\Console\Traits\InteractWithProcessWire;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\outro;

final class BoostUpdateCommand extends Command
{
	use InteractWithProcessWire;

	protected function configure(): void
	{
		$this
			->setName('boost:update')
			->setDescription('Re-sync ProcessWire Boost guidelines & skills from saved configuration')
			->addOption('discover', null, InputOption::VALUE_NONE, 'Automatically add newly discovered module resources')
			->addOption('no-discover', null, InputOption::VALUE_NONE, 'Skip discovering newly available module resources')
			->addOption('ignore-skills', null, InputOption::VALUE_NONE, 'Skip updating the central skills directory')
			->addOption('json', null, InputOption::VALUE_NONE, 'Output structured JSON');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$asJson = (bool) $input->getOption('json');

		if (!$asJson) {
			intro('ProcessWire Boost :: Update');
		}

		if ($input->getOption('discover') && $input->getOption('no-discover')) {
			return $this->failCommand($output, $asJson, 'INVALID_OPTIONS', 'You cannot combine --discover and --no-discover.');
		}

		$projectRoot = getcwd() ?: '.';
		$manager = new BoostManager($projectRoot);

		if (!$manager->hasConfig()) {
			return $this->failCommand($output, $asJson, 'NOT_CONFIGURED', 'Boost is not initialized. Run boost:install first.');
		}

		$config = $manager->loadConfig();
		if ($config['agents'] === []) {
			return $this->failCommand($output, $asJson, 'NO_AGENTS', 'No agents configured. Run boost:install first.');
		}

		if (!$asJson) {
			info('Re-syncing guidelines and skills...');
		}

		$modules = $config['modules'];
		if (!$input->getOption('no-discover')) {
			$modules = $this->discoverNewModules($manager, $modules, $input, $asJson);
		}

		$features = [];
		if ($config['guidelines']) {
			$features[] = 'guidelines';
		}
		if ($config['skills'] && !$input->getOption('ignore-skills')) {
			$features[] = 'skills';
		}
		if ($config['mcp']) {
			$features[] = 'mcp';
		}

		$agents = $manager->resolveConfiguredAgents($config['agents']);

		try {
			$manager->install(
				features: $features,
				modules: $modules,
				agents: $agents,
			);

			$config['modules'] = $modules;
			$manager->saveConfig($config);
		} catch (\Throwable $e) {
			return $this->failCommand($output, $asJson, 'UPDATE_FAILED', $e->getMessage());
		}

		if ($asJson) {
			return $this->outputJsonSuccess($output, [
				'features' => $features,
				'modules' => $modules,
				'agents' => $config['agents'],
			]);
		}

		info('Boost guidelines and skills updated successfully.');
		outro('Done');
		return Command::SUCCESS;
	}

	/**
	 * @param list<string> $configuredModules
	 * @return list<string>
	 */
	private function discoverNewModules(BoostManager $manager, array $configuredModules, InputInterface $input, bool $asJson): array
	{
		$discoverable = array_keys($manager->getDiscoverableModules());
		$newModules = array_values(array_diff($discoverable, $configuredModules));

		if ($newModules === []) {
			return $configuredModules;
		}

		if ((bool) $input->getOption('discover')) {
			return array_values(array_unique(array_merge($configuredModules, $newModules)));
		}

		if (!$input->isInteractive() || $asJson) {
			return $configuredModules;
		}

		$options = array_combine($newModules, $newModules);
		/** @var list<string> $selectedModules */
		$selectedModules = array_values(multiselect(
			label: 'New Boost-aware modules were discovered. Which would you like to add?',
			options: $options,
			required: false,
			hint: 'Selected modules will be added to the saved Boost configuration',
		));

		if ($selectedModules === []) {
			return $configuredModules;
		}

		return array_values(array_unique(array_merge($configuredModules, $selectedModules)));
	}
}

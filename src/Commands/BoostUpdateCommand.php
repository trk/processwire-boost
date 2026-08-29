<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Boost\BoostManager;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\info;

final class BoostUpdateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('boost:update')
            ->setDescription('Re-sync ProcessWire Boost guidelines & skills from saved configuration')
            ->addOption('discover', null, InputOption::VALUE_NONE, 'Discover newly available module resources (default)')
            ->addOption('no-discover', null, InputOption::VALUE_NONE, 'Skip discovering newly available module resources')
            ->addOption('ignore-skills', null, InputOption::VALUE_NONE, 'Skip updating the central skills directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        intro('ProcessWire Boost :: Update');

        if ($input->getOption('discover') && $input->getOption('no-discover')) {
            $output->writeln('<error>You cannot combine --discover and --no-discover.</error>');
            return Command::FAILURE;
        }

        $projectRoot = getcwd() ?: '.';
        $configPath = $projectRoot . '/.agents/boost.json';

        if (!file_exists($configPath)) {
            $output->writeln('<error>Please run boost:install first.</error>');
            return Command::FAILURE;
        }

        $config = json_decode(file_get_contents($configPath) ?: '', true) ?: [];
        if (empty($config['agents'])) {
            $output->writeln('<error>No agents configured. Please run boost:install first.</error>');
            return Command::FAILURE;
        }

        $manager = new BoostManager($projectRoot);

        info('Re-syncing guidelines and skills...');

        $modules = $config['modules'] ?? [];
        if (!$input->getOption('no-discover')) {
            $modules = $this->discoverNewModules($manager, $modules, $input);
        }

        $features = [];
        if ($config['guidelines'] ?? false) {
            $features[] = 'guidelines';
        }
        if (($config['skills'] ?? false) && !$input->getOption('ignore-skills')) {
            $features[] = 'skills';
        }
        if ($config['mcp'] ?? false) {
            $features[] = 'mcp';
        }

        $agents = $manager->resolveConfiguredAgents($config['agents'] ?? []);

        $manager->install(
            features: $features,
            modules: $modules,
            agents: $agents,
        );

        $config['modules'] = array_values($modules);
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));

        info('Boost guidelines and skills updated successfully.');

        outro('Done');
        return Command::SUCCESS;
    }

    /**
     * @param list<string> $configuredModules
     * @return list<string>
     */
    private function discoverNewModules(BoostManager $manager, array $configuredModules, InputInterface $input): array
    {
        $discoverable = array_keys($manager->getDiscoverableModules());
        $newModules = array_values(array_diff($discoverable, $configuredModules));

        if ($newModules === []) {
            return $configuredModules;
        }

        if ($input->getOption('discover') && !$input->isInteractive()) {
            return array_values(array_unique(array_merge($configuredModules, $newModules)));
        }

        if (!$input->isInteractive()) {
            return $configuredModules;
        }

        $options = array_combine($newModules, $newModules);
        $selectedModules = multiselect(
            label: 'New Boost-aware modules were discovered. Which would you like to add?',
            options: $options,
            required: false,
            hint: 'Selected modules will be added to the saved Boost configuration',
        );

        if ($selectedModules === []) {
            return $configuredModules;
        }

        return array_values(array_unique(array_merge($configuredModules, $selectedModules)));
    }
}

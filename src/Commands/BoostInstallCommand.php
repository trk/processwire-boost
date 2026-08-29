<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Boost\BoostManager;
use Totoglu\Console\Boost\Contracts\SupportsGuidelines;
use Totoglu\Console\Boost\Contracts\SupportsMcp;
use Totoglu\Console\Boost\Contracts\SupportsSkills;
use Totoglu\Console\Boost\Install\Agents\Agent;
use Totoglu\Console\Boost\Install\AgentsDetector;
use Totoglu\Console\Boost\Install\McpWriter;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\info;

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
        return $input->getOption('guidelines') || $input->getOption('skills') || $input->getOption('mcp');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->displayBanner();

        intro('Managing ProcessWire Boost installation...');

        $projectRoot = getcwd() ?: '.';
        $manager = new BoostManager($projectRoot);
        $agentsDetector = new AgentsDetector($manager);
        $configPath = $projectRoot . '/.agents/boost.json';

        $config = [
            'version' => '0.2.1',
            'guidelines' => false,
            'skills' => false,
            'mcp' => false,
            'modules' => [],
            'agents' => [],
            'generated_at' => null,
        ];
        if (file_exists($configPath)) {
            $config = array_merge($config, json_decode(file_get_contents($configPath) ?: '{}', true) ?: []);
        }
        $installedFeatures = [
            'guidelines' => $config['guidelines'] ?? false,
            'skills' => $config['skills'] ?? false,
            'mcp' => $config['mcp'] ?? false,
        ];
        $installedModules = $config['modules'] ?? [];
        $installedAgents = $this->normalizeConfiguredAgentNames($manager, $config['agents'] ?? []);

        $explicitMode = $this->isExplicitFlagMode($input);

        if ($explicitMode) {
            $selectedFeatures = [];
            if ($input->getOption('guidelines')) $selectedFeatures[] = 'guidelines';
            if ($input->getOption('skills')) $selectedFeatures[] = 'skills';
            if ($input->getOption('mcp')) $selectedFeatures[] = 'mcp';
        } else {
            $featureLabels = [];
            foreach (self::FEATURES as $key => $label) {
                $featureLabels[$key] = $label;
            }
            $defaults = [];
            foreach (self::FEATURES as $key => $label) {
                if ($installedFeatures[$key]) $defaults[] = $key;
            }
            if (empty($defaults)) $defaults = array_keys(self::FEATURES);
            $selectedFeatures = multiselect(
                label: 'Which Boost features would you like to configure?',
                options: $featureLabels,
                default: $defaults,
                hint: 'This will configure the selected features',
            );
        }

        $availableModules = $manager->getDiscoverableModules();
        $moduleChoices = array_keys($availableModules);

        $selectedModules = [];
        $modulesOpt = $input->getOption('modules');
        if ($modulesOpt) {
            $selectedModules = array_filter(array_map('trim', explode(',', $modulesOpt)));
        } elseif (!empty($moduleChoices)) {
            $moduleOptions = [];
            foreach ($moduleChoices as $m) {
                $moduleOptions[$m] = $m;
            }
            $selectedModules = multiselect(
                label: 'Which third-party AI guidelines/skills would you like to install?',
                options: $moduleOptions,
                default: $installedModules,
                hint: 'Select to install, deselect to remove.',
                required: false
            );
        } else {
            note('No third-party modules with Boost resources detected.');
        }

        $agentOptions = $manager->agentOptions($selectedFeatures);
        $selectedAgents = [];
        $agentsOpt = $input->getOption('agents');
        if ($agentsOpt) {
            $selectedAgents = $this->normalizeConfiguredAgentNames(
                $manager,
                array_filter(array_map('trim', explode(',', $agentsOpt)))
            );
        } elseif ($agentOptions !== []) {
            $defaultAgents = $installedAgents !== []
                ? $installedAgents
                : $this->resolveDetectedDefaults($manager, $agentsDetector, $projectRoot);

            $defaultAgents = array_values(array_filter(
                $defaultAgents,
                static fn(string $agentName): bool => array_key_exists($agentName, $agentOptions)
            ));

            $promptOptions = [];
            foreach ($agentOptions as $agent) {
                $promptOptions[$agent->name()] = $agent->displayName();
            }

            $selectedAgents = multiselect(
                label: 'Which AI agents would you like to configure?',
                options: $promptOptions,
                default: $defaultAgents,
                required: false
            );
        }

        $output->writeln("\n  <fg=yellow>Processing changes...</>\n");

        $toInstall = [];
        $toRemove = [];
        foreach (self::FEATURES as $key => $label) {
            $isSelected = in_array($key, $selectedFeatures);
            $isInstalled = $installedFeatures[$key] ?? false;
            if ($isSelected && !$isInstalled) {
                $toInstall[] = $key;
            } elseif (!$isSelected && $isInstalled) {
                $toRemove[] = $key;
            }
        }

        $modulesToInstall = array_diff($selectedModules, $installedModules);
        $modulesToRemove = array_diff($installedModules, $selectedModules);
        $agentsToInstall = array_diff($selectedAgents, $installedAgents);
        $agentsToRemove = array_diff($installedAgents, $selectedAgents);

        $installedAgentInstances = $manager->resolveConfiguredAgents($installedAgents);

        foreach ($toRemove as $featureKey) {
            $output->writeln("  <fg=red>✗ Removing " . self::FEATURES[$featureKey] . "...</>");
            $manager->uninstallFeature($featureKey, $installedAgentInstances);
        }

        foreach ($toInstall as $featureKey) {
            $output->writeln("  <fg=green>✓ Installing " . self::FEATURES[$featureKey] . "...</>");
        }

        $shouldSync = !empty($toInstall) || !empty($toRemove) || !empty($modulesToInstall) || !empty($modulesToRemove) || !empty($selectedFeatures);

        if ($shouldSync) {
            info('Syncing Boost configuration...');
            $agents = $manager->resolveConfiguredAgents($selectedAgents);
            $manager->sync($selectedFeatures, $selectedModules, $agents);
            $output->writeln("  <fg=green>✓ Sync complete</>\n");
        }

        foreach ($manager->resolveConfiguredAgents($agentsToInstall) as $agent) {
            $output->writeln("  <fg=green>✓ Configured {$agent->displayName()} ({$agent->guidelinesPath()})</>");
        }

        foreach ($manager->resolveConfiguredAgents($agentsToRemove) as $agent) {
            if ($agent instanceof SupportsGuidelines && $agent->guidelinesPath() !== 'AGENTS.md') {
                $agentFile = $projectRoot . '/' . $agent->guidelinesPath();
                if (file_exists($agentFile)) {
                    @unlink($agentFile);
                }
            }
            if ($agent instanceof SupportsSkills) {
                $skillsPath = $agent->skillsPath();
                if ($skillsPath !== '' && $skillsPath !== '.agents/skills') {
                    $skillsFullPath = $projectRoot . '/' . $skillsPath;
                    if (is_dir($skillsFullPath)) {
                        $this->removeDirectoryContents($skillsFullPath);
                    }
                }
            }
            if ($agent instanceof SupportsMcp) {
                try {
                    (new McpWriter($agent, $projectRoot))->remove();
                } catch (\Throwable) {
                    // Non-fatal: individual agent files may have permission issues
                }
                $mcpConfig = $agent->mcpConfigPath();
                if (is_string($mcpConfig) && $mcpConfig !== '') {
                    $mcpFull = $projectRoot . '/' . $mcpConfig;
                    // Only remove whole MCP file if it's empty or exclusive to Boost;
                    // safer to leave and let McpWriter strip our server entry.
                    unset($mcpFull);
                }
            }
            $output->writeln("  <fg=red>✗ Removed {$agent->displayName()} ({$agent->guidelinesPath()})</>");
        }

        $aiDir = $projectRoot . '/.agents';
        if (!is_dir($aiDir)) {
            mkdir($aiDir, 0755, true);
        }

        $newConfig = [
            'version' => '0.2.1',
            'guidelines' => in_array('guidelines', $selectedFeatures),
            'skills' => in_array('skills', $selectedFeatures),
            'mcp' => in_array('mcp', $selectedFeatures),
            'modules' => array_values($selectedModules),
            'agents' => array_values($selectedAgents),
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        if ($explicitMode) {
            $config = array_merge($config, $newConfig);
        } else {
            $config = $newConfig;
        }

        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));

        $this->displaySummary($output, $selectedFeatures, $selectedAgents, $selectedModules);

        $output->writeln("\n  ┌─────────────────────────────────────────────────────────────────┐");
        $output->writeln("  │  Enjoy the boost 🚀 Check your AI agent's MD file in root.      │");
        $output->writeln("  │  📦 https://github.com/trk/processwire-boost/                  │");
        $output->writeln("  └─────────────────────────────────────────────────────────────────┘\n");
        return Command::SUCCESS;
    }

    /**
     * @param BoostManager $manager Already instantiated manager for the current project root
     * @param list<string> $agentNames
     * @return list<Agent>
     */
    private function resolveAgents(BoostManager $manager, array $agentNames): array
    {
        return $manager->resolveConfiguredAgents($agentNames);
    }

    private function removeDirectoryContents(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $item) {
            $full = $path . '/' . $item;
            is_dir($full) ? $this->removeDirectoryContents($full) : @unlink($full);
        }
    }

    private function displaySummary(OutputInterface $output, array $selectedFeatures, array $selectedAgents, array $selectedModules): void
    {
        $output->writeln("\n  ┌─────────────────────────────────────────────────────────────┐");
        $output->writeln("  │                    Installation Summary                    │");
        $output->writeln("  └─────────────────────────────────────────────────────────────┘\n");

        $projectRoot = getcwd() ?: '.';
        $summaryManager = new BoostManager($projectRoot);
        $guidelineCount = (is_file($projectRoot . '/AGENTS.md') ? 1 : 0)
            + count(array_filter(array_map(
                static fn(Agent $agent): ?string => $agent->guidelinesPath() !== 'AGENTS.md' ? $agent->guidelinesPath() : null,
                $this->resolveAgents($summaryManager, $selectedAgents)
            )));
        $skillCount = count(glob($projectRoot . '/.agents/skills/*/SKILL.md') ?: []);

        $featureLabels = [];
        foreach ($selectedFeatures as $key) {
            $featureLabels[] = self::FEATURES[$key];
        }
        $output->writeln("  📋 <fg=yellow>Installed Features:</> " . implode(', ', $featureLabels) . "\n");

        if (in_array('guidelines', $selectedFeatures)) {
            $output->writeln("  ✅ <fg=green>{$guidelineCount}</> guidelines installed");
        }

        if (in_array('skills', $selectedFeatures)) {
            $output->writeln("  ✅ <fg=green>{$skillCount}</> skills synced");
        }

        if (in_array('mcp', $selectedFeatures)) {
            $output->writeln("  ✅ MCP servers configured");
        }

        if (!empty($selectedModules)) {
            $output->writeln("\n  📦 <fg=yellow>Third-party modules:</> " . implode(', ', $selectedModules));
        }

        if (!empty($selectedAgents)) {
            $agents = array_map(
                static fn(Agent $agent): string => $agent->displayName(),
                $this->resolveAgents($summaryManager, $selectedAgents)
            );

            $output->writeln("\n  🤖 <fg=yellow>AI Agents:</> " . implode(', ', $agents));
        }

        $output->writeln("");
    }

    /**
     * @param list<string> $configuredAgents
     * @return list<string>
     */
    private function normalizeConfiguredAgentNames(BoostManager $manager, array $configuredAgents): array
    {
        return array_values(array_map(
            static fn(Agent $agent): string => $agent->name(),
            $manager->resolveConfiguredAgents($configuredAgents)
        ));
    }

    /**
     * @return list<string>
     */
    private function resolveDetectedDefaults(BoostManager $manager, AgentsDetector $agentsDetector, string $projectRoot): array
    {
        $detected = array_values(array_unique(array_merge(
            $agentsDetector->discoverProjectInstalledAgents($projectRoot),
            $agentsDetector->discoverSystemInstalledAgents()
        )));

        return $this->normalizeConfiguredAgentNames($manager, $detected);
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

<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\Console\Boost\BoostManager;
use Totoglu\Console\Boost\Install\Agents\Amp as AmpAgent;
use Totoglu\Console\Boost\Install\Agents\ClaudeCode as ClaudeAgent;
use Totoglu\Console\Boost\Install\Agents\Codex as CodexAgent;
use Totoglu\Console\Boost\Install\Agents\Copilot as CopilotAgent;
use Totoglu\Console\Boost\Install\Agents\Cursor as CursorAgent;
use Totoglu\Console\Boost\Install\Agents\Gemini as GeminiAgent;
use Totoglu\Console\Boost\Install\Agents\Junie as JunieAgent;
use Totoglu\Console\Boost\Install\Agents\OpenCode as OpenCodeAgent;
use Totoglu\Console\Boost\Install\Agents\Trae as TraeAgent;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\info;

final class BoostUpdateCommand extends Command
{
    private const AGENT_MAP = [
        'Amp' => AmpAgent::class,
        'Claude Code' => ClaudeAgent::class,
        'Codex' => CodexAgent::class,
        'Cursor' => CursorAgent::class,
        'Gemini CLI' => GeminiAgent::class,
        'GitHub Copilot' => CopilotAgent::class,
        'Junie' => JunieAgent::class,
        'OpenCode' => OpenCodeAgent::class,
        'Trae' => TraeAgent::class,
    ];

    private const FEATURE_MAP = [
        'guidelines' => 'AI Guidelines',
        'skills' => 'Agent Skills',
        'mcp' => 'Boost MCP Server Configuration',
    ];

    protected function configure(): void
    {
        $this->setName('boost:update')->setDescription('Re-sync ProcessWire Boost guidelines & skills from saved configuration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        intro('ProcessWire Boost :: Update');

        $projectRoot = getcwd();
        $configPath = $projectRoot . '/.agents/boost.json';

        if (!file_exists($configPath)) {
            $output->writeln('<error>Please run boost:install first.</error>');
            return Command::FAILURE;
        }

        $config = json_decode(file_get_contents($configPath) ?: '', true);
        if (empty($config['agents'])) {
            $output->writeln('<error>No agents configured. Please run boost:install first.</error>');
            return Command::FAILURE;
        }

        $manager = new BoostManager($projectRoot);

        info('Re-syncing guidelines and skills...');

        $modules = $config['modules'] ?? [];

        // Map boolean flags to feature labels that BoostManager expects
        $features = [];
        if ($config['guidelines'] ?? false) {
            $features[] = self::FEATURE_MAP['guidelines'];
        }
        if ($config['skills'] ?? false) {
            $features[] = self::FEATURE_MAP['skills'];
        }
        if ($config['mcp'] ?? false) {
            $features[] = self::FEATURE_MAP['mcp'];
        }

        // Resolve agent display names to Agent instances
        $agents = [];
        foreach ($config['agents'] ?? [] as $name) {
            $class = self::AGENT_MAP[$name] ?? null;
            if ($class) {
                $agents[] = new $class();
            }
        }

        $manager->install(
            features: $features,
            modules: $modules,
            agents: $agents,
        );

        info('Boost guidelines and skills updated successfully.');

        outro('Done');
        return Command::SUCCESS;
    }
}
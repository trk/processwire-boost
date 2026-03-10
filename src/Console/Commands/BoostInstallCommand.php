<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Totoglu\ProcessWire\Boost\BoostManager;
use Totoglu\ProcessWire\Boost\SkillBuilder;
use Totoglu\ProcessWire\Boost\Install\Agents\Gemini as GeminiAgent;
use Totoglu\ProcessWire\Boost\Install\Agents\Codex as CodexAgent;
use Totoglu\ProcessWire\Boost\Install\Agents\Cursor as CursorAgent;
use Totoglu\ProcessWire\Boost\Install\Agents\Copilot as CopilotAgent;
use Totoglu\ProcessWire\Boost\Install\Agents\ClaudeCode as ClaudeAgent;
use Totoglu\ProcessWire\Boost\Install\Agents\Amp as AmpAgent;
use Totoglu\ProcessWire\Boost\Install\Agents\Junie as JunieAgent;
use Totoglu\ProcessWire\Boost\Install\Agents\OpenCode as OpenCodeAgent;
use Totoglu\ProcessWire\Boost\Install\Agents\Trae as TraeAgent;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

final class BoostInstallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('boost:install')
            ->setDescription('Initialize the AI helper setup (ProcessWire Boost) with a modern interface.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->displayBanner();

        intro('Starting ProcessWire Boost installation...');

        $projectRoot = getcwd();
        $manager = new BoostManager($projectRoot);

        $step1 = select(
            label: 'Which third-party AI guidelines/skills would you like to install?',
            options: [
                'Skip' => 'skip',
                'Proceed' => 'proceed',
            ],
            default: 'proceed'
        );

        $selectedModules = [];
        if ($step1 === 'proceed') {
            $availableModules = $manager->getDiscoverableModules();
            $moduleChoices = array_keys($availableModules);
            if (!empty($moduleChoices)) {
                $selectedModules = multiselect(
                    label: 'Which third-party AI guidelines/skills would you like to install?',
                    options: array_combine($moduleChoices, $moduleChoices),
                    hint: 'Space to select, Enter to confirm',
                    required: false
                );
            } else {
                note('No third-party modules with Boost resources detected.');
            }
        }

        $step2 = select(
            label: 'Which AI agents would you like to configure?',
            options: [
                'Skip' => 'skip',
                'Proceed' => 'proceed',
            ],
            default: 'proceed'
        );

        $agentChoices = ['Amp','Claude Code','Codex','Cursor','Gemini CLI','GitHub Copilot','Junie','OpenCode','Trae'];
        $selectedAgents = [];
        if ($step2 === 'proceed') {
            $selectedAgents = multiselect(
                label: 'Which AI agents would you like to configure?',
                options: array_combine($agentChoices, $agentChoices),
                required: false
            );
        }

        spin(
            function () use ($manager, $selectedModules, $selectedAgents) {
                $manager->install(['Agent Skills'], $selectedModules, $selectedAgents);
            },
            'Installing and Configuring Boost...'
        );

        if (!empty($selectedAgents)) {
            foreach ($selectedAgents as $agent) {
                $filename = strtoupper(str_replace(' ', '_', $agent)) . '.md';
                if ($agent === 'Cursor') $filename = 'CURSOR.md';
                if ($agent === 'Gemini CLI') $filename = 'GEMINI.md';
                if ($agent === 'Claude Code') $filename = 'CLAUDE.md';
                info("✓ Configured {$agent} ({$filename})");
            }
        }

        $step3 = select(
            label: 'Which AI agents would you like to configure?',
            options: [
                'AI Guidelines' => 'guidelines',
                'Agent Skills' => 'skills',
                'Boost MCP Server Configuration' => 'mcp',
            ],
            default: 'guidelines'
        );

        if ($step3 === 'guidelines') {
            note('Building AI Guidelines for selected agents...');
            spin(function () use ($manager, $selectedAgents) {
                $manager->install(['AI Guidelines'], $selectedModules ?? [], $selectedAgents);
            }, 'Building guidelines...');
            info('Guidelines built');
        }

        if ($step3 === 'skills') {
            note('Building Agent Skills...');
            spin(function () use ($selectedAgents, $projectRoot) {
                if (in_array('Trae', $selectedAgents)) {
                    $sb = new SkillBuilder($projectRoot);
                    $trae = new TraeAgent();
                    $sb->exportForAgent($trae, null, $projectRoot . '/.trae/skills');
                }
                if (in_array('OpenCode', $selectedAgents)) {
                    $sb = new SkillBuilder($projectRoot);
                    $opencode = new OpenCodeAgent();
                    $sb->exportForAgent($opencode, null, $projectRoot . '/.ai/skills');
                }
            }, 'Building skills...');
            info('Skills built');
        }

        if ($step3 === 'mcp' && !empty($selectedAgents)) {
            $pathType = select(
                label: 'Path type for MCP command?',
                options: [
                    'Relative (vendor/bin/wire)' => 'rel',
                    'Absolute (/.../vendor/bin/wire)' => 'abs',
                ],
                default: 'rel'
            );

            $agentsForMcp = [];
            foreach ($selectedAgents as $a) {
                if ($a === 'Gemini CLI') $agentsForMcp[] = new GeminiAgent();
                if ($a === 'Codex') $agentsForMcp[] = new CodexAgent();
                if ($a === 'Cursor') $agentsForMcp[] = new CursorAgent();
                if ($a === 'GitHub Copilot') $agentsForMcp[] = new CopilotAgent();
                if ($a === 'Claude Code') $agentsForMcp[] = new ClaudeAgent();
                if ($a === 'Amp') $agentsForMcp[] = new AmpAgent();
                if ($a === 'Junie') $agentsForMcp[] = new JunieAgent();
                if ($a === 'OpenCode') $agentsForMcp[] = new OpenCodeAgent();
                if ($a === 'Trae') $agentsForMcp[] = new TraeAgent();
            }
            if (!empty($agentsForMcp)) {
                spin(function () use ($agentsForMcp, $pathType, $projectRoot) {
                    $key = 'processwire';
                    $command = 'php';
                    $wire = $pathType === 'abs' ? ($projectRoot . '/vendor/bin/wire') : 'vendor/bin/wire';
                    $args = [$wire, 'boost:mcp'];
                    foreach ($agentsForMcp as $agent) {
                        $agent->installMcp($key, $command, $args, []);
                    }
                }, 'Writing agent MCP configurations...');
                info('Agent MCP configurations completed');
                note("MCP Server is available via: ./vendor/bin/wire boost:mcp");
            }
        }

        $configPath = $projectRoot . '/.ai/boost.json';
        $aiDir = $projectRoot . '/.ai';
        if (!is_dir($aiDir)) {
            mkdir($aiDir, 0755, true);
        }
        $config = [
            'version' => '1.0.0',
            'agents' => $selectedAgents,
            'modules' => $selectedModules,
            'features' => [$step3],
            'generated_at' => date('Y-m-d H:i:s'),
        ];
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));

        outro('Enjoy the boost 🚀 Check your AI agent\'s MD file in root.');
        return Command::SUCCESS;
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
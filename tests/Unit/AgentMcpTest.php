<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Tests\Unit;

use Totoglu\Console\Boost\Contracts\SupportsMcp;
use Totoglu\Console\Boost\Install\Agents\Amp;
use Totoglu\Console\Boost\Install\Agents\Antigravity;
use Totoglu\Console\Boost\Install\Agents\ClaudeCode;
use Totoglu\Console\Boost\Install\Agents\Codex;
use Totoglu\Console\Boost\Install\Agents\Copilot;
use Totoglu\Console\Boost\Install\Agents\Cursor;
use Totoglu\Console\Boost\Install\Agents\Factory;
use Totoglu\Console\Boost\Install\Agents\Gemini;
use Totoglu\Console\Boost\Install\Agents\GrokBuild;
use Totoglu\Console\Boost\Install\Agents\Junie;
use Totoglu\Console\Boost\Install\Agents\Kiro;
use Totoglu\Console\Boost\Install\Agents\OpenCode;
use Totoglu\Console\Boost\Install\Agents\Pi;
use Totoglu\Console\Boost\Install\Agents\Trae;
use Totoglu\Console\Boost\Install\Agents\Zed;
use Totoglu\Console\Boost\Install\McpWriter;
use Totoglu\Console\Boost\Tests\TestCase;

final class AgentMcpTest extends TestCase
{
	public function test_agents_mcp_config_paths_and_keys(): void
	{
		$expectations = [
			Antigravity::class => ['.agents/mcp_config.json', 'mcpServers'],
			ClaudeCode::class => ['.mcp.json', 'mcpServers'],
			Copilot::class => ['.vscode/mcp.json', 'servers'],
			Cursor::class => ['.cursor/mcp.json', 'mcpServers'],
			Factory::class => ['.factory/mcp.json', 'mcpServers'],
			Gemini::class => ['.gemini/settings.json', 'mcpServers'],
			Junie::class => ['.junie/mcp/mcp.json', 'mcpServers'],
			Kiro::class => ['.kiro/settings/mcp.json', 'mcpServers'],
			OpenCode::class => ['opencode.json', 'mcp'],
			Trae::class => ['.trae/mcp.json', 'mcpServers'],
			Zed::class => ['.zed/settings.json', 'context_servers'],
			Amp::class => ['.amp/settings.json', 'amp.mcpServers'],
			Codex::class => ['.codex/config.toml', 'mcp_servers'],
			GrokBuild::class => ['.grok/config.toml', 'mcp_servers'],
		];

		foreach ($expectations as $agentClass => [$expectedPath, $expectedKey]) {
			$agent = new $agentClass();
			$this->assertInstanceOf(SupportsMcp::class, $agent);
			$this->assertSame($expectedPath, $agent->mcpConfigPath(), sprintf('%s path mismatch', $agentClass));
			$this->assertSame($expectedKey, $agent->mcpConfigKey(), sprintf('%s key mismatch', $agentClass));
		}

		$pi = new Pi();
		$this->assertNotInstanceOf(SupportsMcp::class, $pi);
	}

	public function test_copilot_mcp_server_config_includes_type_stdio(): void
	{
		$copilot = new Copilot();
		$config = $copilot->mcpServerConfig('php', ['vendor/bin/wire', 'boost:mcp'], ['FOO' => 'BAR'], '/path/to/project');

		$this->assertSame('stdio', $config['type'] ?? null);
		$this->assertSame('php', $config['command']);
		$this->assertSame(['vendor/bin/wire', 'boost:mcp'], $config['args']);
		$this->assertSame(['FOO' => 'BAR'], $config['env']);
		$this->assertSame('/path/to/project', $config['cwd']);

		$copilot->installMcp('processwire', 'php', ['vendor/bin/wire', 'boost:mcp'], [], $this->tempDir);
		$writtenFile = $this->tempDir . '/.vscode/mcp.json';

		$this->assertFileExists($writtenFile);
		$data = json_decode((string) file_get_contents($writtenFile), true);
		$this->assertIsArray($data);
		$this->assertArrayHasKey('servers', $data);
		$this->assertArrayHasKey('processwire', $data['servers']);
		$this->assertSame('stdio', $data['servers']['processwire']['type']);
		$this->assertSame('php', $data['servers']['processwire']['command']);
	}

	public function test_amp_mcp_server_config_creates_clean_flat_key_without_corruption(): void
	{
		$amp = new Amp();
		$amp->installMcp('processwire', 'php', ['vendor/bin/wire', 'boost:mcp'], [], $this->tempDir);

		$writtenFile = $this->tempDir . '/.amp/settings.json';
		$this->assertFileExists($writtenFile);

		$data = json_decode((string) file_get_contents($writtenFile), true);
		$this->assertIsArray($data);

		// amp.mcpServers must be a flat dictionary of servers
		$this->assertArrayHasKey('amp.mcpServers', $data);
		$this->assertIsArray($data['amp.mcpServers']);
		$this->assertArrayHasKey('processwire', $data['amp.mcpServers']);
		$this->assertSame('php', $data['amp.mcpServers']['processwire']['command']);

		// Must not have nested 'amp' object or empty phantom array
		$this->assertArrayNotHasKey('amp', $data);

		// Uninstall should cleanly remove it
		$amp->uninstallMcp('processwire', $this->tempDir);
		$updated = json_decode((string) file_get_contents($writtenFile), true);
		$this->assertArrayNotHasKey('processwire', $updated['amp.mcpServers']);
	}

	public function test_opencode_mcp_server_config_format(): void
	{
		$openCode = new OpenCode();
		$config = $openCode->mcpServerConfig('php', ['vendor/bin/wire', 'boost:mcp'], [], '/workspace');

		$this->assertSame('local', $config['type']);
		$this->assertTrue($config['enabled']);
		$this->assertSame(['php', 'vendor/bin/wire', 'boost:mcp'], $config['command']);
		$this->assertSame('/workspace', $config['cwd']);

		$openCode->installMcp('processwire', 'php', ['vendor/bin/wire', 'boost:mcp'], [], $this->tempDir);
		$writtenFile = $this->tempDir . '/opencode.json';

		$this->assertFileExists($writtenFile);
		$data = json_decode((string) file_get_contents($writtenFile), true);
		$this->assertSame('https://opencode.ai/config.json', $data['$schema']);
		$this->assertArrayHasKey('mcp', $data);
		$this->assertArrayHasKey('processwire', $data['mcp']);
		$this->assertSame('local', $data['mcp']['processwire']['type']);
	}

	public function test_mcp_writer_installs_and_removes_using_project_root(): void
	{
		$agent = new Antigravity();
		$writer = new McpWriter($agent, $this->tempDir);

		$writer->write('processwire');
		$targetFile = $this->tempDir . '/.agents/mcp_config.json';

		$this->assertFileExists($targetFile);
		$data = json_decode((string) file_get_contents($targetFile), true);
		$this->assertArrayHasKey('mcpServers', $data);
		$this->assertArrayHasKey('processwire', $data['mcpServers']);

		$writer->remove('processwire');
		$updated = json_decode((string) file_get_contents($targetFile), true);
		$this->assertArrayNotHasKey('processwire', $updated['mcpServers']);
	}

	public function test_toml_mcp_install_and_uninstall(): void
	{
		$codex = new Codex();
		$codex->installMcp('processwire', 'php', ['vendor/bin/wire', 'boost:mcp'], ['APP_ENV' => 'testing'], $this->tempDir);

		$targetFile = $this->tempDir . '/.codex/config.toml';
		$this->assertFileExists($targetFile);
		$content = (string) file_get_contents($targetFile);

		$this->assertStringContainsString('[mcp_servers.processwire]', $content);
		$this->assertStringContainsString('command = "php"', $content);
		$this->assertStringContainsString('[mcp_servers.processwire.env]', $content);
		$this->assertStringContainsString('APP_ENV = "testing"', $content);

		$codex->uninstallMcp('processwire', $this->tempDir);
		$updatedContent = (string) file_get_contents($targetFile);
		$this->assertStringNotContainsString('[mcp_servers.processwire]', $updatedContent);
	}
}

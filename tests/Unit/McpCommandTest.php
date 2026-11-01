<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Tests\Unit;

use Totoglu\Console\Boost\BoostManager;
use Totoglu\Console\Boost\Mcp\McpServer;
use Totoglu\Console\Boost\Tests\TestCase;

final class McpCommandTest extends TestCase
{
	public function test_initialize_returns_protocol_and_version(): void
	{
		$server = new McpServer();
		$response = $server->handleRequest([
			'jsonrpc' => '2.0',
			'id' => 1,
			'method' => 'initialize',
		]);

		$this->assertIsArray($response);
		$this->assertSame('2.0', $response['jsonrpc']);
		$this->assertSame(1, $response['id']);
		$this->assertSame('2024-11-05', $response['result']['protocolVersion']);
		$this->assertSame('processwire-boost', $response['result']['serverInfo']['name']);
		$this->assertSame(BoostManager::VERSION, $response['result']['serverInfo']['version']);
	}

	public function test_ping_returns_empty_object_result(): void
	{
		$server = new McpServer();
		$response = $server->handleRequest([
			'jsonrpc' => '2.0',
			'id' => 42,
			'method' => 'ping',
		]);

		$this->assertIsArray($response);
		$this->assertSame(42, $response['id']);
		$this->assertEquals((object) [], $response['result']);
	}

	public function test_id_zero_is_preserved_for_requests_and_errors(): void
	{
		$server = new McpServer();

		$pingResponse = $server->handleRequest([
			'jsonrpc' => '2.0',
			'id' => 0,
			'method' => 'ping',
		]);
		$this->assertIsArray($pingResponse);
		$this->assertSame(0, $pingResponse['id']);

		$errorResponse = $server->handleRequest([
			'jsonrpc' => '2.0',
			'id' => 0,
			'method' => 'unknown/method',
		]);
		$this->assertIsArray($errorResponse);
		$this->assertSame(0, $errorResponse['id']);
		$this->assertSame(-32601, $errorResponse['error']['code']);
	}

	public function test_notifications_return_null(): void
	{
		$server = new McpServer();

		$response = $server->handleRequest([
			'jsonrpc' => '2.0',
			'method' => 'notifications/initialized',
		]);

		$this->assertNull($response);
	}

	public function test_tools_list_returns_tools_with_valid_input_schemas_and_no_duplicate_aliases(): void
	{
		$server = new McpServer();
		$response = $server->handleRequest([
			'jsonrpc' => '2.0',
			'id' => 2,
			'method' => 'tools/list',
		]);

		$this->assertIsArray($response);
		$tools = $response['result']['tools'] ?? [];
		$this->assertCount(28, $tools);

		$toolsByName = [];
		foreach ($tools as $tool) {
			$this->assertArrayHasKey('name', $tool);
			$this->assertArrayHasKey('description', $tool);
			$this->assertArrayHasKey('inputSchema', $tool);
			$this->assertSame('object', $tool['inputSchema']['type']);
			$this->assertArrayHasKey('properties', $tool['inputSchema']);
			$toolsByName[$tool['name']] = $tool;
		}

		// Duplicate aliases must not exist
		$this->assertArrayNotHasKey('pw_module_enable', $toolsByName);
		$this->assertArrayNotHasKey('pw_module_disable', $toolsByName);

		// Verify specific tool parameter schemas
		$this->assertArrayHasKey('selector', (array) $toolsByName['pw_query']['inputSchema']['properties']);
		$this->assertArrayHasKey('limit', (array) $toolsByName['pw_query']['inputSchema']['properties']);

		$this->assertSame(['code'], $toolsByName['pw_execute']['inputSchema']['required']);
		$this->assertSame(['name', 'type'], $toolsByName['pw_schema_field_create']['inputSchema']['required']);
		$this->assertSame(['role', 'permission'], $toolsByName['pw_access_role_grant']['inputSchema']['required']);
		$this->assertSame(['file'], $toolsByName['pw_system_restore']['inputSchema']['required']);
	}

	public function test_pw_schema_read_delegates_to_boost_manager_schema_map(): void
	{
		$server = new McpServer();
		$response = $server->handleRequest([
			'jsonrpc' => '2.0',
			'id' => 3,
			'method' => 'tools/call',
			'params' => [
				'name' => 'pw_schema_read',
				'arguments' => [],
			],
		]);

		$this->assertIsArray($response);
		$this->assertSame(3, $response['id']);
		$this->assertArrayNotHasKey('isError', $response['result']);
		$text = $response['result']['content'][0]['text'] ?? '';
		$decoded = json_decode($text, true);
		$this->assertIsArray($decoded);
		$this->assertArrayHasKey('templates', $decoded);
		$this->assertArrayHasKey('fields', $decoded);
		$this->assertArrayHasKey('modules', $decoded);
		$this->assertArrayHasKey('roles', $decoded);
		$this->assertArrayHasKey('permissions', $decoded);
	}
}

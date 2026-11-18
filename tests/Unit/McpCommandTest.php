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

		$pingNotification = $server->handleRequest([
			'jsonrpc' => '2.0',
			'method' => 'ping',
		]);

		$this->assertNull($pingNotification);
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

	public function test_security_guards_and_jsonrpc_request_validation(): void
	{
		$server = new McpServer();

		// Invalid JSON-RPC version, empty object, or non-scalar id returns -32600
		$invalidReq = $server->handleRequest(['id' => 9, 'method' => '']);
		$this->assertIsArray($invalidReq);
		$this->assertSame(-32600, $invalidReq['error']['code']);

		$emptyReq = $server->handleRequest([]);
		$this->assertIsArray($emptyReq);
		$this->assertSame(-32600, $emptyReq['error']['code']);

		$badIdReq = $server->handleRequest(['jsonrpc' => '2.0', 'id' => ['nested'], 'method' => 'ping']);
		$this->assertIsArray($badIdReq);
		$this->assertSame(-32600, $badIdReq['error']['code']);

		$infIdReq = $server->handleRequest(['jsonrpc' => '2.0', 'id' => INF, 'method' => 'ping']);
		$this->assertIsArray($infIdReq);
		$this->assertSame(-32600, $infIdReq['error']['code']);

		$badParamsReq = $server->handleRequest(['jsonrpc' => '2.0', 'id' => 12, 'method' => 'tools/call', 'params' => ['positional']]);
		$this->assertIsArray($badParamsReq);
		$this->assertSame(-32602, $badParamsReq['error']['code']);

		// Built-in system permission deletion is blocked before touching DB
		$delSysPerm = $server->handleRequest([
			'jsonrpc' => '2.0',
			'id' => 10,
			'method' => 'tools/call',
			'params' => [
				'name' => 'pw_permission_delete',
				'arguments' => ['name' => 'page-view'],
			],
		]);
		$this->assertIsArray($delSysPerm);
		$this->assertTrue($delSysPerm['result']['isError'] ?? false);
		$this->assertStringContainsString('built-in ProcessWire permission', $delSysPerm['result']['content'][0]['text']);

		// Non-scalar argument types return clean tool error without PHP warnings
		$badArgType = $server->handleRequest([
			'jsonrpc' => '2.0',
			'id' => 13,
			'method' => 'tools/call',
			'params' => [
				'name' => 'pw_permission_delete',
				'arguments' => ['name' => ['nested']],
			],
		]);
		$this->assertIsArray($badArgType);
		$this->assertTrue($badArgType['result']['isError'] ?? false);
		$this->assertStringContainsString("Argument 'name' must be a string", $badArgType['result']['content'][0]['text']);

		// Zero or negative backup purge days is blocked
		$purgeZero = $server->handleRequest([
			'jsonrpc' => '2.0',
			'id' => 11,
			'method' => 'tools/call',
			'params' => [
				'name' => 'pw_system_backup_purge',
				'arguments' => ['days' => 0],
			],
		]);
		$this->assertIsArray($purgeZero);
		$this->assertTrue($purgeZero['result']['isError'] ?? false);

		// Top-level selector limit detection & clamping ignores bracketed sub-selectors and parenthesized OR-groups
		$this->assertFalse(McpServer::selectorHasTopLevelLimit('template=product, items=[limit=5]'));
		$this->assertFalse(McpServer::selectorHasTopLevelLimit('(template=product, limit=5, id>0)'));
		$this->assertFalse(McpServer::selectorHasTopLevelLimit('template=product, limit=0'));
		$this->assertTrue(McpServer::selectorHasTopLevelLimit('template=product, limit=25'));
		$this->assertSame(
			'template=basic-page, limit=500',
			McpServer::enforceBoundedTopLevelLimit('template=basic-page, limit=5000', 100, 500)
		);
		$this->assertSame(
			'template=basic-page, items=[limit=5000], limit=100',
			McpServer::enforceBoundedTopLevelLimit('template=basic-page, items=[limit=5000], limit=0', 100, 500)
		);
		$this->assertSame(
			'(template=basic-page, limit=5, id>0), limit=100',
			McpServer::enforceBoundedTopLevelLimit('(template=basic-page, limit=5, id>0), limit=-5', 100, 500)
		);
		$this->assertSame(
			'template=basic-page, limit=10',
			McpServer::enforceBoundedTopLevelLimit('template=basic-page, limit=10, limit>=999999', 100, 500)
		);
		$this->assertSame(
			'id>0, limit=2',
			McpServer::enforceBoundedTopLevelLimit('id>0, limit=2, !limit=0, @limit=99999, foo@limit=99999', 100, 500)
		);
		$this->assertSame(
			'id>0, title={a, limit=9999}, limit=2',
			McpServer::enforceBoundedTopLevelLimit('id>0, title={a, limit=9999}, limit=2, !!limit=0, !@limit=0, limit&=0', 100, 500)
		);

		// Log name normalization lowercases, strips .txt, allows internal dots, and rejects traversal
		$this->assertSame('errors', McpServer::normalizeLogName('Errors.TXT'));
		$this->assertSame('my.module', McpServer::normalizeLogName('my.module.txt'));
		$this->assertSame('', McpServer::normalizeLogName('.hidden'));
		$this->assertSame('', McpServer::normalizeLogName('..'));
		$this->assertSame('', McpServer::normalizeLogName('../errors'));
		$this->assertSame('', McpServer::normalizeLogName('../errors.txt'));
		$this->assertSame('', McpServer::normalizeLogName('bad$name'));
	}
}

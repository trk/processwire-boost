<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Mcp;

use Totoglu\Console\Boost\BoostManager;
use Totoglu\Console\Support\FeatureDiscoverer;

final class McpServer
{
	/** @var list<string> */
	private const SYSTEM_PERMISSIONS = [
		'page-view',
		'page-edit',
		'page-add',
		'page-create',
		'page-delete',
		'page-move',
		'page-sort',
		'page-template',
		'page-lock',
		'page-publish',
		'page-lister',
		'profile-edit',
		'user-admin',
		'user-admin-all',
		'logs-view',
		'logs-edit',
	];

	/**
	 * @param array<string|int, mixed> $request
	 * @return ?array<string, mixed>
	 */
	public function handleRequest(array $request): ?array
	{
		if ($request === [] || array_is_list($request)) {
			return $this->invalidRequest();
		}

		$jsonrpc = $request['jsonrpc'] ?? null;
		$method = is_string($request['method'] ?? null) ? $request['method'] : '';
		$hasId = array_key_exists('id', $request);
		$id = $request['id'] ?? null;

		if ($hasId && $id !== null && !is_string($id) && !is_int($id) && !is_float($id)) {
			return $this->invalidRequest();
		}

		if ($jsonrpc !== '2.0' || $method === '') {
			return $this->buildError($hasId ? $id : null, -32600, 'Invalid Request');
		}

		if (
			array_key_exists('params', $request)
			&& $request['params'] !== null
			&& (!is_array($request['params']) || ($request['params'] !== [] && array_is_list($request['params'])))
		) {
			return $hasId ? $this->buildError($id, -32602, 'Invalid params') : null;
		}

		// JSON-RPC 2.0 §4.1: Notifications (requests without an "id" member) MUST NOT be replied to.
		if (!$hasId) {
			return null;
		}

		switch ($method) {
			case 'initialize':
				return $this->buildResponse($id, [
					'protocolVersion' => '2024-11-05',
					'capabilities' => ['tools' => (object) []],
					'serverInfo' => [
						'name' => 'processwire-boost',
						'version' => BoostManager::VERSION,
					],
				]);

			case 'ping':
				return $this->buildResponse($id, (object) []);

			case 'tools/list':
				return $this->buildResponse($id, ['tools' => $this->getToolsList()]);

			case 'tools/call':
				$params = is_array($request['params'] ?? null) ? $request['params'] : [];
				$toolName = is_string($params['name'] ?? null) ? trim($params['name']) : '';
				if ($toolName === '') {
					return $this->buildError($id, -32602, 'Invalid params: missing tool name');
				}
				if (
					array_key_exists('arguments', $params)
					&& $params['arguments'] !== null
					&& (!is_array($params['arguments']) || ($params['arguments'] !== [] && array_is_list($params['arguments'])))
				) {
					return $this->buildError($id, -32602, 'Invalid params: arguments must be an object');
				}
				$args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
				return $this->buildResponse($id, $this->callTool($toolName, $args));

			default:
				return $this->buildError($id, -32601, 'Method not found');
		}
	}

	/**
	 * @return list<array{name: string, description: string, inputSchema: array{type: string, properties: object|array<string, array<string, mixed>>, required?: list<string>}}>
	 */
	public function getToolsList(): array
	{
		return [
			[
				'name' => 'pw_query',
				'description' => 'Run a ProcessWire selector and return JSON.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'selector' => [
							'type' => 'string',
							'description' => 'ProcessWire selector string (default: id>0).',
						],
						'limit' => [
							'type' => 'integer',
							'description' => 'Maximum number of pages to return when limit is not in selector (default: 100).',
						],
					],
				],
			],
			[
				'name' => 'pw_execute',
				'description' => 'Execute PHP code via Tinker (guarded).',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'code' => [
							'type' => 'string',
							'description' => 'PHP code to evaluate in the ProcessWire context.',
						],
					],
					'required' => ['code'],
				],
			],
			[
				'name' => 'pw_schema_read',
				'description' => 'Read current site schema (templates/fields).',
				'inputSchema' => [
					'type' => 'object',
					'properties' => (object) [],
				],
			],
			[
				'name' => 'pw_schema_field_create',
				'description' => 'Create a new field.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'name' => [
							'type' => 'string',
							'description' => 'Machine name of the new field.',
						],
						'type' => [
							'type' => 'string',
							'description' => 'Fieldtype short name (e.g. Text, Textarea, Integer, Page).',
						],
					],
					'required' => ['name', 'type'],
				],
			],
			[
				'name' => 'pw_schema_template_create',
				'description' => 'Create a new template.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'name' => [
							'type' => 'string',
							'description' => 'Machine name of the new template.',
						],
					],
					'required' => ['name'],
				],
			],
			[
				'name' => 'pw_module_list',
				'description' => 'List installed modules.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => (object) [],
				],
			],
			[
				'name' => 'pw_module_info',
				'description' => 'Get detailed module info.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'name' => [
							'type' => 'string',
							'description' => 'Module class name.',
						],
					],
					'required' => ['name'],
				],
			],
			[
				'name' => 'pw_module_install',
				'description' => 'Install a module by class name.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'name' => [
							'type' => 'string',
							'description' => 'Module class name to install.',
						],
					],
					'required' => ['name'],
				],
			],
			[
				'name' => 'pw_module_uninstall',
				'description' => 'Uninstall a module by class name.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'name' => [
							'type' => 'string',
							'description' => 'Module class name to uninstall.',
						],
					],
					'required' => ['name'],
				],
			],
			[
				'name' => 'pw_module_refresh',
				'description' => 'Refresh modules.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => (object) [],
				],
			],
			[
				'name' => 'pw_module_upgrade',
				'description' => 'Attempt to upgrade a module.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'name' => [
							'type' => 'string',
							'description' => 'Module class name to upgrade.',
						],
					],
					'required' => ['name'],
				],
			],
			[
				'name' => 'pw_access_user_create',
				'description' => 'Create a new user.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'name' => [
							'type' => 'string',
							'description' => 'Username for the new user.',
						],
						'password' => [
							'type' => 'string',
							'description' => 'Password for the new user.',
						],
						'email' => [
							'type' => 'string',
							'description' => 'Optional email address.',
						],
						'roles' => [
							'type' => 'array',
							'items' => ['type' => 'string'],
							'description' => 'Optional list of role names to assign.',
						],
					],
					'required' => ['name', 'password'],
				],
			],
			[
				'name' => 'pw_access_user_update',
				'description' => 'Update a user (email/pass/roles).',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'id' => [
							'type' => 'integer',
							'description' => 'User ID to update (provide id or name).',
						],
						'name' => [
							'type' => 'string',
							'description' => 'Username to update (provide id or name).',
						],
						'email' => [
							'type' => 'string',
							'description' => 'Updated email address.',
						],
						'password' => [
							'type' => 'string',
							'description' => 'Updated password.',
						],
						'addRoles' => [
							'type' => 'array',
							'items' => ['type' => 'string'],
							'description' => 'Role names to add to the user.',
						],
						'removeRoles' => [
							'type' => 'array',
							'items' => ['type' => 'string'],
							'description' => 'Role names to remove from the user.',
						],
					],
				],
			],
			[
				'name' => 'pw_access_user_delete',
				'description' => 'Delete a user.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'id' => [
							'type' => 'integer',
							'description' => 'User ID to delete (provide id or name).',
						],
						'name' => [
							'type' => 'string',
							'description' => 'Username to delete (provide id or name).',
						],
					],
				],
			],
			[
				'name' => 'pw_access_user_list',
				'description' => 'List users.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'search' => [
							'type' => 'string',
							'description' => 'Optional substring filter for username.',
						],
						'role' => [
							'type' => 'string',
							'description' => 'Optional role name filter.',
						],
						'limit' => [
							'type' => 'integer',
							'description' => 'Maximum number of users to return (default: 50).',
						],
					],
				],
			],
			[
				'name' => 'pw_access_role_create',
				'description' => 'Create a new role.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'name' => [
							'type' => 'string',
							'description' => 'Role name to create.',
						],
					],
					'required' => ['name'],
				],
			],
			[
				'name' => 'pw_access_role_grant',
				'description' => 'Grant permission to a role.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'role' => [
							'type' => 'string',
							'description' => 'Role name.',
						],
						'permission' => [
							'type' => 'string',
							'description' => 'Permission name to grant.',
						],
					],
					'required' => ['role', 'permission'],
				],
			],
			[
				'name' => 'pw_access_role_revoke',
				'description' => 'Revoke permission from a role.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'role' => [
							'type' => 'string',
							'description' => 'Role name.',
						],
						'permission' => [
							'type' => 'string',
							'description' => 'Permission name to revoke.',
						],
					],
					'required' => ['role', 'permission'],
				],
			],
			[
				'name' => 'pw_permission_delete',
				'description' => 'Delete a custom permission.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'name' => [
							'type' => 'string',
							'description' => 'Permission name to delete.',
						],
					],
					'required' => ['name'],
				],
			],
			[
				'name' => 'pw_system_get_logs',
				'description' => 'Retrieve system logs.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'name' => [
							'type' => 'string',
							'description' => 'Log name without extension (default: errors).',
						],
						'limit' => [
							'type' => 'integer',
							'description' => 'Maximum number of log entries to return (default: 10).',
						],
					],
				],
			],
			[
				'name' => 'pw_system_cache_clear',
				'description' => 'Clear system caches.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => (object) [],
				],
			],
			[
				'name' => 'pw_system_backup',
				'description' => 'Create a database backup.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => (object) [],
				],
			],
			[
				'name' => 'pw_system_backup_list',
				'description' => 'List database backups.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => (object) [],
				],
			],
			[
				'name' => 'pw_system_backup_purge',
				'description' => 'Purge old database backups.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'days' => [
							'type' => 'integer',
							'description' => 'Delete backups older than this number of days (default: 30).',
						],
					],
				],
			],
			[
				'name' => 'pw_system_cache_wire_clear',
				'description' => 'Clear WireCache by key/pattern.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'key' => [
							'type' => 'string',
							'description' => 'Exact cache key to delete.',
						],
						'pattern' => [
							'type' => 'string',
							'description' => 'SQL LIKE pattern for cache names to delete.',
						],
					],
				],
			],
			[
				'name' => 'pw_system_logs_clear',
				'description' => 'Clear a log file.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'name' => [
							'type' => 'string',
							'description' => 'Log name without extension (default: errors).',
						],
					],
				],
			],
			[
				'name' => 'pw_system_restore',
				'description' => 'Restore database from allowed backups directory (guarded).',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'file' => [
							'type' => 'string',
							'description' => 'Path to the .sql backup file inside site/assets/backups/database/.',
						],
					],
					'required' => ['file'],
				],
			],
			[
				'name' => 'pw_system_logs_tail_last',
				'description' => 'Return last N lines from a log file.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'name' => [
							'type' => 'string',
							'description' => 'Log name without extension (default: errors).',
						],
						'lines' => [
							'type' => 'integer',
							'description' => 'Number of trailing lines to read (default: 200).',
						],
					],
				],
			],
		];
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	private function callTool(string $name, array $args): array
	{
		try {
			return match (true) {
				in_array($name, ['pw_query', 'pw_execute'], true),
				str_starts_with($name, 'pw_schema_') => $this->callSchemaTool($name, $args),
				str_starts_with($name, 'pw_module_') => $this->callModuleTool($name, $args),
				str_starts_with($name, 'pw_access_'),
				$name === 'pw_permission_delete' => $this->callAccessTool($name, $args),
				str_starts_with($name, 'pw_system_') => $this->callSystemTool($name, $args),
				default => $this->errorResult("Unknown MCP tool: {$name}"),
			};
		} catch (\Throwable $e) {
			return $this->errorResult('Error: ' . $e->getMessage());
		}
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	private function callSchemaTool(string $name, array $args): array
	{
		switch ($name) {
			case 'pw_query':
				$selector = trim((string) ($args['selector'] ?? 'id>0'));
				if ($selector === '') {
					$selector = 'id>0';
				}
				$defaultLimit = max(1, min(500, (int) ($args['limit'] ?? 100)));
				$selector = self::enforceBoundedTopLevelLimit($selector, $defaultLimit, 500);
				$pages = \ProcessWire\wire('pages')->find($selector);
				$data = [];
				foreach ($pages as $p) {
					$data[] = [
						'id' => $p->id,
						'name' => $p->name,
						'path' => $p->path,
						'template' => $p->template->name,
					];
				}
				return $this->jsonResult($data);

			case 'pw_execute':
				if (!self::isEnvEnabled('PW_MCP_ALLOW_EXECUTE')) {
					return $this->errorResult('pw_execute is disabled. Set PW_MCP_ALLOW_EXECUTE=1 to enable.');
				}
				$code = (string) ($args['code'] ?? '');
				if ($code === '') {
					return $this->errorResult("Missing 'code' argument.");
				}
				ob_start();
				$result = eval($code);
				$out = trim((string) ob_get_clean());
				return $this->jsonResult(['result' => $result, 'output' => $out]);

			case 'pw_schema_read':
				return $this->jsonResult((new BoostManager())->buildSchemaMap());

			case 'pw_schema_field_create':
				$type = trim((string) ($args['type'] ?? ''));
				$rawName = trim((string) ($args['name'] ?? ''));
				if ($type === '' || $rawName === '') {
					return $this->errorResult('Required args: type, name');
				}
				$sanitizer = \ProcessWire\wire('sanitizer');
				$nameArg = (string) $sanitizer->fieldName($rawName);
				if ($nameArg === '' || $nameArg !== $rawName) {
					return $this->errorResult("Invalid field machine name '{$rawName}'.");
				}
				$fieldsApi = \ProcessWire\wire('fields');
				if ($fieldsApi->get($nameArg)?->id) {
					return $this->errorResult("Field '{$nameArg}' already exists.");
				}
				$pwType = str_starts_with($type, 'Fieldtype') ? $type : 'Fieldtype' . ucfirst($type);
				if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $pwType) !== 1) {
					return $this->errorResult("Invalid Fieldtype class name '{$pwType}'.");
				}
				$fieldtypeModule = \ProcessWire\wire('modules')->get($pwType);
				if (!($fieldtypeModule instanceof \ProcessWire\Fieldtype)) {
					return $this->errorResult("Field type '{$pwType}' not installed.");
				}
				$f = new \ProcessWire\Field();
				$f->type = $fieldtypeModule;
				$f->name = $nameArg;
				$f->label = ucfirst(str_replace('_', ' ', $nameArg));
				$fieldsApi->save($f);
				return $this->jsonResult(['ok' => true, 'field' => $nameArg, 'type' => $pwType]);

			case 'pw_schema_template_create':
				$rawTplName = trim((string) ($args['name'] ?? ''));
				if ($rawTplName === '') {
					return $this->errorResult('Required arg: name');
				}
				$tplName = (string) \ProcessWire\wire('sanitizer')->templateName($rawTplName);
				if ($tplName === '' || $tplName !== $rawTplName) {
					return $this->errorResult("Invalid template machine name '{$rawTplName}'.");
				}
				$templatesApi = \ProcessWire\wire('templates');
				if ($templatesApi->get($tplName)?->id) {
					return $this->errorResult("Template '{$tplName}' already exists.");
				}
				$fieldgroups = \ProcessWire\wire('fieldgroups');
				$fg = $fieldgroups->get($tplName);
				if (!$fg || !$fg->id) {
					$fg = new \ProcessWire\Fieldgroup();
					$fg->name = $tplName;
					$titleField = \ProcessWire\wire('fields')->get('title');
					if ($titleField && $titleField->id) {
						$fg->add($titleField);
					}
					$fieldgroups->save($fg);
				}
				$t = new \ProcessWire\Template();
				$t->name = $tplName;
				$t->fieldgroup = $fg;
				$templatesApi->save($t);
				return $this->jsonResult(['ok' => true, 'template' => $tplName]);

			default:
				return $this->errorResult("Unknown MCP tool: {$name}");
		}
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	private function callModuleTool(string $name, array $args): array
	{
		switch ($name) {
			case 'pw_module_list':
				$modules = \ProcessWire\wire('modules');
				$installed = $modules->getAll();
				$list = [];
				foreach ($installed as $modName => $_m) {
					$modNameStr = (string) $modName;
					$info = $modules->getModuleInfo($modNameStr);
					$modFile = str_replace('\\', '/', (string) $modules->getModuleFile($modNameStr));
					$list[] = [
						'name' => $modNameStr,
						'title' => $info['title'] ?? '',
						'version' => $modules->formatVersion($info['version'] ?? 0),
						'summary' => $info['summary'] ?? '',
						'core' => str_contains($modFile, '/wire/modules/'),
					];
				}
				return $this->jsonResult($list);

			case 'pw_module_info':
				$modName = trim((string) ($args['name'] ?? ''));
				if ($modName === '') {
					return $this->errorResult('Required arg: name');
				}
				if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $modName) !== 1) {
					return $this->errorResult("Invalid module class name '{$modName}'.");
				}
				$modules = \ProcessWire\wire('modules');
				$info = $modules->getModuleInfo($modName);
				if (!$info) {
					return $this->errorResult("Module not found: {$modName}");
				}
				$payload = $info;
				$payload['version'] = $modules->formatVersion($info['version'] ?? 0);
				return $this->jsonResult($payload);

			case 'pw_module_install':
				$modName = trim((string) ($args['name'] ?? ''));
				if ($modName === '') {
					return $this->errorResult('Required arg: name');
				}
				if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $modName) !== 1) {
					return $this->errorResult("Invalid module class name '{$modName}'.");
				}
				$modules = \ProcessWire\wire('modules');
				if ($modules->isInstalled($modName)) {
					return $this->jsonResult(['ok' => true, 'installed' => true, 'note' => 'already']);
				}
				if (!$modules->getModuleFile($modName)) {
					return $this->errorResult("Module not found: {$modName}");
				}
				$installedObj = $modules->install($modName);
				if ($installedObj === null) {
					return $this->errorResult("Failed to install module '{$modName}'.");
				}
				return $this->jsonResult(['ok' => true, 'installed' => true]);

			case 'pw_module_uninstall':
				$modName = trim((string) ($args['name'] ?? ''));
				if ($modName === '') {
					return $this->errorResult('Required arg: name');
				}
				if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $modName) !== 1) {
					return $this->errorResult("Invalid module class name '{$modName}'.");
				}
				$modules = \ProcessWire\wire('modules');
				if (!$modules->isInstalled($modName)) {
					return $this->jsonResult(['ok' => true, 'uninstalled' => true, 'note' => 'already']);
				}
				$modFile = str_replace('\\', '/', (string) $modules->getModuleFile($modName));
				if (str_contains($modFile, '/wire/modules/')) {
					return $this->errorResult("Refusing to uninstall core ProcessWire module '{$modName}' via MCP.");
				}
				$info = $modules->getModuleInfoVerbose($modName);
				if (!empty($info['permanent'])) {
					return $this->errorResult("Module '{$modName}' is marked permanent and cannot be uninstalled.");
				}
				$reason = $modules->isUninstallable($modName, true);
				if ($reason !== true) {
					$detail = is_string($reason) && $reason !== '' ? " ({$reason})" : '';
					return $this->errorResult("Module '{$modName}' cannot be uninstalled{$detail}.");
				}
				$uninstalled = $modules->uninstall($modName);
				if (!$uninstalled) {
					return $this->errorResult("Failed to uninstall module '{$modName}'.");
				}
				return $this->jsonResult(['ok' => true, 'uninstalled' => true]);

			case 'pw_module_refresh':
				\ProcessWire\wire('modules')->refresh();
				return $this->textResult('Modules refreshed.');

			case 'pw_module_upgrade':
				$modName = trim((string) ($args['name'] ?? ''));
				if ($modName === '') {
					return $this->errorResult('Required arg: name');
				}
				if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $modName) !== 1) {
					return $this->errorResult("Invalid module class name '{$modName}'.");
				}
				$modules = \ProcessWire\wire('modules');
				$modules->refresh();
				if (!$modules->isInstalled($modName)) {
					return $this->errorResult("Module '{$modName}' is not installed.");
				}
				$modules->get($modName);
				return $this->jsonResult(['ok' => true, 'upgraded' => true]);

			default:
				return $this->errorResult("Unknown MCP tool: {$name}");
		}
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	private function callAccessTool(string $name, array $args): array
	{
		switch ($name) {
			case 'pw_access_user_create':
				$rawUname = trim((string) ($args['name'] ?? ''));
				$rawEmail = trim((string) ($args['email'] ?? ''));
				$pass = (string) ($args['password'] ?? '');
				$rolesIn = $args['roles'] ?? [];
				if ($rawUname === '' || $pass === '') {
					return $this->errorResult('Required args: name, password (email optional)');
				}
				$sanitizer = \ProcessWire\wire('sanitizer');
				$uname = (string) $sanitizer->pageName($rawUname);
				if ($uname === '' || $uname !== $rawUname || ctype_digit($uname)) {
					return $this->errorResult("Invalid user name '{$rawUname}'.");
				}
				$email = '';
				if ($rawEmail !== '') {
					$email = (string) $sanitizer->email($rawEmail);
					if ($email === '') {
						return $this->errorResult("Invalid email address '{$rawEmail}'.");
					}
				}
				$resolvedRolesToAssign = [];
				if (is_array($rolesIn)) {
					$superRoleId = (int) (\ProcessWire\wire('config')->superUserRolePageID ?? 38);
					foreach ($rolesIn as $r) {
						$roleCandidate = trim((string) $r);
						if ($roleCandidate === '') {
							continue;
						}
						if (strtolower($roleCandidate) === 'superuser' || (ctype_digit($roleCandidate) && (int) $roleCandidate === $superRoleId)) {
							return $this->errorResult('Refusing to assign superuser role via MCP.');
						}
						$role = $this->findNamedSystemItem('roles', $roleCandidate);
						if ($role instanceof \ProcessWire\Role && $role->id) {
							if ((string) $role->name === 'superuser' || (int) $role->id === $superRoleId) {
								return $this->errorResult('Refusing to assign superuser role via MCP.');
							}
							$resolvedRolesToAssign[] = $role;
						}
					}
				}
				$users = \ProcessWire\wire('users');
				if ($this->findNamedSystemItem('users', $uname) !== null) {
					return $this->errorResult("User '{$uname}' already exists.");
				}
				$u = $users->add($uname);
				$u->of(false);
				if ($email !== '') {
					$u->email = $email;
				}
				$u->pass = $pass;
				foreach ($resolvedRolesToAssign as $role) {
					$u->addRole($role);
				}
				$users->save($u);
				return $this->jsonResult(['ok' => true, 'userId' => $u->id]);

			case 'pw_access_role_create':
				$rawRoleName = trim((string) ($args['name'] ?? ''));
				if ($rawRoleName === '') {
					return $this->errorResult('Required arg: name');
				}
				$rname = (string) \ProcessWire\wire('sanitizer')->pageName($rawRoleName);
				if ($rname === '' || $rname !== $rawRoleName || ctype_digit($rname)) {
					return $this->errorResult("Invalid role name '{$rawRoleName}'.");
				}
				$roles = \ProcessWire\wire('roles');
				if ($this->findNamedSystemItem('roles', $rname) !== null) {
					return $this->errorResult("Role '{$rname}' already exists.");
				}
				$role = $roles->add($rname);
				return $this->jsonResult(['ok' => true, 'roleId' => $role->id]);

			case 'pw_access_user_update':
				$id = $args['id'] ?? null;
				$uname = trim((string) ($args['name'] ?? ''));
				$rawEmail = trim((string) ($args['email'] ?? ''));
				$pass = (string) ($args['password'] ?? '');
				$addRoles = $args['addRoles'] ?? [];
				$removeRoles = $args['removeRoles'] ?? [];
				if (($id === null || $id === '') && $uname === '') {
					return $this->errorResult("Provide 'id' or 'name'.");
				}
				$sanitizer = \ProcessWire\wire('sanitizer');
				$users = \ProcessWire\wire('users');
				if ($id !== null && $id !== '') {
					$idStr = trim((string) $id);
					if (!ctype_digit($idStr)) {
						return $this->errorResult("Invalid user ID '{$idStr}'.");
					}
					$user = $users->get((int) $idStr);
				} else {
					$user = $this->findNamedSystemItem('users', $uname);
				}
				if (!($user instanceof \ProcessWire\User) || !$user->id) {
					return $this->errorResult('User not found.');
				}
				if ($user->isSuperuser()) {
					return $this->errorResult('Refusing to modify a superuser via MCP.');
				}
				if ($user->isGuest() || (string) $user->name === 'guest') {
					return $this->errorResult('Refusing to modify the built-in guest user via MCP.');
				}
				$superRoleId = (int) (\ProcessWire\wire('config')->superUserRolePageID ?? 38);
				$rolesToAdd = [];
				if (is_array($addRoles)) {
					foreach ($addRoles as $r) {
						$roleCandidate = trim((string) $r);
						if ($roleCandidate === '') {
							continue;
						}
						if (strtolower($roleCandidate) === 'superuser' || (ctype_digit($roleCandidate) && (int) $roleCandidate === $superRoleId)) {
							return $this->errorResult('Refusing to grant superuser role via MCP.');
						}
						$role = $this->findNamedSystemItem('roles', $roleCandidate);
						if ($role instanceof \ProcessWire\Role && $role->id) {
							if ((string) $role->name === 'superuser' || (int) $role->id === $superRoleId) {
								return $this->errorResult('Refusing to grant superuser role via MCP.');
							}
							$rolesToAdd[] = $role;
						}
					}
				}
				$user->of(false);
				if ($rawEmail !== '') {
					$validEmail = (string) $sanitizer->email($rawEmail);
					if ($validEmail === '') {
						return $this->errorResult("Invalid email address '{$rawEmail}'.");
					}
					$user->email = $validEmail;
				}
				if ($pass !== '') {
					$user->pass = $pass;
				}
				foreach ($rolesToAdd as $role) {
					if (!$user->hasRole($role)) {
						$user->addRole($role);
					}
				}
				if (is_array($removeRoles)) {
					foreach ($removeRoles as $r) {
						$role = $this->findNamedSystemItem('roles', (string) $r);
						if (!($role instanceof \ProcessWire\Role) || !$role->id) {
							continue;
						}
						if ((string) $role->name === 'guest' || (string) $role->name === 'superuser') {
							continue;
						}
						if ($user->hasRole($role)) {
							$user->removeRole($role);
						}
					}
				}
				$users->save($user);
				return $this->jsonResult(['ok' => true, 'userId' => $user->id]);

			case 'pw_access_user_delete':
				$id = $args['id'] ?? null;
				$uname = trim((string) ($args['name'] ?? ''));
				if (($id === null || $id === '') && $uname === '') {
					return $this->errorResult("Provide 'id' or 'name'.");
				}
				$users = \ProcessWire\wire('users');
				if ($id !== null && $id !== '') {
					$idStr = trim((string) $id);
					if (!ctype_digit($idStr)) {
						return $this->errorResult("Invalid user ID '{$idStr}'.");
					}
					$user = $users->get((int) $idStr);
				} else {
					$user = $this->findNamedSystemItem('users', $uname);
				}
				if (!($user instanceof \ProcessWire\User) || !$user->id) {
					return $this->errorResult('User not found.');
				}
				if ($user->isSuperuser()) {
					return $this->errorResult('Refusing to delete a superuser.');
				}
				if ($user->isGuest() || (string) $user->name === 'guest') {
					return $this->errorResult('Refusing to delete the built-in guest user.');
				}
				$users->delete($user);
				return $this->jsonResult(['ok' => true, 'deleted' => true]);

			case 'pw_access_user_list':
				$search = (string) ($args['search'] ?? '');
				$roleFilter = (string) ($args['role'] ?? '');
				$limit = max(1, min(500, (int) ($args['limit'] ?? 50)));
				$sanitizer = \ProcessWire\wire('sanitizer');
				$selector = [];
				if ($search !== '') {
					$search = $sanitizer->selectorValue($search);
					$selector[] = "name%={$search}";
				}
				if ($roleFilter !== '') {
					$roleFilter = $sanitizer->selectorValue($roleFilter);
					$selector[] = "roles={$roleFilter}";
				}
				$selector[] = "limit={$limit}";
				$users = \ProcessWire\wire('users')->find(implode(', ', $selector));
				$list = [];
				foreach ($users as $u) {
					$roles = [];
					foreach ($u->roles as $r) {
						$roles[] = $r->name;
					}
					$list[] = [
						'id' => $u->id,
						'name' => $u->name,
						'email' => (string) $u->email,
						'roles' => $roles,
					];
				}
				return $this->jsonResult($list);

			case 'pw_access_role_grant':
				return $this->mutateRolePermission($args, true);

			case 'pw_access_role_revoke':
				return $this->mutateRolePermission($args, false);

			case 'pw_permission_delete':
				$permName = trim((string) ($args['name'] ?? ''));
				if ($permName === '') {
					return $this->errorResult('Arg required: name');
				}
				if (in_array($permName, self::SYSTEM_PERMISSIONS, true)) {
					return $this->errorResult("Refusing to delete built-in ProcessWire permission '{$permName}'.");
				}
				$perm = $this->findNamedSystemItem('permissions', $permName);
				if (!($perm instanceof \ProcessWire\Permission) || !$perm->id) {
					return $this->errorResult('Permission not found.');
				}
				if (in_array((string) $perm->name, self::SYSTEM_PERMISSIONS, true)) {
					return $this->errorResult("Refusing to delete built-in ProcessWire permission '{$perm->name}'.");
				}
				\ProcessWire\wire('permissions')->delete($perm);
				return $this->jsonResult(['ok' => true, 'deleted' => true]);

			default:
				return $this->errorResult("Unknown MCP tool: {$name}");
		}
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	private function callSystemTool(string $name, array $args): array
	{
		switch ($name) {
			case 'pw_system_logs_clear':
				$logName = self::normalizeLogName((string) ($args['name'] ?? 'errors'));
				if ($logName === '') {
					return $this->errorResult('Invalid log name.');
				}
				$logsDir = (string) \ProcessWire\wire('config')->paths->logs;
				$path = $logsDir . $logName . '.txt';
				$realLogsDir = realpath($logsDir);
				$realPath = realpath($path);
				if (
					is_link($path)
					|| !is_file($path)
					|| $realLogsDir === false
					|| $realPath === false
					|| !str_starts_with($realPath, rtrim($realLogsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
				) {
					return $this->errorResult('Log not found.');
				}
				@file_put_contents($realPath, '');
				return $this->jsonResult(['ok' => true, 'cleared' => $logName]);

			case 'pw_system_backup_list':
				$dir = (string) \ProcessWire\wire('config')->paths->assets . 'backups/database/';
				if (!is_dir($dir)) {
					return $this->jsonResult([]);
				}
				$files = array_values(array_filter(
					scandir($dir) ?: [],
					static fn(string $f): bool => $f !== '.' && $f !== '..' && str_ends_with(strtolower($f), '.sql') && !is_link($dir . $f) && is_file($dir . $f)
				));
				usort($files, static fn(string $a, string $b): int => filemtime($dir . $b) <=> filemtime($dir . $a));
				return $this->jsonResult($files);

			case 'pw_system_backup_purge':
				$days = (int) ($args['days'] ?? 30);
				if ($days < 1) {
					return $this->errorResult('Argument "days" must be at least 1.');
				}
				$threshold = time() - ($days * 86400);
				$dir = (string) \ProcessWire\wire('config')->paths->assets . 'backups/database/';
				if (!is_dir($dir)) {
					return $this->jsonResult(['ok' => true, 'deleted' => 0]);
				}
				$deleted = 0;
				foreach (scandir($dir) ?: [] as $f) {
					if ($f === '.' || $f === '..' || !str_ends_with(strtolower($f), '.sql')) {
						continue;
					}
					$path = $dir . $f;
					if (!is_link($path) && is_file($path) && filemtime($path) < $threshold) {
						if (@unlink($path)) {
							$deleted++;
						}
					}
				}
				return $this->jsonResult(['ok' => true, 'deleted' => $deleted]);

			case 'pw_system_restore':
				if (!self::isEnvEnabled('PW_MCP_ALLOW_RESTORE')) {
					return $this->errorResult('pw_system_restore disabled. Set PW_MCP_ALLOW_RESTORE=1 to enable.');
				}
				$file = (string) ($args['file'] ?? '');
				if ($file === '') {
					return $this->errorResult('Required arg: file');
				}
				$assetsDir = (string) \ProcessWire\wire('config')->paths->assets . 'backups/database/';
				$realAssetsDir = realpath($assetsDir);
				$real = realpath($file);
				if (
					is_link($file)
					|| $real === false
					|| $realAssetsDir === false
					|| !str_starts_with($real, rtrim($realAssetsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
					|| !is_file($real)
					|| !is_readable($real)
				) {
					return $this->errorResult('File not permitted: must reside under backups/database and be readable.');
				}
				if (!str_ends_with(strtolower($real), '.sql')) {
					return $this->errorResult('Only .sql files are accepted.');
				}
				$size = filesize($real);
				if ($size === false || $size === 0) {
					return $this->errorResult('SQL file is empty.');
				}
				$handle = @fopen($real, 'rb');
				$headSample = $handle !== false ? (string) fread($handle, 4096) : '';
				if ($handle !== false) {
					fclose($handle);
				}
				if (trim($headSample) === '' && $size <= 4096) {
					return $this->errorResult('SQL file is empty.');
				}
				$db = \ProcessWire\wire('database');
				try {
					$backups = $db->backups();
					if (!$backups) {
						return $this->errorResult('Database restore utility is unavailable.');
					}
					$restored = $backups->restore(basename($real));
					if (!$restored) {
						$errors = is_object($backups) && method_exists($backups, 'errors')
							? implode('; ', $backups->errors())
							: 'Database restore returned false.';
						return $this->errorResult("Restore failed: {$errors}");
					}
				} catch (\Throwable $e) {
					return $this->errorResult('Restore failed: ' . $e->getMessage());
				}
				return $this->jsonResult(['ok' => true, 'restored' => basename($real)]);

			case 'pw_system_cache_wire_clear':
				$rawKey = $args['key'] ?? null;
				$rawPattern = $args['pattern'] ?? null;
				$key = ($rawKey !== null && $rawKey !== '') ? (string) $rawKey : '';
				$pattern = ($rawPattern !== null && $rawPattern !== '') ? (string) $rawPattern : '';
				if ($key === '' && $pattern === '') {
					return $this->errorResult("Provide 'key' or 'pattern'.");
				}
				if ($key !== '') {
					$deleted = \ProcessWire\wire('cache')->delete($key) ? 1 : 0;
					return $this->jsonResult(['ok' => true, 'deleted' => $deleted]);
				}
				$db = \ProcessWire\wire('database');
				$table = '`' . $db->escapeTable('caches') . '`';
				$stmt = $db->prepare("DELETE FROM {$table} WHERE name LIKE :p");
				$stmt->bindValue(':p', $pattern);
				$stmt->execute();
				return $this->jsonResult(['ok' => true, 'deleted' => $stmt->rowCount()]);

			case 'pw_system_get_logs':
				$logName = self::normalizeLogName((string) ($args['name'] ?? 'errors'));
				if ($logName === '') {
					return $this->errorResult('Invalid log name.');
				}
				$limit = max(1, min(1000, (int) ($args['limit'] ?? 10)));
				$entries = \ProcessWire\wire('log')->getEntries($logName, ['limit' => $limit]);
				$text = '';
				foreach ($entries as $e) {
					if (is_array($e)) {
						$date = (string) ($e['date'] ?? '');
						$lineText = (string) ($e['text'] ?? '');
						$text .= "[{$date}] {$lineText}\n";
					}
				}
				return $this->textResult($text !== '' ? $text : 'No entries found.');

			case 'pw_system_logs_tail_last':
				$logName = self::normalizeLogName((string) ($args['name'] ?? 'errors'));
				if ($logName === '') {
					return $this->errorResult('Invalid log name.');
				}
				$lines = max(1, min(5000, (int) ($args['lines'] ?? 200)));
				$logsDir = (string) \ProcessWire\wire('config')->paths->logs;
				$path = $logsDir . $logName . '.txt';
				$realLogsDir = realpath($logsDir);
				$realPath = realpath($path);
				if (
					is_link($path)
					|| !is_file($path)
					|| $realLogsDir === false
					|| $realPath === false
					|| !str_starts_with($realPath, rtrim($realLogsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
				) {
					return $this->errorResult('Log not found.');
				}
				$slice = FeatureDiscoverer::readLastLines($realPath, $lines);
				return $this->textResult(implode("\n", $slice));

			case 'pw_system_cache_clear':
				\ProcessWire\wire('modules')->refresh();
				return $this->textResult('Caches cleared and modules refreshed.');

			case 'pw_system_backup':
				$backup = \ProcessWire\wire('database')->backups();
				if (!$backup) {
					return $this->errorResult('Backup tool not available.');
				}
				$file = $backup->backup();
				if ($file) {
					return $this->jsonResult(['ok' => true, 'file' => $file]);
				}
				return $this->errorResult('Backup failed.');

			default:
				return $this->errorResult("Unknown MCP tool: {$name}");
		}
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	private function mutateRolePermission(array $args, bool $grant): array
	{
		$roleName = trim((string) ($args['role'] ?? ''));
		$permName = trim((string) ($args['permission'] ?? ''));
		if ($roleName === '' || $permName === '') {
			return $this->errorResult('Args required: role, permission');
		}
		if (strtolower($roleName) === 'superuser') {
			return $this->errorResult('Refusing to mutate the superuser role.');
		}
		$role = $this->findNamedSystemItem('roles', $roleName);
		$permission = $this->findNamedSystemItem('permissions', $permName);
		if (!($role instanceof \ProcessWire\Role) || !$role->id || !($permission instanceof \ProcessWire\Permission) || !$permission->id) {
			return $this->errorResult('Role or permission not found.');
		}
		$resolvedRoleName = (string) $role->name;
		$resolvedPermName = (string) $permission->name;
		if ($resolvedRoleName === 'superuser') {
			return $this->errorResult('Refusing to mutate the superuser role.');
		}
		if ($grant && $resolvedRoleName === 'guest' && $resolvedPermName !== 'page-view') {
			return $this->errorResult('Refusing to grant privileged permissions to the guest role via MCP.');
		}
		if (!$grant && $resolvedRoleName === 'guest' && $resolvedPermName === 'page-view') {
			return $this->errorResult("Refusing to revoke mandatory 'page-view' permission from the guest role via MCP.");
		}
		$roles = \ProcessWire\wire('roles');
		$role->of(false);
		if ($grant && !$role->hasPermission($permission)) {
			$role->addPermission($permission);
			$roles->save($role);
		} elseif (!$grant && $role->hasPermission($permission)) {
			$role->removePermission($permission);
			$roles->save($role);
		}
		$actionKey = $grant ? 'granted' : 'revoked';
		return $this->jsonResult(['ok' => true, $actionKey => true]);
	}

	/**
	 * Look up a ProcessWire system entity (users, roles, permissions) strictly by exact non-numeric pageName.
	 */
	private function findNamedSystemItem(string $apiVar, string $rawName): ?\ProcessWire\Page
	{
		$trimmed = trim($rawName);
		if ($trimmed === '' || ctype_digit($trimmed)) {
			return null;
		}
		$sanitizer = \ProcessWire\wire('sanitizer');
		$clean = (string) $sanitizer->pageName($trimmed, true);
		if ($clean === '' || $clean !== strtolower($trimmed)) {
			$exact = (string) $sanitizer->pageName($trimmed);
			if ($exact === '' || $exact !== $trimmed) {
				return null;
			}
			$clean = $exact;
		}
		$safeVal = $sanitizer->selectorValue($clean);
		$item = \ProcessWire\wire($apiVar)->get("name={$safeVal}");

		return ($item instanceof \ProcessWire\Page && $item->id) ? $item : null;
	}

	public static function isEnvEnabled(string $name): bool
	{
		return getenv($name) === '1'
			|| ($_ENV[$name] ?? null) === '1'
			|| ($_SERVER[$name] ?? null) === '1';
	}

	public static function selectorHasTopLevelLimit(string $selector): bool
	{
		foreach (self::splitTopLevelSelectorClauses($selector) as $clause) {
			if (preg_match('/^\s*limit\s*=\s*([1-9]\d*)\s*$/i', $clause) === 1) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Ensure a selector has a positive top-level `limit=N` clause and clamp any explicit top-level limit to $maxLimit.
	 */
	public static function enforceBoundedTopLevelLimit(string $selector, int $defaultLimit = 100, int $maxLimit = 500): string
	{
		$clauses = self::splitTopLevelSelectorClauses($selector);
		$foundPositiveLimit = false;
		$rebuilt = [];

		foreach ($clauses as $clause) {
			if (preg_match('/^(\s*limit\s*=\s*)(\d+)(\s*)$/i', $clause, $m) === 1) {
				$num = (int) $m[2];
				if ($num <= 0) {
					continue;
				}
				$foundPositiveLimit = true;
				$clamped = min($maxLimit, $num);
				$rebuilt[] = $m[1] . $clamped . $m[3];
				continue;
			}
			$rebuilt[] = $clause;
		}

		if (!$foundPositiveLimit) {
			$safeDefault = max(1, min($maxLimit, $defaultLimit));
			$rebuilt[] = ' limit=' . $safeDefault;
		}

		return ltrim(implode(',', $rebuilt));
	}

	/**
	 * @return list<string>
	 */
	private static function splitTopLevelSelectorClauses(string $selector): array
	{
		$clauses = [];
		$current = '';
		$depth = 0;
		$quote = '';
		$len = strlen($selector);

		for ($i = 0; $i < $len; $i++) {
			$ch = $selector[$i];
			if ($quote !== '') {
				$current .= $ch;
				if ($ch === '\\' && $i + 1 < $len) {
					$current .= $selector[++$i];
					continue;
				}
				if ($ch === $quote) {
					$quote = '';
				}
				continue;
			}

			if ($ch === '"' || $ch === "'") {
				$quote = $ch;
				$current .= $ch;
				continue;
			}

			if ($ch === '[') {
				$depth++;
				$current .= $ch;
				continue;
			}

			if ($ch === ']') {
				$depth = max(0, $depth - 1);
				$current .= $ch;
				continue;
			}

			if ($ch === ',' && $depth === 0) {
				$clauses[] = $current;
				$current = '';
				continue;
			}

			$current .= $ch;
		}

		if (trim($current) !== '') {
			$clauses[] = $current;
		}

		return $clauses;
	}

	public static function normalizeLogName(string $rawName): string
	{
		$trimmed = trim($rawName);
		if ($trimmed === '' || str_contains($trimmed, '/') || str_contains($trimmed, '\\') || str_contains($trimmed, '..')) {
			return '';
		}
		$base = (string) preg_replace('/\.txt$/i', '', $trimmed);
		if ($base === '' || $base === '.' || $base === '..') {
			return '';
		}
		if (preg_match('/^[A-Za-z0-9_-]+$/', $base) !== 1) {
			return '';
		}
		return $base;
	}

	/**
	 * @return array{content: list<array{type: string, text: string}>}
	 */
	private function textResult(string $text): array
	{
		return ['content' => [['type' => 'text', 'text' => $text]]];
	}

	/**
	 * @return array{content: list<array{type: string, text: string}>}
	 */
	private function jsonResult(mixed $data): array
	{
		$encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		return $this->textResult($encoded !== false ? $encoded : '{}');
	}

	/**
	 * @return array{isError: true, content: list<array{type: string, text: string}>}
	 */
	private function errorResult(string $message): array
	{
		return ['isError' => true, 'content' => [['type' => 'text', 'text' => $message]]];
	}

	/**
	 * @return array{jsonrpc: string, id: mixed, result: mixed}
	 */
	private function buildResponse(mixed $id, mixed $result): array
	{
		return [
			'jsonrpc' => '2.0',
			'id' => $id,
			'result' => $result,
		];
	}

	/**
	 * @return array{jsonrpc: string, id: mixed, error: array{code: int, message: string}}
	 */
	private function buildError(mixed $id, int $code, string $message): array
	{
		return [
			'jsonrpc' => '2.0',
			'id' => $id,
			'error' => [
				'code' => $code,
				'message' => $message,
			],
		];
	}

	/**
	 * @return array{jsonrpc: string, id: mixed, error: array{code: int, message: string}}
	 */
	public function parseError(): array
	{
		return $this->buildError(null, -32700, 'Parse error');
	}

	/**
	 * @return array{jsonrpc: string, id: mixed, error: array{code: int, message: string}}
	 */
	public function invalidRequest(): array
	{
		return $this->buildError(null, -32600, 'Invalid Request');
	}
}

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
		'page-hide',
		'page-publish',
		'page-edit-created',
		'page-edit-trash-created',
		'page-edit-images',
		'page-edit-recent',
		'page-clone',
		'page-clone-tree',
		'page-lister',
		'profile-edit',
		'user-admin',
		'user-admin-all',
		'logs-view',
		'logs-edit',
		'lang-edit',
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

		if ($hasId && $id !== null && !is_string($id) && !is_int($id) && !(is_float($id) && is_finite($id))) {
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
				'description' => 'Read current site schema (templates, fields, modules, roles, permissions). Supports optional section, template, field, and deep filters.',
				'inputSchema' => [
					'type' => 'object',
					'properties' => [
						'section' => [
							'type' => 'string',
							'enum' => ['templates', 'fields', 'modules', 'roles', 'permissions'],
							'description' => 'Optional section filter to return only one schema section.',
						],
						'template' => [
							'type' => 'string',
							'description' => 'Optional template name to return only that template and its attached field definitions.',
						],
						'field' => [
							'type' => 'string',
							'description' => 'Optional field name to return only that field schema (automatically expands nested blocks).',
						],
						'deep' => [
							'type' => 'boolean',
							'description' => 'Expand deeply nested recursive block trees inside compound fields (default: false).',
						],
					],
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
				$selector = trim(self::argString($args, 'selector', 'id>0'));
				if ($selector === '') {
					$selector = 'id>0';
				}
				$defaultLimit = max(1, min(500, self::argInt($args, 'limit', 100)));
				$selector = self::enforceBoundedTopLevelLimit($selector, $defaultLimit, 500);
				$pages = $this->requireWire('pages')->find($selector);
				$data = [];
				foreach ($pages as $p) {
					$data[] = [
						'id' => $p->id,
						'name' => $p->name,
						'path' => $p->path,
						'template' => (string) ($p->template->name ?? ''),
					];
				}
				return $this->jsonResult($data);

			case 'pw_execute':
				if (!self::isEnvEnabled('PW_MCP_ALLOW_EXECUTE')) {
					return $this->errorResult('pw_execute is disabled. Set PW_MCP_ALLOW_EXECUTE=1 to enable.');
				}
				$code = trim(self::argString($args, 'code'));
				$code = (string) preg_replace('/^\s*<\?(?:php)?\s*/i', '', $code);
				if (trim($code) === '') {
					return $this->errorResult("Missing 'code' argument.");
				}
				$this->requireWire('pages');
				$apiVars = self::collectProcessWireContext();
				$runner = static function (string $__pwExecSnippet, array $__pwApiVars): mixed {
					extract($__pwApiVars, EXTR_SKIP);
					unset($__pwApiVars);
					return eval($__pwExecSnippet);
				};
				ob_start();
				try {
					$result = $runner($code, $apiVars);
				} finally {
					$out = trim((string) ob_get_clean());
				}
				return $this->jsonResult(['result' => $result, 'output' => $out]);

			case 'pw_schema_read':
				return $this->handleSchemaRead($args);

			case 'pw_schema_field_create':
				$type = trim(self::argString($args, 'type'));
				$rawName = trim(self::argString($args, 'name'));
				if ($type === '' || $rawName === '') {
					return $this->errorResult('Required args: type, name');
				}
				$sanitizer = $this->requireWire('sanitizer');
				$nameArg = (string) $sanitizer->fieldName($rawName);
				if (
					$nameArg === ''
					|| $nameArg !== $rawName
					|| preg_match('/^(_|\d)/', $nameArg) === 1
					|| str_contains($nameArg, '__')
				) {
					return $this->errorResult("Invalid field machine name '{$rawName}'.");
				}
				$languagesApi = \ProcessWire\wire('languages');
				if (is_object($languagesApi) && method_exists($languagesApi, 'get')) {
					$langCollision = $languagesApi->get($nameArg);
					if (is_object($langCollision) && !empty($langCollision->id)) {
						return $this->errorResult("Field machine name '{$nameArg}' collides with an installed language name.");
					}
				}
				$fieldsApi = $this->requireWire('fields');
				if (
					(($fieldsApi->isNative($nameArg) || \ProcessWire\wire($nameArg) !== null)
						&& !in_array($nameArg, ['files', 'datetime'], true))
				) {
					return $this->errorResult("Field machine name '{$nameArg}' is a reserved ProcessWire name.");
				}
				if ($fieldsApi->get($nameArg)?->id) {
					return $this->errorResult("Field '{$nameArg}' already exists.");
				}
				$pwType = str_starts_with($type, 'Fieldtype') ? $type : 'Fieldtype' . ucfirst($type);
				if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $pwType) !== 1) {
					return $this->errorResult("Invalid Fieldtype class name '{$pwType}'.");
				}
				$disallowedFieldtypes = [
					'FieldtypeFieldsetClose',
					'FieldtypePageTitle',
					'FieldtypePageTitleLanguage',
					'FieldtypeModule',
				];
				if (in_array($pwType, $disallowedFieldtypes, true)) {
					return $this->errorResult("Field type '{$pwType}' is an internal/system Fieldtype and cannot be created standalone.");
				}
				$modulesApi = $this->requireWire('modules');
				if (!$modulesApi->isInstalled($pwType)) {
					return $this->errorResult("Field type '{$pwType}' not installed.");
				}
				$fieldtypeModule = $modulesApi->get($pwType);
				if (!($fieldtypeModule instanceof \ProcessWire\Fieldtype)) {
					return $this->errorResult("Module '{$pwType}' is not a valid Fieldtype.");
				}
				$f = new \ProcessWire\Field();
				$f->type = $fieldtypeModule;
				$f->name = $nameArg;
				$f->label = ucfirst(str_replace('_', ' ', $nameArg));
				if (!$fieldsApi->save($f) || !$f->id) {
					return $this->errorResult("Failed to save field '{$nameArg}'.");
				}
				return $this->jsonResult(['ok' => true, 'field' => $nameArg, 'type' => $pwType]);

			case 'pw_schema_template_create':
				$rawTplName = trim(self::argString($args, 'name'));
				if ($rawTplName === '') {
					return $this->errorResult('Required arg: name');
				}
				$tplName = (string) $this->requireWire('sanitizer')->templateName($rawTplName);
				if ($tplName === '' || $tplName !== $rawTplName || ctype_digit($tplName)) {
					return $this->errorResult("Invalid template machine name '{$rawTplName}'.");
				}
				$templatesApi = $this->requireWire('templates');
				if ($templatesApi->get($tplName)?->id) {
					return $this->errorResult("Template '{$tplName}' already exists.");
				}
				$fieldgroups = $this->requireWire('fieldgroups');
				if ($fieldgroups->get($tplName)?->id) {
					return $this->errorResult("A fieldgroup named '{$tplName}' already exists.");
				}
				$fg = new \ProcessWire\Fieldgroup();
				$fg->name = $tplName;
				$titleField = $this->requireWire('fields')->get('title');
				if ($titleField && $titleField->id) {
					$fg->add($titleField);
				}
				if (!$fieldgroups->save($fg) || !$fg->id) {
					return $this->errorResult("Failed to create fieldgroup '{$tplName}'.");
				}
				try {
					$t = new \ProcessWire\Template();
					$t->name = $tplName;
					$t->setFieldgroup($fg);
					if (!$templatesApi->save($t) || !$t->id) {
						$fieldgroups->delete($fg);
						return $this->errorResult("Failed to create template '{$tplName}'.");
					}
				} catch (\Throwable $e) {
					try {
						$fieldgroups->delete($fg);
					} catch (\Throwable) {
					}
					return $this->errorResult('Error: ' . $e->getMessage());
				}
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
				$modules = $this->requireWire('modules');
				$installed = $modules->getAll();
				$list = [];
				foreach ($installed as $modName => $_m) {
					$modNameStr = (string) $modName;
					$info = $modules->getModuleInfoVerbose($modNameStr);
					$modFile = str_replace('\\', '/', (string) $modules->getModuleFile($modNameStr));
					$list[] = [
						'name' => $modNameStr,
						'title' => (string) ($info['title'] ?? ''),
						'version' => $modules->formatVersion($info['version'] ?? 0),
						'summary' => (string) ($info['summary'] ?? ''),
						'core' => str_contains($modFile, '/wire/modules/'),
					];
				}
				return $this->jsonResult($list);

			case 'pw_module_info':
				$modName = trim(self::argString($args, 'name'));
				if ($modName === '') {
					return $this->errorResult('Required arg: name');
				}
				if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $modName) !== 1) {
					return $this->errorResult("Invalid module class name '{$modName}'.");
				}
				$modules = $this->requireWire('modules');
				$info = $modules->getModuleInfoVerbose($modName);
				if (
					!$info
					|| !empty($info['error'])
					|| (($info['name'] ?? '') === '' && !$modules->isInstalled($modName) && !$modules->getModuleFile($modName))
				) {
					return $this->errorResult("Module not found: {$modName}");
				}
				$payload = $info;
				$payload['version'] = $modules->formatVersion($info['version'] ?? 0);
				return $this->jsonResult($payload);

			case 'pw_module_install':
				$modName = trim(self::argString($args, 'name'));
				if ($modName === '') {
					return $this->errorResult('Required arg: name');
				}
				if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $modName) !== 1) {
					return $this->errorResult("Invalid module class name '{$modName}'.");
				}
				$modules = $this->requireWire('modules');
				if ($modules->isInstalled($modName)) {
					return $this->jsonResult(['ok' => true, 'installed' => true, 'note' => 'already']);
				}
				if (!$modules->getModuleFile($modName) || !$modules->isInstallable($modName)) {
					$modules->refresh();
				}
				if (!$modules->getModuleFile($modName)) {
					return $this->errorResult("Module not found: {$modName}");
				}
				if (!$modules->isInstallable($modName)) {
					return $this->errorResult("Module '{$modName}' is not currently installable (check module requirements).");
				}
				$installedObj = $modules->install($modName);
				if ($installedObj === null) {
					return $this->errorResult("Failed to install module '{$modName}'.");
				}
				return $this->jsonResult(['ok' => true, 'installed' => true]);

			case 'pw_module_uninstall':
				$modName = trim(self::argString($args, 'name'));
				if ($modName === '') {
					return $this->errorResult('Required arg: name');
				}
				if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $modName) !== 1) {
					return $this->errorResult("Invalid module class name '{$modName}'.");
				}
				$modules = $this->requireWire('modules');
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
				$this->requireWire('modules')->refresh();
				return $this->textResult('Modules refreshed.');

			case 'pw_module_upgrade':
				$modName = trim(self::argString($args, 'name'));
				if ($modName === '') {
					return $this->errorResult('Required arg: name');
				}
				if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $modName) !== 1) {
					return $this->errorResult("Invalid module class name '{$modName}'.");
				}
				$modules = $this->requireWire('modules');
				$modules->refresh();
				if (!$modules->isInstalled($modName)) {
					return $this->errorResult("Module '{$modName}' is not installed.");
				}
				$upgradedModule = $modules->getModule($modName, [
					'noPermissionCheck' => true,
					'noInit' => true,
					'configOnly' => true,
					'noInstall' => true,
				]);
				if ($upgradedModule === null) {
					return $this->errorResult("Failed to load module '{$modName}' for upgrade check.");
				}
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
				$rawUname = trim(self::argString($args, 'name'));
				$rawEmail = trim(self::argString($args, 'email'));
				$pass = self::argString($args, 'password');
				$rolesIn = $args['roles'] ?? [];
				if (!is_array($rolesIn)) {
					return $this->errorResult("Argument 'roles' must be an array of role names.");
				}
				if ($rawUname === '' || $pass === '') {
					return $this->errorResult('Required args: name, password (email optional)');
				}
				$sanitizer = $this->requireWire('sanitizer');
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
				$superRoleId = (int) ($this->requireWire('config')->superUserRolePageID ?? 38);
				foreach ($rolesIn as $r) {
					if (!is_string($r) && !is_int($r)) {
						return $this->errorResult("Each entry in 'roles' must be a string.");
					}
					$roleCandidate = trim((string) $r);
					if ($roleCandidate === '') {
						continue;
					}
					if (strtolower($roleCandidate) === 'superuser' || (ctype_digit($roleCandidate) && (int) $roleCandidate === $superRoleId)) {
						return $this->errorResult('Refusing to assign superuser role via MCP.');
					}
					$role = $this->findNamedSystemItem('roles', $roleCandidate);
					if (!($role instanceof \ProcessWire\Role) || !$role->id) {
						return $this->errorResult("Role not found: '{$roleCandidate}'.");
					}
					if ((string) $role->name === 'superuser' || (int) $role->id === $superRoleId) {
						return $this->errorResult('Refusing to assign superuser role via MCP.');
					}
					$resolvedRolesToAssign[] = $role;
				}
				$users = $this->requireWire('users');
				if ($this->findNamedSystemItem('users', $uname) !== null) {
					return $this->errorResult("User '{$uname}' already exists.");
				}
				$u = $users->add($uname);
				if (!($u instanceof \ProcessWire\User) || !$u->id) {
					return $this->errorResult("Failed to create user '{$uname}'.");
				}
				try {
					$u->of(false);
					if ($email !== '') {
						$u->email = $email;
					}
					$u->pass = $pass;
					foreach ($resolvedRolesToAssign as $role) {
						$u->addRole($role);
					}
					if (!$users->save($u)) {
						$users->delete($u);
						return $this->errorResult("Failed to save user '{$uname}'.");
					}
				} catch (\Throwable $e) {
					try {
						$users->delete($u);
					} catch (\Throwable) {
					}
					return $this->errorResult('Error: ' . $e->getMessage());
				}
				return $this->jsonResult(['ok' => true, 'userId' => $u->id]);

			case 'pw_access_role_create':
				$rawRoleName = trim(self::argString($args, 'name'));
				if ($rawRoleName === '') {
					return $this->errorResult('Required arg: name');
				}
				$rname = (string) $this->requireWire('sanitizer')->pageName($rawRoleName);
				if ($rname === '' || $rname !== $rawRoleName || ctype_digit($rname)) {
					return $this->errorResult("Invalid role name '{$rawRoleName}'.");
				}
				$roles = $this->requireWire('roles');
				if ($this->findNamedSystemItem('roles', $rname) !== null) {
					return $this->errorResult("Role '{$rname}' already exists.");
				}
				$role = $roles->add($rname);
				if (!($role instanceof \ProcessWire\Role) || !$role->id) {
					return $this->errorResult("Failed to create role '{$rname}'.");
				}
				return $this->jsonResult(['ok' => true, 'roleId' => $role->id]);

			case 'pw_access_user_update':
				$id = $args['id'] ?? null;
				$uname = trim(self::argString($args, 'name'));
				$rawEmail = trim(self::argString($args, 'email'));
				$pass = self::argString($args, 'password');
				$addRoles = $args['addRoles'] ?? [];
				$removeRoles = $args['removeRoles'] ?? [];
				if (!is_array($addRoles) || !is_array($removeRoles)) {
					return $this->errorResult("Arguments 'addRoles' and 'removeRoles' must be arrays.");
				}
				if (($id === null || $id === '') && $uname === '') {
					return $this->errorResult("Provide 'id' or 'name'.");
				}
				$sanitizer = $this->requireWire('sanitizer');
				$users = $this->requireWire('users');
				if ($id !== null && $id !== '') {
					if (!is_int($id) && !is_string($id)) {
						return $this->errorResult("Invalid user ID.");
					}
					$idStr = trim((string) $id);
					if (!ctype_digit($idStr) || (int) $idStr < 1) {
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
				$superRoleId = (int) ($this->requireWire('config')->superUserRolePageID ?? 38);
				$rolesToAdd = [];
				foreach ($addRoles as $r) {
					if (!is_string($r) && !is_int($r)) {
						return $this->errorResult("Each entry in 'addRoles' must be a string.");
					}
					$roleCandidate = trim((string) $r);
					if ($roleCandidate === '') {
						continue;
					}
					if (strtolower($roleCandidate) === 'superuser' || (ctype_digit($roleCandidate) && (int) $roleCandidate === $superRoleId)) {
						return $this->errorResult('Refusing to grant superuser role via MCP.');
					}
					$role = $this->findNamedSystemItem('roles', $roleCandidate);
					if (!($role instanceof \ProcessWire\Role) || !$role->id) {
						return $this->errorResult("Role not found: '{$roleCandidate}'.");
					}
					if ((string) $role->name === 'superuser' || (int) $role->id === $superRoleId) {
						return $this->errorResult('Refusing to grant superuser role via MCP.');
					}
					$rolesToAdd[] = $role;
				}
				$rolesToRemove = [];
				foreach ($removeRoles as $r) {
					if (!is_string($r) && !is_int($r)) {
						return $this->errorResult("Each entry in 'removeRoles' must be a string.");
					}
					$roleCandidate = trim((string) $r);
					if ($roleCandidate === '') {
						continue;
					}
					$role = $this->findNamedSystemItem('roles', $roleCandidate);
					if (!($role instanceof \ProcessWire\Role) || !$role->id) {
						return $this->errorResult("Role not found: '{$roleCandidate}'.");
					}
					if ((string) $role->name === 'guest' || (string) $role->name === 'superuser') {
						return $this->errorResult("Refusing to remove protected system role '{$role->name}' via MCP.");
					}
					$rolesToRemove[] = $role;
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
				foreach ($rolesToRemove as $role) {
					if ($user->hasRole($role)) {
						$user->removeRole($role);
					}
				}
				if (!$users->save($user)) {
					return $this->errorResult("Failed to save user '{$user->name}'.");
				}
				return $this->jsonResult(['ok' => true, 'userId' => $user->id]);

			case 'pw_access_user_delete':
				$id = $args['id'] ?? null;
				$uname = trim(self::argString($args, 'name'));
				if (($id === null || $id === '') && $uname === '') {
					return $this->errorResult("Provide 'id' or 'name'.");
				}
				$users = $this->requireWire('users');
				if ($id !== null && $id !== '') {
					if (!is_int($id) && !is_string($id)) {
						return $this->errorResult("Invalid user ID.");
					}
					$idStr = trim((string) $id);
					if (!ctype_digit($idStr) || (int) $idStr < 1) {
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
				if (!$users->delete($user)) {
					return $this->errorResult("Failed to delete user '{$user->name}'.");
				}
				return $this->jsonResult(['ok' => true, 'deleted' => true]);

			case 'pw_access_user_list':
				$search = trim(self::argString($args, 'search'));
				$roleFilter = trim(self::argString($args, 'role'));
				$limit = max(1, min(500, self::argInt($args, 'limit', 50)));
				$sanitizer = $this->requireWire('sanitizer');
				$selector = [];
				if ($search !== '') {
					$nameProbe = (string) $sanitizer->pageName($search, true);
					$safeSearch = (string) $sanitizer->selectorValue($search);
					if ($nameProbe === '' || $safeSearch === '') {
						return $this->jsonResult([]);
					}
					$selector[] = "name%={$safeSearch}";
				}
				if ($roleFilter !== '') {
					$safeRole = (string) $sanitizer->selectorValue($roleFilter);
					if ($safeRole === '') {
						return $this->jsonResult([]);
					}
					$selector[] = "roles={$safeRole}";
				}
				$selector[] = "limit={$limit}";
				$users = $this->requireWire('users')->find(implode(', ', $selector));
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
				$permName = trim(self::argString($args, 'name'));
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
				$isSystemPerm = (((int) $perm->status) & (\ProcessWire\Page::statusSystem | \ProcessWire\Page::statusSystemID)) !== 0;
				if ($isSystemPerm || in_array((string) $perm->name, self::SYSTEM_PERMISSIONS, true)) {
					return $this->errorResult("Refusing to delete built-in ProcessWire permission '{$perm->name}'.");
				}
				if (!$this->requireWire('permissions')->delete($perm)) {
					return $this->errorResult("Failed to delete permission '{$perm->name}'.");
				}
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
				$logName = self::normalizeLogName(self::argString($args, 'name', 'errors'));
				if ($logName === '') {
					return $this->errorResult('Invalid log name.');
				}
				$logsDir = (string) $this->requireWire('config')->paths->logs;
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
				if (@file_put_contents($realPath, '') === false) {
					return $this->errorResult("Failed to clear log '{$logName}'.");
				}
				return $this->jsonResult(['ok' => true, 'cleared' => $logName]);

			case 'pw_system_backup_list':
				$dir = (string) $this->requireWire('config')->paths->assets . 'backups/database/';
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
				$days = self::argInt($args, 'days', 30);
				if ($days < 1) {
					return $this->errorResult('Argument "days" must be at least 1.');
				}
				$threshold = time() - ($days * 86400);
				$dir = (string) $this->requireWire('config')->paths->assets . 'backups/database/';
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
				$file = trim(self::argString($args, 'file'));
				if ($file === '') {
					return $this->errorResult('Required arg: file');
				}
				$assetsDir = (string) $this->requireWire('config')->paths->assets . 'backups/database/';
				$candidatePath = (!str_contains($file, '/') && !str_contains($file, '\\'))
					? $assetsDir . $file
					: $file;
				$realAssetsDir = realpath($assetsDir);
				$real = realpath($candidatePath);
				if (
					is_link($candidatePath)
					|| $real === false
					|| $realAssetsDir === false
					|| dirname($real) !== rtrim($realAssetsDir, DIRECTORY_SEPARATOR)
					|| !is_file($real)
					|| !is_readable($real)
				) {
					return $this->errorResult('File not permitted: must reside directly under backups/database and be readable.');
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
				$db = $this->requireWire('database');
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
				$key = self::argString($args, 'key');
				$pattern = self::argString($args, 'pattern');
				if ($key === '' && $pattern === '') {
					return $this->errorResult("Provide 'key' or 'pattern'.");
				}
				$db = $this->requireWire('database');
				$table = '`' . $db->escapeTable('caches') . '`';
				$reserved = defined('\ProcessWire\WireCache::expireReserved') ? \ProcessWire\WireCache::expireReserved : '2010-04-08 03:10:01';
				if ($key !== '') {
					$stmt = $db->prepare("DELETE FROM {$table} WHERE name = :k AND expires != :reserved");
					$stmt->bindValue(':k', $key);
					$stmt->bindValue(':reserved', $reserved);
					$stmt->execute();
					return $this->jsonResult(['ok' => true, 'deleted' => $stmt->rowCount()]);
				}
				$stmt = $db->prepare("DELETE FROM {$table} WHERE name LIKE :p AND expires != :reserved");
				$stmt->bindValue(':p', $pattern);
				$stmt->bindValue(':reserved', $reserved);
				$stmt->execute();
				return $this->jsonResult(['ok' => true, 'deleted' => $stmt->rowCount()]);

			case 'pw_system_get_logs':
				$logName = self::normalizeLogName(self::argString($args, 'name', 'errors'));
				if ($logName === '') {
					return $this->errorResult('Invalid log name.');
				}
				$limit = max(1, min(1000, self::argInt($args, 'limit', 10)));
				$logsDir = (string) $this->requireWire('config')->paths->logs;
				$logPath = $logsDir . $logName . '.txt';
				if (is_link($logPath)) {
					return $this->errorResult('Refusing to read symbolic link log file.');
				}
				if (!is_file($logPath)) {
					return $this->textResult('No entries found.');
				}
				$realLogsDir = realpath($logsDir);
				$realLogPath = realpath($logPath);
				if (
					$realLogsDir === false
					|| $realLogPath === false
					|| !str_starts_with($realLogPath, rtrim($realLogsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
				) {
					return $this->errorResult('Invalid log file path.');
				}
				$entries = $this->requireWire('log')->getEntries($logName, ['limit' => $limit]);
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
				$logName = self::normalizeLogName(self::argString($args, 'name', 'errors'));
				if ($logName === '') {
					return $this->errorResult('Invalid log name.');
				}
				$lines = max(1, min(5000, self::argInt($args, 'lines', 200)));
				$logsDir = (string) $this->requireWire('config')->paths->logs;
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
				$this->requireWire('modules')->refresh();
				$compiledPath = (string) $this->requireWire('config')->paths->cache . 'FileCompiler/';
				if (is_dir($compiledPath) && !is_link(rtrim($compiledPath, '/'))) {
					$iterator = new \RecursiveIteratorIterator(
						new \RecursiveDirectoryIterator($compiledPath, \RecursiveDirectoryIterator::SKIP_DOTS),
						\RecursiveIteratorIterator::CHILD_FIRST
					);
					foreach ($iterator as $fileInfo) {
						/** @var \SplFileInfo $fileInfo */
						$pathname = $fileInfo->getPathname();
						if ($fileInfo->isLink()) {
							@unlink($pathname);
						} elseif ($fileInfo->isDir()) {
							@rmdir($pathname);
						} else {
							@unlink($pathname);
						}
					}
				}
				return $this->textResult('Caches cleared and modules refreshed.');

			case 'pw_system_backup':
				$backup = $this->requireWire('database')->backups();
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
		$roleName = trim(self::argString($args, 'role'));
		$permName = trim(self::argString($args, 'permission'));
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
		$roles = $this->requireWire('roles');
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
		$sanitizer = $this->requireWire('sanitizer');
		$clean = (string) $sanitizer->pageName($trimmed, true);
		if ($clean === '' || $clean !== strtolower($trimmed)) {
			$exact = (string) $sanitizer->pageName($trimmed);
			if ($exact === '' || $exact !== $trimmed) {
				return null;
			}
			$clean = $exact;
		}
		$safeVal = $sanitizer->selectorValue($clean);
		$item = $this->requireWire($apiVar)->get("name={$safeVal}");

		return ($item instanceof \ProcessWire\Page && $item->id) ? $item : null;
	}

	private function requireWire(string $apiVar): mixed
	{
		if (!function_exists('\ProcessWire\wire')) {
			throw new \RuntimeException('ProcessWire runtime is not booted.');
		}
		$instance = \ProcessWire\wire($apiVar);
		if ($instance === null) {
			throw new \RuntimeException("ProcessWire API variable '{$apiVar}' is unavailable.");
		}

		return $instance;
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	private function handleSchemaRead(array $args): array
	{
		$fieldFilter = trim(self::argString($args, 'field'));
		$templateFilter = trim(self::argString($args, 'template'));
		$sectionFilter = trim(self::argString($args, 'section'));
		$deep = !empty($args['deep']) || $fieldFilter !== '';

		$map = (new BoostManager())->buildSchemaMap($deep);

		if ($fieldFilter !== '') {
			if (!isset($map['fields'][$fieldFilter])) {
				return $this->errorResult("Field '{$fieldFilter}' not found in schema.");
			}
			return $this->jsonResult([
				'field' => $fieldFilter,
				'schema' => $map['fields'][$fieldFilter],
			]);
		}

		if ($templateFilter !== '') {
			if (!isset($map['templates'][$templateFilter])) {
				return $this->errorResult("Template '{$templateFilter}' not found in schema.");
			}
			$tplSchema = $map['templates'][$templateFilter];
			$attachedFields = [];
			$fieldNames = is_array($tplSchema) && is_array($tplSchema['fields'] ?? null) ? $tplSchema['fields'] : [];
			foreach ($fieldNames as $fname) {
				if (is_string($fname) && isset($map['fields'][$fname])) {
					$attachedFields[$fname] = $map['fields'][$fname];
				}
			}
			return $this->jsonResult([
				'template' => $templateFilter,
				'schema' => $tplSchema,
				'fields' => $attachedFields,
			]);
		}

		if ($sectionFilter !== '') {
			if (!array_key_exists($sectionFilter, $map)) {
				return $this->errorResult("Invalid schema section '{$sectionFilter}'. Valid sections: templates, fields, modules, roles, permissions.");
			}
			return $this->jsonResult([$sectionFilter => $map[$sectionFilter]]);
		}

		return $this->jsonResult($map);
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private static function argString(array $args, string $key, string $default = ''): string
	{
		if (!array_key_exists($key, $args) || $args[$key] === null) {
			return $default;
		}
		$val = $args[$key];
		if (is_string($val)) {
			return $val;
		}
		if (is_int($val) || (is_float($val) && is_finite($val))) {
			return (string) $val;
		}

		throw new \InvalidArgumentException("Argument '{$key}' must be a string.");
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private static function argInt(array $args, string $key, int $default): int
	{
		if (!array_key_exists($key, $args) || $args[$key] === null || $args[$key] === '') {
			return $default;
		}
		$val = $args[$key];
		if (is_int($val)) {
			return $val;
		}
		if (is_string($val) && preg_match('/^-?\d+$/', trim($val)) === 1) {
			return (int) trim($val);
		}

		throw new \InvalidArgumentException("Argument '{$key}' must be an integer.");
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
			if (preg_match('/^\s*limit\s*=\s*0*([1-9]\d*)\s*$/i', $clause) === 1) {
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
			if (preg_match('/^(\s*)(?:!+\s*|[A-Za-z0-9_.-]*@\s*)*limit(?![A-Za-z0-9_.])\s*(.*)$/i', $clause) === 1) {
				if (preg_match('/^(\s*)limit\s*=\s*0*([1-9]\d*)\s*$/i', $clause, $m) === 1) {
					$num = (int) $m[2];
					if ($num > 0) {
						$foundPositiveLimit = true;
						$clamped = min($maxLimit, $num);
						$rebuilt[] = $m[1] . 'limit=' . $clamped;
					}
				}
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
		$bracketDepth = 0;
		$parenDepth = 0;
		$braceDepth = 0;
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

			if ($ch === '{') {
				$braceDepth++;
				$current .= $ch;
				continue;
			}

			if ($ch === '}') {
				$braceDepth = max(0, $braceDepth - 1);
				$current .= $ch;
				continue;
			}

			if ($ch === '[') {
				$bracketDepth++;
				$current .= $ch;
				continue;
			}

			if ($ch === ']') {
				$bracketDepth = max(0, $bracketDepth - 1);
				$current .= $ch;
				continue;
			}

			if ($ch === '(') {
				$parenDepth++;
				$current .= $ch;
				continue;
			}

			if ($ch === ')') {
				$parenDepth = max(0, $parenDepth - 1);
				$current .= $ch;
				continue;
			}

			if ($ch === ',' && $bracketDepth === 0 && $parenDepth === 0 && $braceDepth === 0) {
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
		$base = strtolower((string) preg_replace('/\.txt$/i', '', $trimmed));
		if ($base === '' || $base === '.' || $base === '..' || str_starts_with($base, '.') || str_ends_with($base, '.')) {
			return '';
		}
		if (preg_match('/^[a-z0-9._-]+$/', $base) !== 1) {
			return '';
		}
		return $base;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function collectProcessWireContext(): array
	{
		if (
			!class_exists('\\ProcessWire\\ProcessWire', false)
			|| !function_exists('ProcessWire\\wire')
		) {
			return [];
		}

		$wireInstance = \ProcessWire\ProcessWire::getCurrentInstance();
		if ($wireInstance === null) {
			return [];
		}

		$vars = ['wire' => $wireInstance];
		$names = [
			'pages',
			'page',
			'user',
			'users',
			'templates',
			'fields',
			'roles',
			'permissions',
			'config',
			'modules',
			'database',
			'sanitizer',
			'session',
			'input',
			'log',
			'cache',
			'files',
			'datetime',
			'languages',
		];

		foreach ($names as $varName) {
			$val = \ProcessWire\wire($varName);
			if ($val !== null) {
				$vars[$varName] = $val;
			}
		}

		return $vars;
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
		$encoded = json_encode(
			$data,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
		);
		return $this->textResult(is_string($encoded) ? $encoded : '{}');
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

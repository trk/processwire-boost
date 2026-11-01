<?php

declare(strict_types=1);

namespace Totoglu\Console\Boost\Mcp;

use Totoglu\Console\Boost\BoostManager;
use Totoglu\Console\Support\FeatureDiscoverer;

final class McpServer
{
	/**
	 * @param array<string, mixed> $request
	 * @return ?array<string, mixed>
	 */
	public function handleRequest(array $request): ?array
	{
		$method = is_string($request['method'] ?? null) ? $request['method'] : '';
		$hasId = array_key_exists('id', $request);
		$id = $request['id'] ?? null;

		if (!$hasId && str_starts_with($method, 'notifications/')) {
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
				$toolName = is_string($params['name'] ?? null) ? $params['name'] : '';
				$args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
				return $this->buildResponse($id, $this->callTool($toolName, $args));

			default:
				if (!$hasId) {
					return null;
				}

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
				default => $this->errorResult("Tool '{$name}' not yet implemented in native MCP."),
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
				$selector = (string) ($args['selector'] ?? 'id>0');
				if (!str_contains($selector, 'limit=')) {
					$limit = (int) ($args['limit'] ?? 100);
					$selector .= ", limit={$limit}";
				}
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
				$allow = getenv('PW_MCP_ALLOW_EXECUTE') === '1';
				if (!$allow) {
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
				$type = (string) ($args['type'] ?? '');
				$nameArg = (string) ($args['name'] ?? '');
				if ($type === '' || $nameArg === '') {
					return $this->errorResult('Required args: type, name');
				}
				$fieldsApi = \ProcessWire\wire('fields');
				if ($fieldsApi->get($nameArg)->id) {
					return $this->errorResult("Field '{$nameArg}' already exists.");
				}
				$pwType = 'Fieldtype' . ucfirst($type);
				if (!\ProcessWire\wire('modules')->get($pwType)) {
					return $this->errorResult("Field type '{$pwType}' not installed.");
				}
				$f = new \ProcessWire\Field();
				$f->type = \ProcessWire\wire('modules')->get($pwType);
				$f->name = $nameArg;
				$f->label = ucfirst($nameArg);
				$f->save();
				return $this->jsonResult(['ok' => true, 'field' => $nameArg, 'type' => $type]);

			case 'pw_schema_template_create':
				$tplName = (string) ($args['name'] ?? '');
				if ($tplName === '') {
					return $this->errorResult('Required arg: name');
				}
				if (\ProcessWire\wire('templates')->get($tplName)->id) {
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
					$fg->save();
				}
				$t = new \ProcessWire\Template();
				$t->name = $tplName;
				$t->fieldgroup = $fg;
				$t->save();
				return $this->jsonResult(['ok' => true, 'template' => $tplName]);

			default:
				return $this->errorResult("Tool '{$name}' not yet implemented in native MCP.");
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
					$info = $modules->getModuleInfo($modName);
					$list[] = [
						'name' => $modName,
						'title' => $info['title'] ?? '',
						'version' => $modules->formatVersion($info['version'] ?? 0),
						'summary' => $info['summary'] ?? '',
						'core' => str_contains($modules->getModuleFile($modName), '/wire/modules/'),
					];
				}
				return $this->jsonResult($list);

			case 'pw_module_info':
				$modName = (string) ($args['name'] ?? '');
				if ($modName === '') {
					return $this->errorResult('Required arg: name');
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
				$modName = (string) ($args['name'] ?? '');
				if ($modName === '') {
					return $this->errorResult('Required arg: name');
				}
				$modules = \ProcessWire\wire('modules');
				if ($modules->isInstalled($modName)) {
					return $this->jsonResult(['ok' => true, 'installed' => true, 'note' => 'already']);
				}
				$modules->install($modName);
				return $this->jsonResult(['ok' => true, 'installed' => true]);

			case 'pw_module_uninstall':
				$modName = (string) ($args['name'] ?? '');
				if ($modName === '') {
					return $this->errorResult('Required arg: name');
				}
				$modules = \ProcessWire\wire('modules');
				if (!$modules->isInstalled($modName)) {
					return $this->jsonResult(['ok' => true, 'uninstalled' => true, 'note' => 'already']);
				}
				$modules->uninstall($modName);
				return $this->jsonResult(['ok' => true, 'uninstalled' => true]);

			case 'pw_module_refresh':
				\ProcessWire\wire('modules')->refresh();
				return $this->textResult('Modules refreshed.');

			case 'pw_module_upgrade':
				$modName = (string) ($args['name'] ?? '');
				if ($modName === '') {
					return $this->errorResult('Required arg: name');
				}
				$modules = \ProcessWire\wire('modules');
				$modules->refresh();
				if ($modules->isInstalled($modName)) {
					$modules->get($modName);
				}
				return $this->jsonResult(['ok' => true, 'upgraded' => $modules->isInstalled($modName)]);

			default:
				return $this->errorResult("Tool '{$name}' not yet implemented in native MCP.");
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
				$uname = (string) ($args['name'] ?? '');
				$email = (string) ($args['email'] ?? '');
				$pass = (string) ($args['password'] ?? '');
				$rolesIn = $args['roles'] ?? [];
				if ($uname === '' || $pass === '') {
					return $this->errorResult('Required args: name, password (email optional)');
				}
				$users = \ProcessWire\wire('users');
				if ($users->get($uname)->id) {
					return $this->errorResult("User '{$uname}' already exists.");
				}
				$u = new \ProcessWire\User();
				$u->name = $uname;
				if ($email !== '') {
					$u->email = $email;
				}
				$u->pass = $pass;
				$u->save();
				if (is_array($rolesIn)) {
					$rolesApi = \ProcessWire\wire('roles');
					foreach ($rolesIn as $r) {
						$role = $rolesApi->get((string) $r);
						if ($role && $role->id) {
							$u->addRole($role);
						}
					}
					$u->save();
				}
				return $this->jsonResult(['ok' => true, 'userId' => $u->id]);

			case 'pw_access_role_create':
				$rname = (string) ($args['name'] ?? '');
				if ($rname === '') {
					return $this->errorResult('Required arg: name');
				}
				$roles = \ProcessWire\wire('roles');
				if ($roles->get($rname)->id) {
					return $this->errorResult("Role '{$rname}' already exists.");
				}
				$role = new \ProcessWire\Role();
				$role->name = $rname;
				$role->save();
				return $this->jsonResult(['ok' => true, 'roleId' => $role->id]);

			case 'pw_access_user_update':
				$id = $args['id'] ?? null;
				$uname = (string) ($args['name'] ?? '');
				$email = (string) ($args['email'] ?? '');
				$pass = (string) ($args['password'] ?? '');
				$addRoles = $args['addRoles'] ?? [];
				$removeRoles = $args['removeRoles'] ?? [];
				if ($id === null && $uname === '') {
					return $this->errorResult("Provide 'id' or 'name'.");
				}
				$users = \ProcessWire\wire('users');
				$rolesApi = \ProcessWire\wire('roles');
				$user = $id !== null ? $users->get((int) $id) : $users->get($uname);
				if (!$user || !$user->id) {
					return $this->errorResult('User not found.');
				}
				if ($email !== '') {
					$user->email = $email;
				}
				if ($pass !== '') {
					$user->pass = $pass;
				}
				if (is_array($addRoles)) {
					foreach ($addRoles as $r) {
						$role = $rolesApi->get((string) $r);
						if ($role && $role->id && !$user->hasRole($role)) {
							$user->addRole($role);
						}
					}
				}
				if (is_array($removeRoles)) {
					foreach ($removeRoles as $r) {
						$role = $rolesApi->get((string) $r);
						if ($role && $role->id && $user->hasRole($role)) {
							$user->removeRole($role);
						}
					}
				}
				$user->save();
				return $this->jsonResult(['ok' => true, 'userId' => $user->id]);

			case 'pw_access_user_delete':
				$id = $args['id'] ?? null;
				$uname = (string) ($args['name'] ?? '');
				if ($id === null && $uname === '') {
					return $this->errorResult("Provide 'id' or 'name'.");
				}
				$users = \ProcessWire\wire('users');
				$user = $id !== null ? $users->get((int) $id) : $users->get($uname);
				if (!$user || !$user->id) {
					return $this->errorResult('User not found.');
				}
				if ($user->isSuperuser()) {
					return $this->errorResult('Refusing to delete a superuser.');
				}
				$users->delete($user);
				return $this->jsonResult(['ok' => true, 'deleted' => true]);

			case 'pw_access_user_list':
				$search = (string) ($args['search'] ?? '');
				$roleFilter = (string) ($args['role'] ?? '');
				$limit = (int) ($args['limit'] ?? 50);
				$selector = [];
				if ($search !== '') {
					$search = \ProcessWire\wire('sanitizer')->selectorValue($search);
					$selector[] = "name%={$search}";
				}
				if ($roleFilter !== '') {
					$roleFilter = \ProcessWire\wire('sanitizer')->selectorValue($roleFilter);
					$selector[] = "roles={$roleFilter}";
				}
				if ($limit > 0) {
					$selector[] = "limit={$limit}";
				}
				if ($selector === []) {
					$selector[] = 'limit=50';
				}
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
				$permName = (string) ($args['name'] ?? '');
				if ($permName === '') {
					return $this->errorResult('Arg required: name');
				}
				$permissions = \ProcessWire\wire('permissions');
				$perm = $permissions->get($permName);
				if (!$perm || !$perm->id) {
					return $this->errorResult('Permission not found.');
				}
				$permissions->delete($perm);
				return $this->jsonResult(['ok' => true, 'deleted' => true]);

			default:
				return $this->errorResult("Tool '{$name}' not yet implemented in native MCP.");
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
				$logName = basename((string) ($args['name'] ?? 'errors'));
				$path = \ProcessWire\wire('config')->paths->logs . $logName . '.txt';
				if (!is_file($path)) {
					return $this->errorResult('Log not found.');
				}
				@file_put_contents($path, '');
				return $this->jsonResult(['ok' => true, 'cleared' => $logName]);

			case 'pw_system_backup_list':
				$dir = \ProcessWire\wire('config')->paths->assets . 'backups/database/';
				if (!is_dir($dir)) {
					return $this->jsonResult([]);
				}
				$files = array_values(array_filter(
					scandir($dir) ?: [],
					static fn(string $f): bool => $f !== '.' && $f !== '..' && str_ends_with(strtolower($f), '.sql') && is_file($dir . $f)
				));
				usort($files, static fn(string $a, string $b): int => filemtime($dir . $b) <=> filemtime($dir . $a));
				return $this->jsonResult($files);

			case 'pw_system_backup_purge':
				$days = (int) ($args['days'] ?? 30);
				$threshold = time() - ($days * 86400);
				$dir = \ProcessWire\wire('config')->paths->assets . 'backups/database/';
				if (!is_dir($dir)) {
					return $this->jsonResult(['deleted' => 0]);
				}
				$deleted = 0;
				foreach (scandir($dir) ?: [] as $f) {
					if ($f === '.' || $f === '..' || !str_ends_with(strtolower($f), '.sql')) {
						continue;
					}
					$path = $dir . $f;
					if (is_file($path) && filemtime($path) < $threshold) {
						if (@unlink($path)) {
							$deleted++;
						}
					}
				}
				return $this->jsonResult(['ok' => true, 'deleted' => $deleted]);

			case 'pw_system_restore':
				$allow = getenv('PW_MCP_ALLOW_RESTORE') === '1';
				if (!$allow) {
					return $this->errorResult('pw_system_restore disabled. Set PW_MCP_ALLOW_RESTORE=1 to enable.');
				}
				$file = (string) ($args['file'] ?? '');
				if ($file === '') {
					return $this->errorResult('Required arg: file');
				}
				$assetsDir = \ProcessWire\wire('config')->paths->assets . 'backups/database/';
				$realAssetsDir = realpath($assetsDir);
				$real = realpath($file);
				if (
					$real === false
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
				$sql = file_get_contents($real) ?: '';
				if (trim($sql) === '') {
					return $this->errorResult('SQL file is empty.');
				}
				$db = \ProcessWire\wire('database');
				try {
					$backups = $db->backups();
					if ($backups) {
						$restored = $backups->restore(basename($real));
						if (!$restored) {
							$errors = method_exists($backups, 'errors') ? implode('; ', $backups->errors()) : 'Database restore returned false.';
							return $this->errorResult("Restore failed: {$errors}");
						}
					} else {
						$db->exec($sql);
					}
				} catch (\Throwable $e) {
					return $this->errorResult('Restore failed: ' . $e->getMessage());
				}
				return $this->jsonResult(['ok' => true, 'restored' => basename($real)]);

			case 'pw_system_cache_wire_clear':
				$key = (string) ($args['key'] ?? '');
				$pattern = (string) ($args['pattern'] ?? '');
				if ($key === '' && $pattern === '') {
					return $this->errorResult("Provide 'key' or 'pattern'.");
				}
				if ($key !== '') {
					\ProcessWire\wire('cache')->delete($key);
					return $this->jsonResult(['ok' => true, 'deleted' => 1]);
				}
				$db = \ProcessWire\wire('database');
				$table = $db->quoteIdentifier('caches');
				$stmt = $db->prepare("DELETE FROM {$table} WHERE name LIKE :p");
				$stmt->bindValue(':p', $pattern);
				$stmt->execute();
				return $this->jsonResult(['ok' => true, 'deleted' => $stmt->rowCount()]);

			case 'pw_system_get_logs':
				$logName = basename((string) ($args['name'] ?? 'errors'));
				$limit = (int) ($args['limit'] ?? 10);
				$entries = \ProcessWire\wire('log')->get($logName, $limit);
				$text = '';
				foreach ($entries as $e) {
					$text .= "[{$e['date']}] {$e['text']}\n";
				}
				return $this->textResult($text !== '' ? $text : 'No entries found.');

			case 'pw_system_logs_tail_last':
				$logName = basename((string) ($args['name'] ?? 'errors'));
				$lines = max(1, (int) ($args['lines'] ?? 200));
				$path = \ProcessWire\wire('config')->paths->logs . $logName . '.txt';
				if (!is_file($path)) {
					return $this->errorResult('Log not found.');
				}
				$slice = FeatureDiscoverer::readLastLines($path, $lines);
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
				return $this->errorResult("Tool '{$name}' not yet implemented in native MCP.");
		}
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	private function mutateRolePermission(array $args, bool $grant): array
	{
		$roleName = (string) ($args['role'] ?? '');
		$permName = (string) ($args['permission'] ?? '');
		if ($roleName === '' || $permName === '') {
			return $this->errorResult('Args required: role, permission');
		}
		$roles = \ProcessWire\wire('roles');
		$permissions = \ProcessWire\wire('permissions');
		$role = $roles->get($roleName);
		$permission = $permissions->get($permName);
		if (!$role || !$role->id || !$permission || !$permission->id) {
			return $this->errorResult('Role or permission not found.');
		}
		if ($grant && !$role->hasPermission($permission)) {
			$role->addPermission($permission);
			$role->save();
		} elseif (!$grant && $role->hasPermission($permission)) {
			$role->removePermission($permission);
			$role->save();
		}
		return $this->jsonResult(['ok' => true, $grant ? 'granted' : 'revoked' => true]);
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
		return $this->textResult((string) json_encode($data, JSON_PRETTY_PRINT));
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
}

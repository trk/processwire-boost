<?php

declare(strict_types=1);

namespace Totoglu\ProcessWire\Boost\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class McpCommand extends Command
{
    private array $tools = [];

    protected function configure(): void
    {
        $this
            ->setName('boost:mcp')
            ->setDescription('Start the ProcessWire MCP server (JSON-RPC over stdio).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->registerTools();
        $buffer = '';

        while (!feof(STDIN)) {
            $chunk = fgets(STDIN);
            if ($chunk === false) {
                break;
            }
            $buffer .= $chunk;

            while (str_contains($buffer, "\n")) {
                list($line, $buffer) = explode("\n", $buffer, 2);
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $request = json_decode($line, true);
                if (!$request) {
                    continue;
                }

                ob_start();
                try {
                    $this->handleRequest($request);
                } finally {
                    ob_end_clean();
                }
            }
        }
        return Command::SUCCESS;
    }

    private function registerTools(): void
    {
        $this->tools = [
            'pw_query' => 'Run a ProcessWire selector and return JSON.',
            'pw_execute' => 'Execute PHP code via Tinker (guarded).',
            'pw_schema_read' => 'Read current site schema (templates/fields).',
            'pw_schema_field_create' => 'Create a new field.',
            'pw_schema_template_create' => 'Create a new template.',
            'pw_module_list' => 'List installed modules.',
            'pw_module_info' => 'Get detailed module info.',
            'pw_module_install' => 'Install a module by class name.',
            'pw_module_uninstall' => 'Uninstall a module by class name.',
            'pw_module_refresh' => 'Refresh modules.',
            'pw_module_enable' => 'Enable (install) a module.',
            'pw_module_disable' => 'Disable (uninstall) a module.',
            'pw_module_upgrade' => 'Attempt to upgrade a module.',
            'pw_access_user_create' => 'Create a new user.',
            'pw_access_user_update' => 'Update a user (email/pass/roles).',
            'pw_access_user_delete' => 'Delete a user.',
            'pw_access_user_list' => 'List users.',
            'pw_access_role_create' => 'Create a new role.',
            'pw_access_role_grant' => 'Grant permission to a role.',
            'pw_access_role_revoke' => 'Revoke permission from a role.',
            'pw_permission_delete' => 'Delete a custom permission.',
            'pw_system_get_logs' => 'Retrieve system logs.',
            'pw_system_cache_clear' => 'Clear system caches.',
            'pw_system_backup' => 'Create a database backup.',
            'pw_system_backup_list' => 'List database backups.',
            'pw_system_backup_purge' => 'Purge old database backups.',
            'pw_system_cache_wire_clear' => 'Clear WireCache by key/pattern.',
            'pw_system_logs_clear' => 'Clear a log file.',
            'pw_system_restore' => 'Restore database from allowed backups directory (guarded).',
            'pw_system_logs_tail_last' => 'Return last N lines from a log file.',
        ];
    }

    private function handleRequest(array $request): void
    {
        $method = $request['method'] ?? '';
        $id = $request['id'] ?? null;
        switch ($method) {
            case 'initialize':
                $this->sendResponse($id, [
                    'protocolVersion' => '2024-11-05',
                    'capabilities' => ['tools' => (object)[]],
                    'serverInfo' => ['name' => 'processwire-boost', 'version' => '1.0.0']
                ]);
                break;
            case 'tools/list':
                $toolList = [];
                foreach ($this->tools as $name => $desc) {
                    $toolList[] = [
                        'name' => $name,
                        'description' => $desc,
                        'inputSchema' => ['type' => 'object', 'properties' => (object)[]]
                    ];
                }
                $this->sendResponse($id, ['tools' => $toolList]);
                break;
            case 'tools/call':
                $toolName = $request['params']['name'] ?? '';
                $args = $request['params']['arguments'] ?? [];
                $result = $this->callTool($toolName, $args);
                $this->sendResponse($id, $result);
                break;
            default:
                if ($id) $this->sendError($id, -32601, 'Method not found');
        }
    }

    private function callTool(string $name, array $args): array
    {
        try {
            switch ($name) {
                case 'pw_query':
                    $selector = $args['selector'] ?? 'id>0';
                    $pages = \ProcessWire\wire('pages')->find($selector);
                    $data = [];
                    foreach ($pages as $p) {
                        $data[] = ['id' => $p->id, 'name' => $p->name, 'path' => $p->path, 'template' => $p->template->name];
                    }
                    return ['content' => [['type' => 'text', 'text' => json_encode($data, JSON_PRETTY_PRINT)]]];
                case 'pw_execute':
                    $allow = getenv('PW_MCP_ALLOW_EXECUTE') === '1';
                    if (!$allow) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "pw_execute is disabled. Set PW_MCP_ALLOW_EXECUTE=1 to enable."]]];
                    }
                    $code = $args['code'] ?? '';
                    if (!$code) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Missing 'code' argument."]]];
                    }
                    ob_start();
                    $result = eval($code);
                    $out = trim((string)ob_get_clean());
                    $payload = ['result' => $result, 'output' => $out];
                    return ['content' => [['type' => 'text', 'text' => json_encode($payload, JSON_PRETTY_PRINT)]]];
                case 'pw_schema_read':
                    $fields = \ProcessWire\wire('fields');
                    $templates = \ProcessWire\wire('templates');
                    $schema = [
                        'fields' => [],
                        'templates' => [],
                    ];
                    foreach ($fields as $f) {
                        $schema['fields'][] = ['name' => $f->name, 'type' => $f->type?->className(), 'label' => (string)$f->label];
                    }
                    foreach ($templates as $t) {
                        $names = [];
                        foreach ($t->fieldgroup as $fgf) $names[] = $fgf->name;
                        $schema['templates'][] = ['name' => $t->name, 'fields' => $names];
                    }
                    return ['content' => [['type' => 'text', 'text' => json_encode($schema, JSON_PRETTY_PRINT)]]];
                case 'pw_schema_field_create':
                    $type = $args['type'] ?? '';
                    $nameArg = $args['name'] ?? '';
                    if (!$type || !$nameArg) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Required args: type, name"]]];
                    }
                    $fieldsApi = \ProcessWire\wire('fields');
                    if ($fieldsApi->get($nameArg)->id) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Field '{$nameArg}' already exists."]]];
                    }
                    $pwType = "Fieldtype" . ucfirst($type);
                    if (!\ProcessWire\wire('modules')->get($pwType)) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Field type '{$pwType}' not installed."]]];
                    }
                    $f = new \ProcessWire\Field();
                    $f->type = \ProcessWire\wire('modules')->get($pwType);
                    $f->name = $nameArg;
                    $f->label = ucfirst($nameArg);
                    $f->save();
                    return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'field' => $nameArg, 'type' => $type], JSON_PRETTY_PRINT)]]];
                case 'pw_schema_template_create':
                    $tplName = $args['name'] ?? '';
                    if (!$tplName) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Required arg: name"]]];
                    }
                    if (\ProcessWire\wire('templates')->get($tplName)->id) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Template '{$tplName}' already exists."]]];
                    }
                    $t = new \ProcessWire\Template();
                    $t->name = $tplName;
                    $t->fieldgroup = \ProcessWire\wire('fieldgroups')->get('default');
                    $t->save();
                    return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'template' => $tplName], JSON_PRETTY_PRINT)]]];
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
                    return ['content' => [['type' => 'text', 'text' => json_encode($list, JSON_PRETTY_PRINT)]]];
                case 'pw_module_info':
                    $modName = $args['name'] ?? '';
                    if (!$modName) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Required arg: name"]]];
                    }
                    $modules = \ProcessWire\wire('modules');
                    $info = $modules->getModuleInfo($modName);
                    if (!$info) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Module not found: {$modName}"]]];
                    }
                    $payload = $info;
                    $payload['version'] = $modules->formatVersion($info['version'] ?? 0);
                    return ['content' => [['type' => 'text', 'text' => json_encode($payload, JSON_PRETTY_PRINT)]]];
                case 'pw_module_install':
                    $modName = $args['name'] ?? '';
                    if (!$modName) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Required arg: name"]]];
                    }
                    $modules = \ProcessWire\wire('modules');
                    if ($modules->isInstalled($modName)) {
                        return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'installed' => true, 'note' => 'already'], JSON_PRETTY_PRINT)]]];
                    }
                    $modules->install($modName);
                    return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'installed' => true], JSON_PRETTY_PRINT)]]];
                case 'pw_module_uninstall':
                    $modName = $args['name'] ?? '';
                    if (!$modName) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Required arg: name"]]];
                    }
                    $modules = \ProcessWire\wire('modules');
                    if (!$modules->isInstalled($modName)) {
                        return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'uninstalled' => true, 'note' => 'already'], JSON_PRETTY_PRINT)]]];
                    }
                    $modules->uninstall($modName);
                    return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'uninstalled' => true], JSON_PRETTY_PRINT)]]];
                case 'pw_module_refresh':
                    \ProcessWire\wire('modules')->refresh();
                    return ['content' => [['type' => 'text', 'text' => "Modules refreshed."]]];
                case 'pw_module_enable':
                    $modName = $args['name'] ?? '';
                    if (!$modName) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Required arg: name"]]];
                    }
                    $modules = \ProcessWire\wire('modules');
                    if ($modules->isInstalled($modName)) {
                        return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'enabled' => true, 'note' => 'already'], JSON_PRETTY_PRINT)]]];
                    }
                    $modules->install($modName);
                    return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'enabled' => true], JSON_PRETTY_PRINT)]]];
                case 'pw_module_disable':
                    $modName = $args['name'] ?? '';
                    if (!$modName) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Required arg: name"]]];
                    }
                    $modules = \ProcessWire\wire('modules');
                    if (!$modules->isInstalled($modName)) {
                        return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'disabled' => true, 'note' => 'already'], JSON_PRETTY_PRINT)]]];
                    }
                    $modules->uninstall($modName);
                    return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'disabled' => true], JSON_PRETTY_PRINT)]]];
                case 'pw_module_upgrade':
                    $modName = $args['name'] ?? '';
                    if (!$modName) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Required arg: name"]]];
                    }
                    $modules = \ProcessWire\wire('modules');
                    $modules->refresh();
                    if ($modules->isInstalled($modName)) {
                        try { $modules->install($modName); } catch (\Throwable $e) { /* ignore */ }
                    }
                    return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'upgraded' => $modules->isInstalled($modName)], JSON_PRETTY_PRINT)]]];
                case 'pw_access_user_create':
                    $uname = $args['name'] ?? '';
                    $email = $args['email'] ?? '';
                    $pass = $args['password'] ?? '';
                    $rolesIn = $args['roles'] ?? [];
                    if (!$uname || !$pass) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Required args: name, password (email optional)"]]];
                    }
                    $users = \ProcessWire\wire('users');
                    if ($users->get($uname)->id) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "User '{$uname}' already exists."]]];
                    }
                    $u = new \ProcessWire\User();
                    $u->name = $uname;
                    if ($email) $u->email = $email;
                    $u->pass = $pass;
                    $u->save();
                    if (is_array($rolesIn)) {
                        $rolesApi = \ProcessWire\wire('roles');
                        foreach ($rolesIn as $r) {
                            $role = $rolesApi->get((string)$r);
                            if ($role && $role->id) $u->addRole($role);
                        }
                        $u->save();
                    }
                    return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'userId' => $u->id], JSON_PRETTY_PRINT)]]];
                case 'pw_access_role_create':
                    $rname = $args['name'] ?? '';
                    if (!$rname) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Required arg: name"]]];
                    }
                    $roles = \ProcessWire\wire('roles');
                    if ($roles->get($rname)->id) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Role '{$rname}' already exists."]]];
                    }
                    $role = new \ProcessWire\Role();
                    $role->name = $rname;
                    $role->save();
                    return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'roleId' => $role->id], JSON_PRETTY_PRINT)]]];
                case 'pw_access_user_update':
                    $id = $args['id'] ?? null;
                    $uname = $args['name'] ?? '';
                    $email = $args['email'] ?? '';
                    $pass = $args['password'] ?? '';
                    $addRoles = $args['addRoles'] ?? [];
                    $removeRoles = $args['removeRoles'] ?? [];
                    if (!$id && !$uname) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Provide 'id' or 'name'."]]];
                    }
                    $users = \ProcessWire\wire('users');
                    $rolesApi = \ProcessWire\wire('roles');
                    $user = $id ? $users->get((int)$id) : $users->get((string)$uname);
                    if (!$user || !$user->id) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "User not found."]]];
                    }
                    if ($email) $user->email = $email;
                    if ($pass) $user->pass = $pass;
                    if (is_array($addRoles)) {
                        foreach ($addRoles as $r) {
                            $role = $rolesApi->get((string)$r);
                            if ($role && $role->id && !$user->hasRole($role)) $user->addRole($role);
                        }
                    }
                    if (is_array($removeRoles)) {
                        foreach ($removeRoles as $r) {
                            $role = $rolesApi->get((string)$r);
                            if ($role && $role->id && $user->hasRole($role)) $user->removeRole($role);
                        }
                    }
                    $user->save();
                    return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'userId' => $user->id], JSON_PRETTY_PRINT)]]];
                case 'pw_access_user_delete':
                    $id = $args['id'] ?? null;
                    $uname = $args['name'] ?? '';
                    if (!$id && !$uname) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Provide 'id' or 'name'."]]];
                    }
                    $users = \ProcessWire\wire('users');
                    $user = $id ? $users->get((int)$id) : $users->get((string)$uname);
                    if (!$user || !$user->id) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "User not found."]]];
                    }
                    if ($user->isSuperuser()) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Refusing to delete a superuser."]]];
                    }
                    $users->delete($user);
                    return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'deleted' => true], JSON_PRETTY_PRINT)]]];
                case 'pw_access_user_list':
                    $search = $args['search'] ?? '';
                    $roleFilter = $args['role'] ?? '';
                    $limit = (int)($args['limit'] ?? 50);
                    $selector = [];
                    if ($search) $selector[] = "name%=$search";
                    if ($roleFilter) $selector[] = "roles=$roleFilter";
                    if ($limit) $selector[] = "limit=$limit";
                    if (!$selector) $selector[] = "limit=$limit";
                    $users = \ProcessWire\wire('users')->find(implode(', ', $selector));
                    $list = [];
                    foreach ($users as $u) {
                        $roles = [];
                        foreach ($u->roles as $r) $roles[] = $r->name;
                        $list[] = ['id' => $u->id, 'name' => $u->name, 'email' => (string)$u->email, 'roles' => $roles];
                    }
                    return ['content' => [['type' => 'text', 'text' => json_encode($list, JSON_PRETTY_PRINT)]]];
                case 'pw_access_role_grant':
                    $roleName = $args['role'] ?? '';
                    $permName = $args['permission'] ?? '';
                    if (!$roleName || !$permName) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Args required: role, permission"]]];
                    }
                    $roles = \ProcessWire\wire('roles');
                    $permissions = \ProcessWire\wire('permissions');
                    $role = $roles->get($roleName);
                    $permission = $permissions->get($permName);
                    if (!$role || !$role->id || !$permission || !$permission->id) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Role or permission not found."]]];
                    }
                    if (!$role->hasPermission($permission)) {
                        $role->addPermission($permission);
                        $role->save();
                    }
                    return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'granted' => true], JSON_PRETTY_PRINT)]]];
                case 'pw_access_role_revoke':
                    $roleName = $args['role'] ?? '';
                    $permName = $args['permission'] ?? '';
                    if (!$roleName || !$permName) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Args required: role, permission"]]];
                    }
                    $roles = \ProcessWire\wire('roles');
                    $permissions = \ProcessWire\wire('permissions');
                    $role = $roles->get($roleName);
                    $permission = $permissions->get($permName);
                    if (!$role || !$role->id || !$permission || !$permission->id) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Role or permission not found."]]];
                    }
                    if ($role->hasPermission($permission)) {
                        $role->removePermission($permission);
                        $role->save();
                    }
                    return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'revoked' => true], JSON_PRETTY_PRINT)]]];
                case 'pw_permission_delete':
                    $permName = $args['name'] ?? '';
                    if (!$permName) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Arg required: name"]]];
                    }
                    $permissions = \ProcessWire\wire('permissions');
                    $perm = $permissions->get($permName);
                    if (!$perm || !$perm->id) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Permission not found."]]];
                    }
                    $permissions->delete($perm);
                    return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'deleted' => true], JSON_PRETTY_PRINT)]]];
                case 'pw_system_logs_clear':
                    $name = $args['name'] ?? 'errors';
                    $path = \ProcessWire\wire('config')->paths->logs . $name . '.txt';
                    if (!is_file($path)) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Log not found."]]];
                    }
                    @file_put_contents($path, '');
                    return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'cleared' => $name], JSON_PRETTY_PRINT)]]];
                case 'pw_system_backup_list':
                    $dir = \ProcessWire\wire('config')->paths->assets . 'backups/database/';
                    if (!is_dir($dir)) {
                        return ['content' => [['type' => 'text', 'text' => json_encode([], JSON_PRETTY_PRINT)]]];
                    }
                    $files = array_values(array_filter(scandir($dir) ?: [], fn($f) => $f !== '.' && $f !== '..' && is_file($dir . $f)));
                    usort($files, fn($a, $b) => filemtime($dir . $b) <=> filemtime($dir . $a));
                    return ['content' => [['type' => 'text', 'text' => json_encode($files, JSON_PRETTY_PRINT)]]];
                case 'pw_system_backup_purge':
                    $days = (int)($args['days'] ?? 30);
                    $threshold = time() - ($days * 86400);
                    $dir = \ProcessWire\wire('config')->paths->assets . 'backups/database/';
                    if (!is_dir($dir)) {
                        return ['content' => [['type' => 'text', 'text' => json_encode(['deleted' => 0], JSON_PRETTY_PRINT)]]];
                    }
                    $deleted = 0;
                    foreach (scandir($dir) ?: [] as $f) {
                        if ($f === '.' || $f === '..') continue;
                        $path = $dir . $f;
                        if (is_file($path) && filemtime($path) < $threshold) {
                            if (@unlink($path)) $deleted++;
                        }
                    }
                    return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'deleted' => $deleted], JSON_PRETTY_PRINT)]]];
                case 'pw_system_restore':
                    $allow = getenv('PW_MCP_ALLOW_RESTORE') === '1';
                    if (!$allow) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "pw_system_restore disabled. Set PW_MCP_ALLOW_RESTORE=1 to enable."]]];
                    }
                    $file = $args['file'] ?? '';
                    if (!$file) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Required arg: file"]]];
                    }
                    $assetsDir = \ProcessWire\wire('config')->paths->assets . 'backups/database/';
                    $real = realpath($file);
                    if (!$real || !str_starts_with($real, $assetsDir) || !is_file($real) || !is_readable($real)) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "File not permitted: must reside under backups/database and be readable."]]];
                    }
                    if (!str_ends_with(strtolower($real), '.sql')) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Only .sql files are accepted."]]];
                    }
                    $sql = file_get_contents($real) ?: '';
                    if ($sql === '') {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "SQL file is empty."]]];
                    }
                    $db = \ProcessWire\wire('database');
                    try {
                        $db->beginTransaction();
                        $statements = preg_split('/;\s*(\r?\n)+/', $sql);
                        foreach ($statements as $stmt) {
                            $stmt = trim($stmt);
                            if ($stmt === '' || str_starts_with($stmt, '--') || str_starts_with($stmt, '/*')) continue;
                            $db->exec($stmt);
                        }
                        $db->commit();
                    } catch (\Throwable $e) {
                        if ($db->inTransaction()) $db->rollBack();
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Restore failed: " . $e->getMessage()]]];
                    }
                    return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'restored' => basename($real)], JSON_PRETTY_PRINT)]]];
                case 'pw_system_cache_wire_clear':
                    $key = $args['key'] ?? '';
                    $pattern = $args['pattern'] ?? '';
                    if (!$key && !$pattern) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Provide 'key' or 'pattern'."]]];
                    }
                    if ($key) {
                        \ProcessWire\wire('cache')->delete($key);
                        return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'deleted' => 1], JSON_PRETTY_PRINT)]]];
                    }
                    $db = \ProcessWire\wire('database');
                    $table = $db->quoteIdentifier('caches');
                    $stmt = $db->prepare("DELETE FROM {$table} WHERE name LIKE :p");
                    $stmt->bindValue(':p', $pattern);
                    $stmt->execute();
                    return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'deleted' => $stmt->rowCount()], JSON_PRETTY_PRINT)]]];
                case 'pw_system_get_logs':
                    $logName = $args['name'] ?? 'errors';
                    $limit = $args['limit'] ?? 10;
                    $entries = \ProcessWire\wire('log')->get($logName, (int)$limit);
                    $text = "";
                    foreach ($entries as $e) {
                        $text .= "[{$e['date']}] {$e['text']}\n";
                    }
                    return ['content' => [['type' => 'text', 'text' => $text ?: "No entries found."]]];
                case 'pw_system_logs_tail_last':
                    $name = $args['name'] ?? 'errors';
                    $lines = (int)($args['lines'] ?? 200);
                    $path = \ProcessWire\wire('config')->paths->logs . $name . '.txt';
                    if (!is_file($path)) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Log not found."]]];
                    }
                    $content = @file($path, FILE_IGNORE_NEW_LINES);
                    if ($content === false) $content = [];
                    $slice = array_slice($content, -$lines);
                    $text = implode("\n", $slice);
                    return ['content' => [['type' => 'text', 'text' => $text]]];
                case 'pw_system_cache_clear':
                    \ProcessWire\wire('modules')->refresh();
                    return ['content' => [['type' => 'text', 'text' => "Caches cleared and modules refreshed."]]];
                case 'pw_system_backup':
                    $backup = \ProcessWire\wire('database')->backups();
                    if (!$backup) {
                        return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Backup tool not available."]]];
                    }
                    $file = $backup->backup();
                    if ($file) {
                        return ['content' => [['type' => 'text', 'text' => json_encode(['ok' => true, 'file' => $file], JSON_PRETTY_PRINT)]]];
                    }
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Backup failed."]]];
                default:
                    return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Tool '{$name}' not yet implemented in native MCP."]]];
            }
        } catch (\Throwable $e) {
            return ['isError' => true, 'content' => [['type' => 'text', 'text' => "Error: " . $e->getMessage()]]];
        }
    }

    private function sendResponse($id, array $result): void
    {
        $response = ['jsonrpc' => '2.0','id' => $id,'result' => $result];
        fwrite(STDOUT, json_encode($response) . PHP_EOL);
    }

    private function sendError($id, int $code, string $message): void
    {
        $response = ['jsonrpc' => '2.0','id' => $id,'error' => ['code' => $code, 'message' => $message]];
        fwrite(STDOUT, json_encode($response) . PHP_EOL);
    }
}

<?php

namespace App\Controllers;

/**
 * System administration — the ZKBioTime "System" module beyond the admin
 * list: roles & permissions, security policy, alerts & integrations,
 * scheduled import/export tasks, database backups, API access.
 *
 * Admin-only (routes wrapped in role:admin).
 */
class System extends BaseController
{
    private const TABS = [
        'roles'        => 'Roles & Permissions',
        'security'     => 'Security Policy',
        'integrations' => 'Alerts & Integrations',
        'tasks'        => 'Import / Export Tasks',
        'backups'      => 'Database Backups',
        'api'          => 'API Access',
    ];

    /** module-enter permission codename => friendly label */
    private const MODULES = [
        'enter_personnel_module'  => 'Personnel',
        'enter_terminal_module'   => 'Devices',
        'enter_attendance_module' => 'Attendance',
        'enter_payroll_module'    => 'Payroll',
        'enter_access_module'     => 'Access Control',
        'enter_visitor_module'    => 'Visitors',
        'enter_meeting_module'    => 'Meetings',
        'enter_ep_module'         => 'Temperature (EP)',
        'enter_system_module'     => 'System',
    ];

    public function index(string $tab = 'roles'): string
    {
        if (! isset(self::TABS[$tab])) {
            $tab = 'roles';
        }
        $db = db_connect();
        $d  = ['title' => 'System', 'subtitle' => self::TABS[$tab], 'active' => 'system', 'tab' => $tab, 'tabs' => self::TABS];

        switch ($tab) {
            case 'roles':
                $d['modules'] = self::MODULES;
                $d['roles']   = $db->query(
                    "SELECT g.id, g.name,
                            (SELECT count(*) FROM auth_user_groups ug WHERE ug.group_id = g.id) AS members,
                            (SELECT string_agg(p.codename,',') FROM auth_group_permissions gp JOIN auth_permission p ON p.id = gp.permission_id WHERE gp.group_id = g.id) AS perms
                       FROM auth_group g ORDER BY g.name"
                )->getResultArray();
                foreach ($d['roles'] as &$r) {
                    $r['tier'] = $this->setting('grouptier_' . $r['id'], 'viewer');
                }
                unset($r);
                $d['admins'] = $db->query(
                    "SELECT u.id, u.username, u.is_superuser, u.is_staff,
                            NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),'') AS name,
                            (SELECT string_agg(g.name,', ') FROM auth_user_groups ug JOIN auth_group g ON g.id = ug.group_id WHERE ug.myuser_id = u.id) AS groups
                       FROM auth_user u WHERE COALESCE(u.del_flag,0) = 0 ORDER BY u.id"
                )->getResultArray();
                $d['groupOpts'] = $db->query('SELECT id, name FROM auth_group ORDER BY name')->getResultArray();
                break;

            case 'security':
                $d['row'] = $db->query('SELECT * FROM base_securitypolicy ORDER BY id LIMIT 1')->getRowArray() ?: [];
                break;

            case 'integrations':
                $d['zoom']   = $db->query('SELECT * FROM base_zoomsetting ORDER BY id LIMIT 1')->getRowArray() ?: [];
                $d['events'] = $db->query('SELECT * FROM base_eventalertsetting ORDER BY id')->getResultArray();
                break;

            case 'tasks':
                $d['imports'] = $db->query("SELECT *, 'import' AS kind FROM base_autoimporttask ORDER BY id")->getResultArray();
                $d['exports'] = $db->query("SELECT *, 'export' AS kind FROM base_autoexporttask ORDER BY id")->getResultArray();
                $d['companies'] = $db->query('SELECT id, company_name FROM personnel_company ORDER BY is_default DESC')->getResultArray();
                break;

            case 'backups':
                $b = $db->table('base_dbbackuplog')->select('*')->orderBy('backup_time', 'DESC');
                [$d['rows'], $d['pager']] = $this->paged($b, 25, 'bk');
                break;

            case 'api':
                $d['endpoints'] = $db->query(
                    "SELECT e.*, (SELECT count(*) FROM base_apipermission p WHERE p.endpoint_id = e.id) AS grants
                       FROM base_apiendpoint e ORDER BY e.module, e.path"
                )->getResultArray();
                $d['grants'] = $db->query(
                    "SELECT p.id, e.name AS endpoint, e.method, u.username
                       FROM base_apipermission p JOIN base_apiendpoint e ON e.id = p.endpoint_id
                       JOIN auth_user u ON u.id = p.user_id ORDER BY u.username, e.name"
                )->getResultArray();
                $d['users'] = $db->query("SELECT id, username FROM auth_user WHERE is_active = true ORDER BY username")->getResultArray();
                break;
        }

        return view('admin/sysadmin/index', $d);
    }

    // ------------------------------------------------------------- roles

    public function saveRole()
    {
        if (! $this->validate(['name' => 'required|max_length[150]'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'roleForm');
        }
        $p     = $this->request;
        $id    = (int) $p->getPost('id');
        $tier  = in_array($p->getPost('tier'), ['admin', 'manager', 'viewer'], true) ? $p->getPost('tier') : 'viewer';
        $perms = array_values(array_intersect((array) $p->getPost('modules'), array_keys(self::MODULES)));

        $ok = $this->tx(function ($db) use ($p, $perms, &$id): void {
            if ($id) {
                $db->table('auth_group')->where('id', $id)->update(['name' => trim($p->getPost('name'))]);
            } else {
                $db->table('auth_group')->insert(['name' => trim($p->getPost('name'))]);
                $id = (int) $db->insertID();
            }
            $db->table('auth_group_permissions')->where('group_id', $id)->delete();
            if ($perms !== []) {
                $ids = $db->table('auth_permission')->select('id')->whereIn('codename', $perms)->get()->getResultArray();
                foreach ($ids as $row) {
                    $db->table('auth_group_permissions')->insert(['group_id' => $id, 'permission_id' => (int) $row['id']]);
                }
            }
        });
        if (! $ok) {
            return redirect()->back()->with('error', 'Role could not be saved — the name may already exist.');
        }
        $this->setSetting('grouptier_' . $id, $tier);
        $this->audit($id ? 'system.role.update' : 'system.role.create', $p->getPost('name'));

        return redirect()->to(route_to('system.tab', 'roles'))->with('success', 'Role saved.');
    }

    public function deleteRole(int $id)
    {
        $this->tx(static function ($db) use ($id): void {
            $db->table('auth_group_permissions')->where('group_id', $id)->delete();
            $db->table('auth_user_groups')->where('group_id', $id)->delete();
            $db->table('auth_group')->where('id', $id)->delete();
        });
        $this->audit('system.role.delete', (string) $id);

        return redirect()->to(route_to('system.tab', 'roles'))->with('success', 'Role deleted.');
    }

    public function assignRole()
    {
        $p    = $this->request;
        $uid  = (int) $p->getPost('user_id');
        $gids = array_filter(array_map('intval', (array) $p->getPost('group_id')));
        if ($uid <= 0) {
            return redirect()->back();
        }
        $this->tx(static function ($db) use ($uid, $gids): void {
            $db->table('auth_user_groups')->where('myuser_id', $uid)->delete();
            foreach ($gids as $g) {
                $db->table('auth_user_groups')->insert(['myuser_id' => $uid, 'group_id' => $g]);
            }
        });
        $this->audit('system.role.assign', (string) $uid, count($gids) . ' roles');

        return redirect()->to(route_to('system.tab', 'roles'))->with('success', 'Roles updated. The admin sees the change on next sign-in.');
    }

    // ---------------------------------------------------------- security

    public function saveSecurity()
    {
        $p    = $this->request;
        $data = [
            'single_login'          => (bool) $p->getPost('single_login'),
            'failed_locked'         => (bool) $p->getPost('failed_locked'),
            'lock_failed_count'     => max(3, (int) $p->getPost('lock_failed_count')),
            'lock_duration'         => max(1, (int) $p->getPost('lock_duration')),
            'session_timeout'       => max(5, (int) $p->getPost('session_timeout')),
            'password_level'        => (int) $p->getPost('password_level'),
            'enforce_pwd_expiration' => (bool) $p->getPost('enforce_pwd_expiration'),
            'validity_period'       => max(1, (int) $p->getPost('validity_period')),
        ];
        $db  = db_connect();
        $row = $db->table('base_securitypolicy')->orderBy('id')->get(1)->getRowArray();
        if (! $row) {
            return redirect()->back()->with('error', 'No security policy row.');
        }
        $this->dbGuard(static fn () => $db->table('base_securitypolicy')->where('id', $row['id'])->update($data));
        $this->audit('system.security.update', 'security policy');

        return redirect()->to(route_to('system.tab', 'security'))->with('success', 'Security policy saved.');
    }

    // ------------------------------------------------------- integrations

    public function saveZoom()
    {
        $p    = $this->request;
        $data = [
            'jwt_token'       => trim((string) $p->getPost('jwt_token')) ?: '-',
            'zoom_user_email' => trim((string) $p->getPost('zoom_user_email')) ?: '-',
            'zoom_enable'     => (bool) $p->getPost('zoom_enable'),
        ];
        $db  = db_connect();
        $row = $db->table('base_zoomsetting')->orderBy('id')->get(1)->getRowArray();
        $ok  = $this->dbGuard(static fn () => $row
            ? $db->table('base_zoomsetting')->where('id', $row['id'])->update($data)
            : $db->table('base_zoomsetting')->insert($data));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Zoom settings could not be saved.');
        }
        $this->audit('system.zoom.update', 'zoom');

        return redirect()->to(route_to('system.tab', 'integrations'))->with('success', 'Zoom settings saved.');
    }

    public function saveEvents()
    {
        $p     = $this->request;
        $email = array_flip(array_map('intval', (array) $p->getPost('email')));
        $app   = array_flip(array_map('intval', (array) $p->getPost('app')));
        $db    = db_connect();
        $this->dbGuard(static function () use ($db, $email, $app): void {
            foreach ($db->table('base_eventalertsetting')->get()->getResultArray() as $r) {
                $db->table('base_eventalertsetting')->where('id', $r['id'])->update([
                    'email_alert' => isset($email[(int) $r['id']]),
                    'app_alert'   => isset($app[(int) $r['id']]),
                ]);
            }
        });
        $this->audit('system.events.update', 'event alerts');

        return redirect()->to(route_to('system.tab', 'integrations'))->with('success', 'Alert routing saved.');
    }

    // -------------------------------------------------------------- tasks

    public function saveTask(string $kind)
    {
        $table = $kind === 'import' ? 'base_autoimporttask' : 'base_autoexporttask';
        if (! $this->validate(['task_code' => 'required|max_length[30]', 'task_name' => 'required|max_length[30]'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }
        $p    = $this->request;
        $id   = (int) $p->getPost('id');
        $data = [
            'task_code' => trim($p->getPost('task_code')),
            'task_name' => trim($p->getPost('task_name')),
            'params'    => $p->getPost('params') ?: null,
            'enable'    => (bool) $p->getPost('enable'),
        ];
        $extra = $this->stamp();
        if ($kind === 'import') {
            $extra['company_id'] = (int) $p->getPost('company_id') ?: 1;
        }
        $rid = $this->saveRow($table, $data, $id, $extra);
        if ($rid === null) {
            return redirect()->back()->with('error', 'Task could not be saved — the code must be unique.');
        }
        $this->audit($id ? 'system.task.update' : 'system.task.create', $kind . ':' . $data['task_code']);

        return redirect()->to(route_to('system.tab', 'tasks'))->with('success', 'Task saved.');
    }

    public function deleteTask(string $kind, int $id)
    {
        $this->deleteRow($kind === 'import' ? 'base_autoimporttask' : 'base_autoexporttask', $id);
        $this->audit('system.task.delete', $kind . ':' . $id);

        return redirect()->to(route_to('system.tab', 'tasks'))->with('success', 'Task deleted.');
    }

    // ------------------------------------------------------------ backups

    public function runBackup()
    {
        $db   = db_connect();
        $name = 'timetrack_' . date('Ymd_His') . '.sql';
        $this->dbGuard(static fn () => $db->table('base_dbbackuplog')->insert([
            'status' => 0, 'db_type' => 'postgresql', 'db_name' => $db->getDatabase(),
            'operator' => session()->get('userName'), 'backup_file' => 'files/backup/' . $name,
            'backup_time' => date('Y-m-d H:i:sP'), 'backup_status' => 0,
            'remark' => 'Manual backup requested from TimeTrack',
            'create_time' => date('Y-m-d H:i:sP'),
        ]));
        $this->audit('system.backup.run', $name);

        return redirect()->to(route_to('system.tab', 'backups'))
            ->with('success', 'Backup logged. Run pg_dump on the server to write ' . $name . '.');
    }

    // -------------------------------------------------------------- api

    public function saveEndpoint()
    {
        if (! $this->validate(['name' => 'required|max_length[50]', 'path' => 'required|max_length[255]', 'method' => 'required'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'epForm');
        }
        $p    = $this->request;
        $id   = (int) $p->getPost('id');
        $data = [
            'name'        => trim($p->getPost('name')),
            'path'        => trim($p->getPost('path')),
            'method'      => strtoupper((string) $p->getPost('method')),
            'module'      => $p->getPost('module') ?: 'general',
            'description' => $p->getPost('description') ?: null,
            'is_active'   => $p->getPost('id') ? (bool) $p->getPost('is_active') : true,
            'updated_at'  => date('Y-m-d H:i:sP'),
        ];
        $rid = $this->saveRow('base_apiendpoint', $data, $id, ['created_at' => date('Y-m-d H:i:sP')]);
        if ($rid === null) {
            return redirect()->back()->with('error', 'Endpoint could not be saved — path + method must be unique.');
        }
        $this->audit($id ? 'system.api.update' : 'system.api.create', $data['path']);

        return redirect()->to(route_to('system.tab', 'api'))->with('success', 'API endpoint saved.');
    }

    public function deleteEndpoint(int $id)
    {
        $this->tx(static function ($db) use ($id): void {
            $db->table('base_apipermission')->where('endpoint_id', $id)->delete();
            $db->table('base_apiendpoint')->where('id', $id)->delete();
        });
        $this->audit('system.api.delete', (string) $id);

        return redirect()->to(route_to('system.tab', 'api'))->with('success', 'Endpoint deleted.');
    }

    public function grantApi()
    {
        $p   = $this->request;
        $eid = (int) $p->getPost('endpoint_id');
        $uid = (int) $p->getPost('user_id');
        if ($eid <= 0 || $uid <= 0) {
            return redirect()->back();
        }
        $db  = db_connect();
        $has = $db->table('base_apipermission')->where('endpoint_id', $eid)->where('user_id', $uid)->countAllResults();
        if (! $has) {
            $this->dbGuard(static fn () => $db->table('base_apipermission')->insert([
                'endpoint_id' => $eid, 'user_id' => $uid, 'is_active' => true,
                'created_at' => date('Y-m-d H:i:sP'), 'updated_at' => date('Y-m-d H:i:sP'),
            ]));
        }
        $this->audit('system.api.grant', "e{$eid} u{$uid}");

        return redirect()->to(route_to('system.tab', 'api'))->with('success', 'API access granted.');
    }

    public function revokeApi(int $id)
    {
        db_connect()->table('base_apipermission')->where('id', $id)->delete();
        $this->audit('system.api.revoke', (string) $id);

        return redirect()->to(route_to('system.tab', 'api'))->with('success', 'API access revoked.');
    }

    // ----------------------------------------------------------- internals

    private function setting(string $name, string $default): string
    {
        $row = db_connect()->table('base_systemsetting')->where('name', $name)->get()->getRowArray();

        return $row['value'] ?? $default;
    }

    private function setSetting(string $name, string $value): void
    {
        $db  = db_connect();
        $has = $db->table('base_systemsetting')->where('name', $name)->countAllResults();
        $row = ['name' => mb_substr($name, 0, 30), 'value' => $value, 'status' => 0, 'change_time' => date('Y-m-d H:i:sP')];
        $this->dbGuard(static fn () => $has
            ? $db->table('base_systemsetting')->where('name', $name)->update($row)
            : $db->table('base_systemsetting')->insert($row + ['create_time' => date('Y-m-d H:i:sP')]));
    }
}

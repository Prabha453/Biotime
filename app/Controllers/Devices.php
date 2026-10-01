<?php

namespace App\Controllers;

/**
 * Device ▸ Device — ZKBioTime's terminal list.
 *
 * A "device" is a row in iclock_terminal (a ZKTeco PUSH-protocol terminal).
 * The list mirrors ZKBio's grid (User / FP / Face / Palm / Transaction / Cmd
 * counts, online state, last activity) and its toolbar: Add · Delete ·
 * New Area · Clear Pending Command · Data Clean · Data Transfer · Device Menu.
 *
 * Every "action" queues a PUSH command in iclock_terminalcommand; the terminal
 * pulls it on its next heartbeat and iclockservice moves it to the command log.
 */
class Devices extends BaseController
{
    /** ZKTeco product_type → label (iclock.Terminal.product_type) */
    private const PRODUCT_TYPES = [
        9 => 'Time attendance', 5 => 'Access controller', 8 => 'Information screen',
        43 => 'Meeting terminal', 31 => 'AI camera',
    ];

    private const TRANSFER_MODES = [1 => 'Real-time', 0 => 'Scheduled'];

    /** Commands grouped exactly like ZKBio's toolbar dropdowns. */
    private const COMMANDS = [
        // Data Clean
        'clear_attlog'   => ['group' => 'clean',    'label' => 'Clear attendance data',    'content' => 'CLEAR DATA'],
        'clear_photo'    => ['group' => 'clean',    'label' => 'Clear attendance photo',   'content' => 'CLEAR PHOTO'],
        'clear_biodata'  => ['group' => 'clean',    'label' => 'Clear all biometric data', 'content' => 'CLEAR DATA PhotoStamp=None'],
        // Data Transfer
        'reupload_user'  => ['group' => 'transfer', 'label' => 'Re-download users to device', 'content' => 'DATA UPDATE USERINFO'],
        'reupload_trans' => ['group' => 'transfer', 'label' => 'Re-upload transactions',      'content' => "DATA QUERY ATTLOG StartTime={from} 00:00:00\tEndTime={to} 23:59:59"],
        'query_userinfo' => ['group' => 'transfer', 'label' => 'Re-upload users from device', 'content' => 'DATA QUERY USERINFO'],
        'query_biodata'  => ['group' => 'transfer', 'label' => 'Re-upload biometric data',    'content' => 'DATA QUERY BIODATA'],
        // Device Menu
        'reboot'         => ['group' => 'menu', 'label' => 'Reboot device',   'content' => 'REBOOT'],
        'info'           => ['group' => 'menu', 'label' => 'Query device info', 'content' => 'INFO'],
        'sync_time'      => ['group' => 'menu', 'label' => 'Sync device time', 'content' => 'SET OPTIONS DateTime={ts}'],
        'clear_cmd'      => ['group' => 'menu', 'label' => 'Clear pending commands', 'content' => 'CLEAR BUFFER'],
    ];

    // ------------------------------------------------------------------ list

    public function index(): string
    {
        $b = $this->listQuery();

        $fbApplied = $this->filterBuilder($b, [
            'alias'         => ['t.alias', 'text'],
            'sn'            => ['t.sn', 'text'],
            'ip_address'    => ['t.ip_address', 'text'],
            'area'          => ['a.area_name', 'text'],
            'fw_ver'        => ['t.fw_ver', 'text'],
            'last_activity' => ['t.last_activity', 'date'],
        ]);

        [$rows, $pager] = $this->paged($b->orderBy('t.alias'), 25);
        $d = [
            'title'     => 'Device',
            'subtitle'  => 'ZKTeco push terminals',
            'active'    => 'devices',
            'rows'      => $rows,
            'pager'     => $pager,
            'fbApplied' => $fbApplied,
        ];

        if ($this->wantsJson()) {
            return view('admin/devices/_list', $d);
        }

        return view('admin/devices/index', $d + [
            'areas'     => db_connect()->query('SELECT id, area_name FROM personnel_area ORDER BY area_name')->getResultArray(),
            'companies' => db_connect()->query('SELECT id, company_name FROM personnel_company ORDER BY is_default DESC, company_name')->getResultArray(),
            'tzList'    => $this->timezones(),
            'modes'     => self::TRANSFER_MODES,
            'commands'  => self::COMMANDS,
        ]);
    }

    private function listQuery()
    {
        return db_connect()->table('iclock_terminal t')
            ->select("t.id, t.alias, t.sn, t.ip_address, t.fw_ver, t.state, t.area_id,
                      t.user_count, t.transaction_count, t.fp_count, t.face_count, t.palm_count, t.fv_count,
                      t.is_attendance, t.is_registration, t.is_access, t.terminal_tz, t.heartbeat,
                      t.transfer_mode, t.transfer_interval, t.transfer_time, t.product_type,
                      a.area_name,
                      to_char(t.last_activity, 'YYYY-MM-DD HH24:MI:SS') AS last_seen,
                      (t.last_activity IS NOT NULL AND now() - t.last_activity < interval '10 minutes') AS online,
                      (SELECT count(*) FROM iclock_terminalcommand c WHERE c.terminal_id = t.id) AS cmd_count")
            ->join('personnel_area a', 'a.id = t.area_id', 'left');
    }

    // ------------------------------------------------------------ add / edit

    public function save(?int $id = null)
    {
        $id = $id ?: (int) $this->request->getPost('id');
        $p  = $this->request;

        $rules = [
            'alias'      => "required|max_length[50]",
            'ip_address' => 'permit_empty|valid_ip',
            'sn'         => "required|max_length[50]|is_unique[iclock_terminal.sn,id,{$id}]",
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors())->with('reopen', 'devForm');
        }

        $data = [
            'alias'             => trim((string) $p->getPost('alias')),
            'sn'                => trim((string) $p->getPost('sn')),
            'ip_address'        => $p->getPost('ip_address') ?: '0.0.0.0',
            'area_id'           => (int) $p->getPost('area_id') ?: null,
            'terminal_tz'       => (int) ($p->getPost('terminal_tz') ?? 330),
            'heartbeat'         => (int) ($p->getPost('heartbeat') ?: 10),
            'transfer_mode'     => (int) $p->getPost('transfer_mode'),
            'transfer_interval' => (int) ($p->getPost('transfer_interval') ?: 1),
            'transfer_time'     => trim((string) $p->getPost('transfer_time')) ?: '00:00;14:05',
            'is_attendance'     => (int) (bool) $p->getPost('is_attendance'),
            'is_registration'   => (int) (bool) $p->getPost('is_registration'),
            'is_access'         => (int) (bool) $p->getPost('is_access'),
        ];

        $db = db_connect();
        $ok = $this->dbGuard(static function () use ($db, $id, $data) {
            if ($id) {
                return $db->table('iclock_terminal')->where('id', $id)->update($data + ['change_time' => date('Y-m-d H:i:sP')]);
            }

            return $db->table('iclock_terminal')->insert($data + [
                'status' => 0, 'state' => 0, 'lock_func' => 0, 'authentication' => 1, 'is_tft' => false,
                'push_protocol' => 'PUSH', 'create_time' => date('Y-m-d H:i:sP'),
            ]);
        });
        if ($ok === null) {
            return redirect()->back()->withInput()->with('error', 'Device could not be saved — the serial number must be unique.');
        }
        $this->audit($id ? 'device.update' : 'device.create', $data['alias'], 'sn=' . $data['sn']);

        return redirect()->to(route_to('devices'))->with('success', $id ? 'Device updated.' : 'Device added.');
    }

    public function bulkDelete()
    {
        $ids = array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
        if (! $ids) {
            return redirect()->back()->with('error', 'Nothing selected.');
        }
        $db = db_connect();
        $this->tx(function ($db) use ($ids) {
            foreach (['iclock_terminalcommand', 'iclock_terminalcommandlog', 'iclock_terminallog',
                'iclock_terminaluploadlog', 'iclock_errorcommandlog', 'iclock_terminalparameter'] as $t) {
                $db->table($t)->whereIn('terminal_id', $ids)->delete();
            }
            $db->table('iclock_transaction')->whereIn('terminal_id', $ids)->update(['terminal_id' => null]);
            $db->table('iclock_terminal')->whereIn('id', $ids)->delete();
        }, 'Could not delete the selected devices.');
        $this->audit('device.bulk_delete', count($ids) . ' devices');

        return redirect()->to(route_to('devices'))->with('success', 'Deleted ' . count($ids) . ' device(s).');
    }

    // ---------------------------------------------------------------- actions

    /** New Area — quick-create a personnel_area without leaving the device list. */
    public function newArea()
    {
        $p = $this->request;
        if (! $this->validate(['area_code' => 'required|max_length[30]', 'area_name' => 'required|max_length[100]'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'devAreaForm');
        }
        $db      = db_connect();
        $company = (int) $p->getPost('company_id') ?: (int) ($db->query('SELECT id FROM personnel_company ORDER BY is_default DESC, id LIMIT 1')->getRow()->id ?? 1);
        $ok = $this->dbGuard(static fn () => $db->table('personnel_area')->insert([
            'area_code'      => trim((string) $p->getPost('area_code')),
            'area_name'      => trim((string) $p->getPost('area_name')),
            'company_id'     => $company,
            'parent_area_id' => (int) $p->getPost('parent_area_id') ?: null,
            'is_default'     => false,
            'employee_count' => 0,
            'device_count'   => 0,
        ]));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Area could not be created — the code must be unique within the company.');
        }
        $this->audit('org.area.create', (string) $p->getPost('area_code'), 'via device page');

        return redirect()->to(route_to('devices'))->with('success', 'Area created.');
    }

    /**
     * Run a grouped command (Data Clean / Data Transfer / Device Menu) against
     * every selected device. ids[] + cmd.
     */
    public function command()
    {
        $p    = $this->request;
        $cmd  = (string) $p->getPost('cmd');
        $spec = self::COMMANDS[$cmd] ?? null;
        if (! $spec) {
            return redirect()->back()->with('error', 'Unknown command.');
        }
        $ids = array_values(array_filter(array_map('intval', (array) $p->getPost('ids'))));
        if (! $ids) {
            return redirect()->back()->with('error', 'Select at least one device.');
        }

        $content = strtr($spec['content'], [
            '{from}' => $p->getPost('from') ?: date('Y-m-d', strtotime('-7 days')),
            '{to}'   => $p->getPost('to') ?: date('Y-m-d'),
            '{ts}'   => (string) (time() - strtotime('2000-01-01 00:00:00')),
        ]);

        $db  = db_connect();
        $now = date('Y-m-d H:i:sP');
        $this->tx(function ($db) use ($cmd, $ids, $content, $now) {
            foreach ($ids as $tid) {
                if ($cmd === 'clear_cmd') {
                    $db->table('iclock_terminalcommand')->where('terminal_id', $tid)->delete();
                }
                $db->table('iclock_terminalcommand')->insert([
                    'content' => $content, 'commit_time' => $now, 'terminal_id' => $tid,
                ]);
            }
        }, 'The command could not be queued.');
        $this->audit('device.command.' . $cmd, count($ids) . ' devices', $content);

        return redirect()->to(route_to('devices'))
            ->with('success', $spec['label'] . ' — queued for ' . count($ids) . ' device(s).');
    }

    /** "Clear Pending Command" toolbar button — wipe the queue for the ticked devices. */
    public function clearPending()
    {
        $ids = array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
        if (! $ids) {
            return redirect()->back()->with('error', 'Nothing selected.');
        }
        $n = db_connect()->table('iclock_terminalcommand')->whereIn('terminal_id', $ids)->delete();
        $this->audit('device.clear_pending', count($ids) . ' devices');

        return redirect()->to(route_to('devices'))->with('success', 'Cleared pending commands for ' . count($ids) . ' device(s).');
    }

    // ------------------------------------------------------------------ view

    public function view(int $id): string
    {
        $db  = db_connect();
        $dev = $db->query(
            "SELECT t.*, a.area_name, to_char(t.last_activity,'YYYY-MM-DD HH24:MI:SS') last_seen,
                    (t.last_activity IS NOT NULL AND now() - t.last_activity < interval '10 minutes') AS online
               FROM iclock_terminal t LEFT JOIN personnel_area a ON a.id = t.area_id
              WHERE t.id = ?",
            [$id]
        )->getRowArray();
        if (! $dev) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('admin/devices/view', [
            'title'       => $dev['alias'],
            'subtitle'    => 'Serial ' . $dev['sn'],
            'active'      => 'devices',
            'dev'         => $dev,
            'params'      => $db->query("SELECT param_name, param_value FROM iclock_terminalparameter WHERE terminal_id = ? ORDER BY param_name", [$id])->getResultArray(),
            'daily'       => $db->query("SELECT to_char(punch_time,'YYYY-MM-DD') d, count(*) c FROM iclock_transaction WHERE terminal_id = ? AND punch_time > now() - interval '14 days' GROUP BY 1 ORDER BY 1", [$id])->getResultArray(),
            'recent'      => $db->query("SELECT to_char(t.punch_time,'YYYY-MM-DD HH24:MI:SS') tm, t.emp_code, t.punch_state, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') name FROM iclock_transaction t LEFT JOIN personnel_employee e ON e.id = t.emp_id WHERE t.terminal_id = ? ORDER BY t.punch_time DESC LIMIT 25", [$id])->getResultArray(),
            'commands'    => $db->query("SELECT content, to_char(commit_time,'MM-DD HH24:MI') ct, return_value, return_time FROM iclock_terminalcommandlog WHERE terminal_id = ? ORDER BY commit_time DESC LIMIT 15", [$id])->getResultArray(),
            'pending'     => $db->query("SELECT id, content, to_char(commit_time,'MM-DD HH24:MI') ct, transfer_time FROM iclock_terminalcommand WHERE terminal_id = ? ORDER BY commit_time DESC", [$id])->getResultArray(),
            'areas'       => $db->query('SELECT id, area_name FROM personnel_area ORDER BY area_name')->getResultArray(),
            'stateLabels' => config('Attendance')->stateLabels,
        ]);
    }

    public function cancelCommand(int $id, int $cmdId)
    {
        db_connect()->table('iclock_terminalcommand')->where('id', $cmdId)->where('terminal_id', $id)->delete();
        $this->audit('device.command.cancel', (string) $cmdId);

        return redirect()->back()->with('success', 'Queued command cancelled.');
    }

    /** Single-device command from the detail page. */
    public function queueCommand(int $id)
    {
        $p    = $this->request;
        $cmd  = (string) $p->getPost('cmd');
        $spec = self::COMMANDS[$cmd] ?? null;
        if (! $spec) {
            return redirect()->back()->with('error', 'Unknown command.');
        }
        $content = strtr($spec['content'], [
            '{from}' => $p->getPost('from') ?: date('Y-m-d', strtotime('-7 days')),
            '{to}'   => $p->getPost('to') ?: date('Y-m-d'),
            '{ts}'   => (string) (time() - strtotime('2000-01-01 00:00:00')),
        ]);
        $db  = db_connect();
        $now = date('Y-m-d H:i:sP');
        if ($cmd === 'clear_cmd') {
            $db->table('iclock_terminalcommand')->where('terminal_id', $id)->delete();
        }
        $this->dbGuard(static fn () => $db->table('iclock_terminalcommand')->insert([
            'content' => $content, 'commit_time' => $now, 'terminal_id' => $id,
        ]));
        $this->audit('device.command.' . $cmd, 'device #' . $id, $content);

        return redirect()->back()->with('success', $spec['label'] . ' — queued.');
    }

    // --------------------------------------------------------------- helpers

    /**
     * ZKTeco terminal_tz codes → label. ZKBio stores whole-hour offsets as the
     * hour (8 → GMT+8) and half-hour offsets as total minutes (330 → GMT+5:30).
     */
    private function timezones(): array
    {
        $tz = [];
        for ($h = -12; $h <= 13; $h++) {
            foreach ([0, 30] as $half) {
                if ($h === 13 && $half) {
                    continue;
                }
                $code  = $half ? ($h * 60 + ($h < 0 ? -$half : $half)) : $h;
                $sign  = $h < 0 ? '-' : '+';
                $tz[$code] = sprintf('GMT%s%d:%02d', $sign, abs($h), $half);
            }
        }
        ksort($tz);

        return $tz;
    }
}

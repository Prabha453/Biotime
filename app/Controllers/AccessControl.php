<?php

namespace App\Controllers;

/**
 * ZKTeco door access control — reads/writes the acc_* tables that the
 * terminal syncs. Access levels group doors + a weekly time zone; a
 * privilege links an employee to a level.
 */
class AccessControl extends BaseController
{
    public const VERIFY_MODES = [
        0  => 'Group default',
        1  => 'Fingerprint',
        3  => 'Password',
        4  => 'Card',
        6  => 'Fingerprint / Password',
        10 => 'Face',
        11 => 'Face / Fingerprint',
    ];

    /** Each Access Control page is its own URL (matching ZKBioTime), sharing this subnav. */
    private const PAGES = [
        'doors'        => ['Device', 'access.doors'],
        'commands'     => ['Device Command', 'access.commands'],
        'timezones'    => ['Time Period', 'access.timezones'],
        'accholidays'  => ['Holiday', 'access.holidays'],
        'levels'       => ['Groups', 'access.levels'],
        'combinations' => ['Combination', 'access.combinations'],
        'privileges'   => ['Privilege', 'access.privileges'],
    ];

    private function areas(): array
    {
        return db_connect()->query('SELECT id, area_name FROM personnel_area ORDER BY area_name')->getResultArray();
    }

    private function timezoneList(): array
    {
        return db_connect()->query(
            "SELECT z.id, z.timezone_no, z.timezone_name, z.remark, z.area_id, a.area_name,
                    z.sun_start, z.sun_end, z.sun_on, z.mon_start, z.mon_end, z.mon_on,
                    z.tue_start, z.tue_end, z.tue_on, z.wed_start, z.wed_end, z.wed_on,
                    z.thu_start, z.thu_end, z.thu_on, z.fri_start, z.fri_end, z.fri_on,
                    z.sat_start, z.sat_end, z.sat_on
               FROM acc_acctimezone z
          LEFT JOIN personnel_area a ON a.id = z.area_id
           ORDER BY z.timezone_no"
        )->getResultArray();
    }

    /** @return array{active:string,pages:array} common view data every Access Control page needs for its subnav */
    private function pageCtx(string $active): array
    {
        return ['active' => 'access', 'tab' => $active, 'pages' => self::PAGES];
    }

    public function index()
    {
        return redirect()->to(route_to('access.levels'));
    }

    // ---- Access levels (acc_accgroups) — "Groups" ----

    public function levels(): string
    {
        $db = db_connect();
        $b = $db->table('acc_accgroups g')
            ->select("g.id, g.group_no, g.group_name, g.verify_mode, g.is_include_holiday,
                      g.timezone1, g.timezone2, g.timezone3, a.area_name,
                      z1.timezone_name AS timezone1_name, z2.timezone_name AS timezone2_name, z3.timezone_name AS timezone3_name,
                      (SELECT count(*) FROM acc_accprivilege p WHERE p.group_id = g.id) AS members")
            ->join('personnel_area a', 'a.id = g.area_id', 'left')
            ->join('acc_acctimezone z1', 'z1.id = g.timezone1', 'left')
            ->join('acc_acctimezone z2', 'z2.id = g.timezone2', 'left')
            ->join('acc_acctimezone z3', 'z3.id = g.timezone3', 'left')
            ->orderBy('g.group_no', 'ASC');
        [$groups, $pager] = $this->paged($b, 20);

        return view('admin/access/levels', $this->pageCtx('levels') + [
            'title'    => 'Groups',
            'subtitle' => 'Access levels — doors (areas) + a weekly time zone',
            'groups'   => $groups,
            'pager'    => $pager,
            'areas'    => $this->areas(),
            'timezones' => $this->timezoneList(),
            'vm'       => self::VERIFY_MODES,
            'nextGroupNo' => (int) ($db->query('SELECT COALESCE(MAX(group_no),0)+1 n FROM acc_accgroups')->getRow()->n),
        ]);
    }

    public function saveLevel()
    {
        $rules = [
            'group_name' => 'required|max_length[50]',
            'group_no'   => 'required|is_natural_no_zero',
            'area_id'    => 'required|is_natural_no_zero',
            'timezone1'  => 'required|is_natural_no_zero',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'lvlNew');
        }

        $p  = $this->request;
        $db = db_connect();
        $id = (int) $p->getPost('id');
        $data = [
            'group_name'        => $p->getPost('group_name'),
            'group_no'          => (int) $p->getPost('group_no'),
            'verify_mode'       => (int) $p->getPost('verify_mode'),
            'is_include_holiday'=> (int) (bool) $p->getPost('is_include_holiday'),
            'area_id'           => (int) $p->getPost('area_id'),
            // ZKBioTime lets a level carry up to 3 weekly time zones (any one
            // matching lets the door open) — timezone1 is the minimum required.
            'timezone1'         => (int) $p->getPost('timezone1'),
            'timezone2'         => (int) $p->getPost('timezone2'),
            'timezone3'         => (int) $p->getPost('timezone3'),
            'status'            => 0,
            'update_time'       => date('Y-m-d H:i:sP'),
        ];
        $ok = $this->dbGuard(function () use ($db, $id, &$data) {
            if ($id) {
                return $db->table('acc_accgroups')->where('id', $id)->update($data);
            }
            $data['create_time'] = date('Y-m-d H:i:sP');

            return $db->table('acc_accgroups')->insert($data);
        });
        if ($ok === null) {
            return redirect()->back()->with('error', 'Access level could not be saved — the Group No. must be unique for this area.');
        }
        $this->audit($id ? 'access.level.update' : 'access.level.create', $data['group_name']);

        return redirect()->to(route_to('access.levels'))->with('success', $id ? 'Access level updated.' : 'Access level created.');
    }

    public function deleteLevel(int $id)
    {
        $db = db_connect();
        if ($db->table('acc_accprivilege')->where('group_id', $id)->countAllResults() > 0) {
            return redirect()->back()->with('error', 'Remove the employee privileges on this level first.');
        }
        $db->table('acc_accgroups')->where('id', $id)->delete();
        $this->audit('access.level.delete', (string) $id);

        return redirect()->to(route_to('access.levels'))->with('success', 'Access level deleted.');
    }

    // ---- Time zones (acc_acctimezone) — "Time Period" ----

    private const WEEKDAYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public function timezones(): string
    {
        $db = db_connect();
        $b = $db->table('acc_acctimezone z')
            ->select("z.id, z.timezone_no, z.timezone_name, z.remark, z.area_id, a.area_name,
                      z.sun_start, z.sun_end, z.sun_on, z.mon_start, z.mon_end, z.mon_on,
                      z.tue_start, z.tue_end, z.tue_on, z.wed_start, z.wed_end, z.wed_on,
                      z.thu_start, z.thu_end, z.thu_on, z.fri_start, z.fri_end, z.fri_on,
                      z.sat_start, z.sat_end, z.sat_on")
            ->join('personnel_area a', 'a.id = z.area_id', 'left')
            ->orderBy('z.timezone_no', 'ASC');
        [$timezones, $pager] = $this->paged($b, 20);

        return view('admin/access/timezones', $this->pageCtx('timezones') + [
            'title'    => 'Time Period',
            'subtitle' => 'Weekly access windows',
            'timezones' => $timezones,
            'pager'    => $pager,
            'areas'    => $this->areas(),
            'nextTimezoneNo' => (int) ($db->query('SELECT COALESCE(MAX(timezone_no),0)+1 n FROM acc_acctimezone')->getRow()->n),
        ]);
    }

    public function saveTimezone()
    {
        $rules = [
            'timezone_name' => 'required|max_length[50]',
            'timezone_no'   => 'required|is_natural_no_zero',
            'area_id'       => 'required|is_natural_no_zero',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'tzNew');
        }

        $p = $this->request;
        // ZKBioTime's Time Period form is a 7-row table — each weekday gets
        // its own independent Start/End time and On/Off toggle, not one
        // range applied to every checked day.
        $row = [
            'timezone_name' => $p->getPost('timezone_name'),
            'timezone_no'   => (int) $p->getPost('timezone_no'),
            'area_id'       => (int) $p->getPost('area_id'),
            'remark'        => $p->getPost('remark') ?: null,
            'status'        => 0,
            'update_time'   => date('Y-m-d H:i:sP'),
        ];
        foreach (self::WEEKDAYS as $d) {
            $on = (bool) $p->getPost("{$d}_on");
            $row["{$d}_start"] = ($p->getPost("{$d}_start") ?: '00:00') . ':00';
            $row["{$d}_end"]   = ($p->getPost("{$d}_end") ?: '23:59') . ':00';
            $row["{$d}_on"]    = $on ? 1 : 0;
        }

        $db = db_connect();
        $id = (int) $p->getPost('id');
        $ok = $this->dbGuard(function () use ($db, $id, &$row) {
            if ($id) {
                return $db->table('acc_acctimezone')->where('id', $id)->update($row);
            }
            $row['create_time'] = date('Y-m-d H:i:sP');

            return $db->table('acc_acctimezone')->insert($row);
        });
        if ($ok === null) {
            return redirect()->back()->with('error', 'Time zone could not be saved — check the Time Period No. is unique for this area.');
        }
        $this->audit($id ? 'access.tz.update' : 'access.tz.create', $row['timezone_name']);

        return redirect()->to(route_to('access.timezones'))->with('success', $id ? 'Time zone updated.' : 'Time zone created.');
    }

    public function deleteTimezone(int $id)
    {
        db_connect()->table('acc_acctimezone')->where('id', $id)->delete();
        $this->audit('access.tz.delete', (string) $id);

        return redirect()->to(route_to('access.timezones'))->with('success', 'Time zone deleted.');
    }

    // ---- Privileges (acc_accprivilege) — "Privilege" ----

    public function privileges(): string
    {
        $db = db_connect();
        $privB = $db->table('acc_accprivilege p')
            ->select("p.id, p.employee_id, p.verify_mode, p.is_group_timezone,
                      e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name, e.first_name,
                      g.group_name, g.group_no, a.area_name")
            ->join('personnel_employee e', 'e.id = p.employee_id')
            ->join('acc_accgroups g', 'g.id = p.group_id')
            ->join('personnel_area a', 'a.id = p.area_id', 'left')
            ->orderBy('e.emp_code_digit', 'ASC');
        [$privileges, $privPager] = $this->paged($privB, 25, 'priv');

        return view('admin/access/privileges', $this->pageCtx('privileges') + [
            'title'    => 'Privilege',
            'subtitle' => 'Which employees hold which access level',
            'privileges' => $privileges,
            'privPager'  => $privPager,
            'groups'   => $db->query('SELECT id, group_name, area_id FROM acc_accgroups ORDER BY group_no')
                ->getResultArray()
                + [], // keep array shape; area_name joined below for display use
            'timezones' => $this->timezoneList(),
            'vm'       => self::VERIFY_MODES,
            'employees' => $db->query(
                "SELECT e.id, e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name, e.first_name
                   FROM personnel_employee e WHERE e.is_active = true ORDER BY e.emp_code_digit ASC"
            )->getResultArray(),
        ]);
    }

    /**
     * ZKBioTime's Privilege ▸ Adjust — grants one access level to MANY
     * employees at once, with an explicit choice (per setting) of using the
     * level's own Verification Mode / Time Zones, or a personal override.
     */
    public function assign()
    {
        $p       = $this->request;
        $empIds  = array_values(array_filter(array_map('intval', (array) $p->getPost('employee_ids'))));
        $grpId   = (int) $p->getPost('group_id');
        if (! $empIds || ! $grpId) {
            return redirect()->back()->with('error', 'Pick a group and at least one employee.')->with('reopen', 'privNew');
        }

        $db     = db_connect();
        $areaId = (int) ($db->query('SELECT area_id FROM acc_accgroups WHERE id = ?', [$grpId])->getRow()->area_id ?? 0);

        $isGroupVerify = (string) $p->getPost('verify_scope') !== 'personnel';
        $isGroupTz     = (string) $p->getPost('tz_scope') !== 'personnel';
        $base = [
            'group_id'            => $grpId,
            'area_id'             => $areaId,
            'is_group_verifycode' => $isGroupVerify ? 1 : 0,
            'verify_mode'         => $isGroupVerify ? 0 : (int) $p->getPost('verify_mode'),
            'is_group_timezone'   => $isGroupTz ? 1 : 0,
            'timezone1'           => $isGroupTz ? null : ((int) $p->getPost('timezone1') ?: null),
            'timezone2'           => $isGroupTz ? null : ((int) $p->getPost('timezone2') ?: null),
            'timezone3'           => $isGroupTz ? null : ((int) $p->getPost('timezone3') ?: null),
            'status'              => 0,
            'create_time'         => date('Y-m-d H:i:sP'),
        ];

        $granted = $skipped = 0;
        $ok = $this->dbGuard(function () use ($db, $empIds, $base, &$granted, &$skipped) {
            foreach ($empIds as $empId) {
                if ($db->table('acc_accprivilege')->where('employee_id', $empId)->where('group_id', $base['group_id'])->countAllResults()) {
                    $skipped++;
                    continue;
                }
                $db->table('acc_accprivilege')->insert($base + ['employee_id' => $empId]);
                $granted++;
            }

            return true;
        });
        if ($ok === null) {
            return redirect()->back()->with('error', 'Access could not be granted — check the group still exists.');
        }
        $this->audit('access.grant', "{$granted} granted, {$skipped} already had it", "level={$grpId}");

        return redirect()->to(route_to('access.privileges'))->with(
            $granted ? 'success' : 'warning',
            $granted ? "Access granted to {$granted} employee(s)." . ($skipped ? " {$skipped} already had it." : '') : 'All selected employees already had this access level.'
        );
    }

    public function revoke(int $id)
    {
        db_connect()->table('acc_accprivilege')->where('id', $id)->delete();
        $this->audit('access.revoke', (string) $id);

        return redirect()->to(route_to('access.privileges'))->with('success', 'Access revoked.');
    }

    // ---- Combinations (acc_acccombination — multi-person unlock) — "Combination" ----

    public function combinations(): string
    {
        $b = db_connect()->table('acc_acccombination c')
            ->select("c.id, c.combination_no, c.combination_name,
                      c.group1, c.group2, c.group3, c.group4, c.group5, a.area_name")
            ->join('personnel_area a', 'a.id = c.area_id', 'left')
            ->orderBy('c.combination_no', 'ASC');
        [$combos, $pager] = $this->paged($b, 20);

        return view('admin/access/combinations', $this->pageCtx('combinations') + [
            'title'    => 'Combination',
            'subtitle' => 'Multi-person unlock — all listed access levels must present',
            'combos'   => $combos,
            'pager'    => $pager,
            'areas'    => $this->areas(),
        ]);
    }

    public function saveCombination()
    {
        if (! $this->validate(['combination_name' => 'required|max_length[100]', 'area_id' => 'required|is_natural_no_zero'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }
        $p    = $this->request;
        $id   = (int) $p->getPost('id');
        $db   = db_connect();
        $data = [
            'combination_name' => trim($p->getPost('combination_name')),
            'area_id'          => (int) $p->getPost('area_id'),
            'remark'           => $p->getPost('remark') ?: null,
        ];
        foreach (['group1', 'group2', 'group3', 'group4', 'group5'] as $g) {
            $data[$g] = (int) $p->getPost($g) ?: 0;
        }
        $ok = $this->dbGuard(static function () use ($db, $id, $data) {
            if ($id) {
                return $db->table('acc_acccombination')->where('id', $id)->update($data);
            }
            $data['combination_no'] = (int) ($db->query('SELECT COALESCE(MAX(combination_no),0)+1 n FROM acc_acccombination')->getRow()->n);

            return $db->table('acc_acccombination')->insert($data + ['status' => 0, 'create_time' => date('Y-m-d H:i:sP'), 'update_time' => date('Y-m-d H:i:sP')]);
        });
        if ($ok === null) {
            return redirect()->back()->with('error', 'Combination could not be saved.');
        }
        $this->audit($id ? 'access.combo.update' : 'access.combo.create', $data['combination_name']);

        return redirect()->to(route_to('access.combinations'))->with('success', 'Combination saved.');
    }

    public function deleteCombination(int $id)
    {
        db_connect()->table('acc_acccombination')->where('id', $id)->delete();
        $this->audit('access.combo.delete', (string) $id);

        return redirect()->to(route_to('access.combinations'))->with('success', 'Combination deleted.');
    }

    // ---- Access holidays (acc_accholiday — holiday × area × time zone) — "Holiday" ----

    public function holidays(): string
    {
        $db = db_connect();
        $b = $db->table('acc_accholiday h')
            ->select("h.id, a.area_name, hol.alias AS holiday, hol.start_date, hol.end_date,
                      z.timezone_name")
            ->join('personnel_area a', 'a.id = h.area_id', 'left')
            ->join('att_holiday hol', 'hol.id = h.holiday_id', 'left')
            ->join('acc_acctimezone z', 'z.id = h.timezone_id', 'left')
            ->orderBy('hol.start_date', 'DESC');
        [$accHolidays, $pager] = $this->paged($b, 20);

        return view('admin/access/holidays', $this->pageCtx('accholidays') + [
            'title'    => 'Holiday',
            'subtitle' => 'On a mapped holiday the door follows the chosen time zone instead of the normal one',
            'accHolidays' => $accHolidays,
            'pager'    => $pager,
            'areas'    => $this->areas(),
            'timezones' => $this->timezoneList(),
            'holidayList' => $db->query('SELECT id, alias, start_date FROM att_holiday ORDER BY start_date DESC')->getResultArray(),
        ]);
    }

    public function saveAccHoliday()
    {
        if (! $this->validate(['holiday_id' => 'required|is_natural_no_zero', 'area_id' => 'required|is_natural_no_zero', 'timezone_id' => 'required|is_natural_no_zero'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }
        $p  = $this->request;
        $rid = $this->saveRow('acc_accholiday', [
            'holiday_id'  => (int) $p->getPost('holiday_id'),
            'area_id'     => (int) $p->getPost('area_id'),
            'timezone_id' => (int) $p->getPost('timezone_id'),
        ], (int) $p->getPost('id'), ['status' => 0, 'create_time' => date('Y-m-d H:i:sP'), 'update_time' => date('Y-m-d H:i:sP')]);
        if ($rid === null) {
            return redirect()->back()->with('error', 'This holiday is already mapped for that area.');
        }
        $this->audit('access.holiday.save', (string) $rid);

        return redirect()->to(route_to('access.holidays'))->with('success', 'Access holiday saved.');
    }

    public function deleteAccHoliday(int $id)
    {
        db_connect()->table('acc_accholiday')->where('id', $id)->delete();
        $this->audit('access.holiday.delete', (string) $id);

        return redirect()->to(route_to('access.holidays'))->with('success', 'Access holiday removed.');
    }

    // ---- Door parameters (acc_accterminal) — "Device" ----

    public function doors(): string
    {
        $b = db_connect()->table('acc_accterminal d')
            ->select('d.*, t.alias AS device, t.sn, ar.area_name')
            ->join('iclock_terminal t', 't.id = d.terminal_id', 'left')
            ->join('personnel_area ar', 'ar.id = t.area_id', 'left')
            ->orderBy('t.alias', 'ASC');
        [$doors, $pager] = $this->paged($b, 20);

        return view('admin/access/doors', $this->pageCtx('doors') + [
            'title'    => 'Device',
            'subtitle' => 'Door parameters — will sync to the device on next connect',
            'doors'    => $doors,
            'pager'    => $pager,
            'terminals' => db_connect()->query('SELECT id, alias, sn FROM iclock_terminal ORDER BY alias')->getResultArray(),
        ]);
    }

    public function saveDoor()
    {
        $p   = $this->request;
        $tid = (int) $p->getPost('terminal_id');
        if ($tid <= 0) {
            return redirect()->back()->with('error', 'Pick a device.');
        }
        $db   = db_connect();
        $data = [
            'door_name'         => $p->getPost('door_name') ?: null,
            'door_lock_delay'   => (int) $p->getPost('door_lock_delay'),
            'door_sensor_delay' => (int) $p->getPost('door_sensor_delay'),
            'door_sensor_type'  => (int) $p->getPost('door_sensor_type'),
            'door_alarm_delay'  => (int) $p->getPost('door_alarm_delay'),
            'valid_holiday'     => (int) (bool) $p->getPost('valid_holiday'),
            'retry_times'       => (int) $p->getPost('retry_times'),
            'push_time'         => date('Y-m-d H:i:sP'),
        ];
        $row = $db->table('acc_accterminal')->where('terminal_id', $tid)->get()->getRowArray();
        $ok  = $this->dbGuard(static fn () => $row
            ? $db->table('acc_accterminal')->where('id', $row['id'])->update($data)
            : $db->table('acc_accterminal')->insert($data + [
                'terminal_id' => $tid, 'status' => 0, 'door_sensor_type' => $data['door_sensor_type'],
                'speaker_alarm' => 0, 'duress_fun_on' => 0, 'alarm_1_1' => 0, 'alarm_1_n' => 0,
                'alarm_password' => 0, 'duress_alarm_delay' => 0, 'anti_passback_mode' => 0,
                'anti_door_direction' => 0, 'verify_mode_485' => 0, 'nc_time_period' => 0, 'no_time_period' => 0,
                'create_time' => date('Y-m-d H:i:sP'),
            ]));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Door parameters could not be saved.');
        }
        $this->audit('access.door.save', (string) $tid);

        return redirect()->to(route_to('access.doors'))->with('success', 'Door parameters saved — will sync to the device on next connect.');
    }

    // ---- Device Command (iclock_terminalcommand — the pending PUSH queue) — "Device Command" ----

    public function commands(): string
    {
        $cmdB = db_connect()->table('iclock_terminalcommand c')
            ->select("c.id, c.content, t.alias AS device, t.sn,
                      to_char(c.commit_time,'YYYY-MM-DD HH24:MI:SS') AS commit_at,
                      to_char(c.transfer_time,'YYYY-MM-DD HH24:MI:SS') AS transfer_at")
            ->join('iclock_terminal t', 't.id = c.terminal_id', 'left')
            ->orderBy('c.commit_time', 'DESC');
        [$commands, $cmdPager] = $this->paged($cmdB, 25, 'cmd');

        return view('admin/access/commands', $this->pageCtx('commands') + [
            'title'    => 'Device Command',
            'subtitle' => 'Pending PUSH command queue',
            'commands' => $commands,
            'cmdPager' => $cmdPager,
        ]);
    }

    public function deleteCommand(int $id)
    {
        db_connect()->table('iclock_terminalcommand')->where('id', $id)->delete();
        $this->audit('access.command.delete', (string) $id);

        return redirect()->to(route_to('access.commands'))->with('success', 'Command removed from the queue.');
    }

    public function bulkDeleteCommand()
    {
        $ids = $this->ids();
        if (! $ids) {
            return redirect()->back()->with('error', 'Nothing selected.');
        }
        $n = db_connect()->table('iclock_terminalcommand')->whereIn('id', $ids)->delete();
        $this->audit('access.command.bulk_delete', $n . ' removed');

        return redirect()->to(route_to('access.commands'))->with('success', $n . ' command(s) removed.');
    }

    public function clearCommands()
    {
        $n = db_connect()->table('iclock_terminalcommand')->delete();
        $this->audit('access.command.clear', $n . ' cleared');

        return redirect()->to(route_to('access.commands'))->with('success', 'Queue cleared.');
    }

    // --------------------------------------------------------------- helper

    private function ids(): array
    {
        return array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
    }
}

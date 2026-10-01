<?php

namespace App\Controllers;

/**
 * Attendance ▸ Setup — the three lookup tables the calculator and every
 * report are built on:
 *
 *   Pay Code          att_paycode   a bucket for worked / OT / leave minutes
 *   Attendance Code   att_attcode   how a payload minute count is displayed
 *   Attendance Group  att_attgroup  a bag of employees for group rules/schedules
 */
class AttSetup extends BaseController
{
    public const CODE_TYPE = [1 => 'Normal', 2 => 'Overtime', 3 => 'Leave', 4 => 'Exception', 5 => 'Training'];
    public const TAG = [
        1 => 'Normal time', 2 => 'Overtime ×1.5', 3 => 'Overtime ×2.0',
        4 => 'Paid holiday ×1.0', 5 => 'Paid holiday ×2.0',
        6 => 'OT type 1', 7 => 'OT type 2', 8 => 'OT type 3',
    ];
    public const FORMAT   = [1 => 'Minutes', 2 => 'Hours', 3 => 'Work day', 4 => 'hh:mm'];
    public const ROUND    = [1 => 'Round to nearest', 2 => 'Round up', 3 => 'Round down'];

    // =====================================================================
    //  Pay Code
    // =====================================================================

    public function paycodes(): string
    {
        $db = db_connect();
        $b  = $db->table('att_paycode')
            ->select('id, code, name, code_type, tag, is_work, is_paid, is_benefit, fixed_hours, display_format, display_order, is_display')
            ->orderBy('display_order')->orderBy('code');
        $fbApplied = $this->filterBuilder($b, [
            'code'      => ['code', 'text'],
            'name'      => ['name', 'text'],
            'code_type' => ['code_type', 'select'],
            'is_paid'   => ['is_paid', 'bool'],
        ]);
        [$rows, $pager] = $this->paged($b, 40);

        return view('admin/attsetup/paycodes', [
            'title'     => 'Pay Code',
            'subtitle'  => 'Buckets the calculator sorts every minute into — the bridge to payroll',
            'active'    => 'att.paycode',
            'rows'      => $rows,
            'pager'     => $pager,
            'fbApplied' => $fbApplied,
            'codeTypes' => self::CODE_TYPE,
            'tags'      => self::TAG,
            'formats'   => self::FORMAT,
            'rounds'    => self::ROUND,
        ]);
    }

    public function savePaycode()
    {
        $p  = $this->request;
        $id = (int) $p->getPost('id');
        if (! $this->validate([
            'code' => "required|max_length[20]|is_unique[att_paycode.code,id,{$id}]",
            'name' => "required|max_length[50]|is_unique[att_paycode.name,id,{$id}]",
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors())->with('reopen', 'pcForm');
        }
        $data = [
            'code'          => strtoupper(trim((string) $p->getPost('code'))),
            'name'          => trim((string) $p->getPost('name')),
            'code_type'     => (int) $p->getPost('code_type'),
            'tag'           => (int) $p->getPost('tag') ?: null,
            'is_work'       => (bool) $p->getPost('is_work'),
            'is_paid'       => (bool) $p->getPost('is_paid'),
            'is_benefit'    => (bool) $p->getPost('is_benefit'),
            'fixed_hours'   => (float) ($p->getPost('fixed_hours') ?: 8),
            'display_format'=> (int) ($p->getPost('display_format') ?: 2),
            'display_order' => (int) ($p->getPost('display_order') ?: 999),
            'symbol'        => trim((string) $p->getPost('symbol')) ?: null,
            'is_display'    => (bool) ($p->getPost('is_display') ?? true),
            'round_off'     => (int) ($p->getPost('round_off') ?: 1),
            'min_val'       => (float) ($p->getPost('min_val') ?: 0.1),
        ];
        $db = db_connect();
        $ok = $this->dbGuard(static fn () => $id
            ? $db->table('att_paycode')->where('id', $id)->update($data)
            : $db->table('att_paycode')->insert($data + ['status' => 0, 'is_default' => false]));
        if ($ok === null) {
            return redirect()->back()->withInput()->with('error', 'Pay code could not be saved — code and name must be unique.');
        }
        $this->audit($id ? 'att.paycode.update' : 'att.paycode.create', $data['code']);

        return redirect()->to(route_to('attsetup.paycodes'))->with('success', 'Pay code saved.');
    }

    public function deletePaycode(int $id)
    {
        $db = db_connect();
        if ($db->table('att_paycode')->where('id', $id)->where('is_default', true)->countAllResults()) {
            return redirect()->back()->with('error', 'A default pay code cannot be deleted.');
        }
        foreach (['att_leave' => 'pay_code_id', 'att_overtime' => 'pay_code_id', 'att_training' => 'pay_code_id',
            'att_timeinterval' => 'ot_pay_code_id', 'att_leavegroupdetail' => 'pay_code_id'] as $t => $fk) {
            if ($db->table($t)->where($fk, $id)->countAllResults()) {
                return redirect()->back()->with('error', 'This pay code is in use (' . $t . ') — reassign first.');
            }
        }
        $db->table('att_paycode')->where('id', $id)->delete();
        $this->audit('att.paycode.delete', (string) $id);

        return redirect()->to(route_to('attsetup.paycodes'))->with('success', 'Pay code deleted.');
    }

    // =====================================================================
    //  Attendance Code
    // =====================================================================

    public function attcodes(): string
    {
        $db = db_connect();
        $b  = $db->table('att_attcode')->select('id, code, alias, display_format, symbol, round_off, min_val, "order"')
            ->orderBy('"order"')->orderBy('alias');
        $fbApplied = $this->filterBuilder($b, ['alias' => ['alias', 'text'], 'code' => ['code', 'text']]);
        [$rows, $pager] = $this->paged($b, 40);

        return view('admin/attsetup/attcodes', [
            'title'     => 'Attendance Code',
            'subtitle'  => 'How a minute count is rendered in a report cell',
            'active'    => 'att.attcode',
            'rows'      => $rows,
            'pager'     => $pager,
            'fbApplied' => $fbApplied,
            'formats'   => self::FORMAT,
            'rounds'    => self::ROUND,
        ]);
    }

    public function saveAttcode()
    {
        $p  = $this->request;
        $id = (int) $p->getPost('id');
        if (! $this->validate(['alias' => "required|max_length[50]|is_unique[att_attcode.alias,id,{$id}]"])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors())->with('reopen', 'acForm');
        }
        $data = [
            'alias'          => trim((string) $p->getPost('alias')),
            'display_format' => (int) ($p->getPost('display_format') ?: 2),
            'symbol'         => trim((string) $p->getPost('symbol')),
            'round_off'      => (int) ($p->getPost('round_off') ?: 1),
            'min_val'        => (float) ($p->getPost('min_val') ?: 0.1),
        ];
        $db = db_connect();
        $ok = $this->dbGuard(static fn () => $id
            ? $db->table('att_attcode')->where('id', $id)->update($data)
            : $db->table('att_attcode')->insert($data + [
                'code' => 'AC' . substr(md5(uniqid('', true)), 0, 8), 'symbol_only' => false, 'order' => 99,
            ]));
        if ($ok === null) {
            return redirect()->back()->withInput()->with('error', 'Attendance code could not be saved.');
        }
        $this->audit($id ? 'att.attcode.update' : 'att.attcode.create', $data['alias']);

        return redirect()->to(route_to('attsetup.attcodes'))->with('success', 'Attendance code saved.');
    }

    public function deleteAttcode(int $id)
    {
        $db = db_connect();
        if ($db->table('att_payloadattcode')->where('att_code_id', $id)->countAllResults()) {
            return redirect()->back()->with('error', 'This code has been used in a calculation.');
        }
        $db->table('att_attcode')->where('id', $id)->delete();
        $this->audit('att.attcode.delete', (string) $id);

        return redirect()->to(route_to('attsetup.attcodes'))->with('success', 'Attendance code deleted.');
    }

    // =====================================================================
    //  Attendance Group
    // =====================================================================

    public function groups(): string
    {
        $db = db_connect();
        $b  = $db->table('att_attgroup g')
            ->select("g.id, g.code, g.name, c.company_name,
                      (SELECT count(*) FROM att_attemployee ae WHERE ae.group_id = g.id) AS emp_count")
            ->join('personnel_company c', 'c.id = g.company_id', 'left')
            ->orderBy('g.name');
        $fbApplied = $this->filterBuilder($b, ['code' => ['g.code', 'text'], 'name' => ['g.name', 'text']]);
        [$rows, $pager] = $this->paged($b, 40);

        return view('admin/attsetup/groups', [
            'title'     => 'Attendance Group',
            'subtitle'  => 'Bags of employees that group rules and schedules target',
            'active'    => 'att.attgroup',
            'rows'      => $rows,
            'pager'     => $pager,
            'fbApplied' => $fbApplied,
        ]);
    }

    public function saveGroup()
    {
        $p  = $this->request;
        $id = (int) $p->getPost('id');
        if (! $this->validate([
            'code' => "required|max_length[50]",
            'name' => "required|max_length[100]",
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors())->with('reopen', 'agForm');
        }
        $data = ['code' => trim((string) $p->getPost('code')), 'name' => trim((string) $p->getPost('name'))];
        $db   = db_connect();
        $ok   = $this->dbGuard(static function () use ($db, $id, $data) {
            if ($id) {
                return $db->table('att_attgroup')->where('id', $id)->update($data + ['change_time' => date('Y-m-d H:i:sP')]);
            }
            $company = (int) ($db->query('SELECT id FROM personnel_company ORDER BY is_default DESC, id LIMIT 1')->getRow()->id ?? 1);

            return $db->table('att_attgroup')->insert($data + ['status' => 0, 'company_id' => $company, 'create_time' => date('Y-m-d H:i:sP')]);
        });
        if ($ok === null) {
            return redirect()->back()->withInput()->with('error', 'Group could not be saved.');
        }
        $this->audit($id ? 'att.attgroup.update' : 'att.attgroup.create', $data['name']);

        return redirect()->to(route_to('attsetup.groups'))->with('success', 'Attendance group saved.');
    }

    public function deleteGroup(int $id)
    {
        $db = db_connect();
        foreach (['att_attemployee' => 'group_id', 'att_groupschedule' => 'group_id', 'att_grouppolicy' => 'group_id',
            'att_holiday' => 'att_group_id'] as $t => $fk) {
            if ($db->table($t)->where($fk, $id)->countAllResults()) {
                return redirect()->back()->with('error', 'This group is in use — clear its members / schedules first.');
            }
        }
        $db->table('att_attgroup')->where('id', $id)->delete();
        $this->audit('att.attgroup.delete', (string) $id);

        return redirect()->to(route_to('attsetup.groups'))->with('success', 'Attendance group deleted.');
    }

    // =====================================================================
    //  Setting Guide ▸ Workbench
    // =====================================================================

    public function workbench(): string
    {
        $db  = db_connect();
        $one = static fn (string $sql) => (int) ($db->query($sql)->getRow()->c ?? 0);

        return view('admin/attsetup/workbench', [
            'title'    => 'Workbench',
            'subtitle' => 'Set attendance up in four steps',
            'active'   => 'att.workbench',
            'counts'   => [
                'breaks'     => $one('SELECT count(*) c FROM att_breaktime'),
                'timetables' => $one('SELECT count(*) c FROM att_timeinterval'),
                'shifts'     => $one('SELECT count(*) c FROM att_attshift'),
                'schedules'  => $one('SELECT count(*) c FROM att_attschedule') + $one('SELECT count(*) c FROM att_departmentschedule'),
                'timecards'  => $one('SELECT count(*) c FROM att_payloadtimecard'),
                'txns'       => $one('SELECT count(*) c FROM iclock_transaction'),
            ],
        ]);
    }
}

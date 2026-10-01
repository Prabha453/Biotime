<?php

namespace App\Controllers;

/**
 * Leave groups (att_leavegroup) + per-type entitlements (att_leavegroupdetail).
 * An employee is linked to a group via personnel_employee.leave_group; the
 * Requests screen then shows / enforces the remaining balance on apply.
 */
class LeaveGroups extends BaseController
{
    /**
     * ZKBioTime att_leavegroupdetail.leave_type — the entitlement
     * calculation mode (NOT the leave type itself, that's pay_code_id).
     * Derived from the real product's add_leave_group_detail.html JS
     * (which fields each mode shows/hides) since the Python choices enum
     * is compiled and unreadable; 1/3/5 are certain, 2/4 are both
     * "fixed quota" variants distinguished only by which extra fields the
     * real form exposes (4 adds interval/distribution/start-day) — high
     * confidence overall, but treat the exact 2-vs-4 label as best-effort.
     */
    public const LEAVE_TYPE_MODES = [
        1 => 'One-Time Leave',
        2 => 'Fixed Quota',
        3 => 'Based On Service',
        4 => 'Fixed Quota (Recurring)',
        5 => 'Compensatory Leave',
    ];

    public function index(): string
    {
        $db = db_connect();

        $groups = $db->query(
            "SELECT g.id, g.code, g.name,
                    (SELECT count(*) FROM att_leavegroupdetail d WHERE d.leave_group_id = g.id) AS types,
                    (SELECT count(*) FROM personnel_employee e WHERE e.leave_group = g.id) AS members
               FROM att_leavegroup g ORDER BY g.name"
        )->getResultArray();

        foreach ($groups as &$g) {
            $g['details'] = $db->query(
                "SELECT d.id, d.allow_leave_day, d.min_leave_day, d.allow_exceed_limit, d.allow_balance,
                        d.max_balance, d.leave_type, d.leave_entitlement, d.leave_interval,
                        d.start_day, d.set_hire_day, d.deduct_holiday_day,
                        pc.name AS pay_code, pc.code AS pay_code_code, d.pay_code_id
                   FROM att_leavegroupdetail d
              LEFT JOIN att_paycode pc ON pc.id = d.pay_code_id
                  WHERE d.leave_group_id = ? ORDER BY pc.display_order",
                [$g['id']]
            )->getResultArray();
        }
        unset($g);

        return view('admin/leavegroups/index', [
            'title'      => 'Leave Types',
            'subtitle'   => 'Annual entitlements per leave group',
            'active'     => 'leavegroups',
            'groups'     => $groups,
            'leaveTypes' => $db->query('SELECT id, code, name FROM att_paycode WHERE code_type = 3 ORDER BY display_order')->getResultArray(),
            'modes'      => self::LEAVE_TYPE_MODES,
        ]);
    }

    public function saveGroup()
    {
        if (! $this->validate(['name' => 'required|max_length[100]', 'code' => 'required|max_length[50]'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'lgNew');
        }
        $p  = $this->request;
        $db = db_connect();
        $id = (int) $p->getPost('id');
        $data = ['code' => $p->getPost('code'), 'name' => $p->getPost('name'), 'status' => 0];

        $ok = $this->dbGuard(function () use ($db, $id, $data) {
            if ($id) {
                return $db->table('att_leavegroup')->where('id', $id)->update($data);
            }
            $data['company_id']  = (int) ($db->query('SELECT id FROM personnel_company ORDER BY id LIMIT 1')->getRow()->id ?? 1);
            $data['create_time'] = date('Y-m-d H:i:sP');

            return $db->table('att_leavegroup')->insert($data);
        });
        if ($ok === null) {
            return redirect()->back()->with('error', 'Leave group could not be saved (code must be unique).');
        }
        $this->audit($id ? 'leavegroup.update' : 'leavegroup.create', $data['name']);

        return redirect()->to(route_to('leavegroups'))->with('success', $id ? 'Leave group updated.' : 'Leave group created.');
    }

    public function deleteGroup(int $id)
    {
        $db = db_connect();
        if ($db->table('personnel_employee')->where('leave_group', $id)->countAllResults() > 0) {
            return redirect()->back()->with('error', 'Unassign employees from this group first.');
        }
        $ok = $this->tx(function ($db) use ($id) {
            $db->table('att_leavegroupdetail')->where('leave_group_id', $id)->delete();
            $db->table('att_leavegroup')->where('id', $id)->delete();
        });
        $this->audit('leavegroup.delete', (string) $id);

        return redirect()->to(route_to('leavegroups'))->with('success', $ok ? 'Leave group deleted.' : 'Delete failed.');
    }

    public function saveDetail()
    {
        $rules = [
            'leave_group_id'  => 'required|is_natural_no_zero',
            'pay_code_id'     => 'required|is_natural_no_zero',
            'leave_type'      => 'required|in_list[' . implode(',', array_keys(self::LEAVE_TYPE_MODES)) . ']',
            'allow_leave_day' => 'required|is_natural',
            'start_day'       => 'permit_empty|regex_match[/^\d{2}-\d{2}$/]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'lgd' . (int) $this->request->getPost('leave_group_id'));
        }
        $p  = $this->request;
        $db = db_connect();
        $id = (int) $p->getPost('id');
        // leave_type is the entitlement CALCULATION MODE (see LEAVE_TYPE_MODES) —
        // a distinct column from pay_code_id (which leave type this entitlement
        // is for). Both this app and the real ZKBioTime read/write this same
        // shared row, so leave_type must be a valid 1-5 mode, not pay_code_id.
        $data = [
            'leave_group_id'     => (int) $p->getPost('leave_group_id'),
            'pay_code_id'        => (int) $p->getPost('pay_code_id'),
            'leave_type'         => (int) $p->getPost('leave_type'),
            'allow_leave_day'    => (int) $p->getPost('allow_leave_day'),
            'min_leave_day'      => (float) ($p->getPost('min_leave_day') ?: 0.5),
            'deduct_holiday_day' => (int) (bool) $p->getPost('deduct_holiday_day'),
            'leave_entitlement'  => ($le = trim((string) $p->getPost('leave_entitlement'))) !== '' ? (int) $le : null,
            'leave_interval'     => (int) ($p->getPost('leave_interval') ?: 1),
            'leave_distribution_time' => (int) ($p->getPost('leave_distribution_time') ?: 1),
            'start_day'          => $p->getPost('start_day') ?: '01-01',
            'set_hire_day'       => (int) (bool) $p->getPost('set_hire_day'),
            'allow_exceed_limit' => (int) (bool) $p->getPost('allow_exceed_limit'),
            'allow_balance'      => (int) (bool) $p->getPost('allow_balance'),
            'max_balance'        => (int) $p->getPost('max_balance') ?: null,
            'status'             => 0,
        ];
        $ok = $this->dbGuard(fn () => $id
            ? $db->table('att_leavegroupdetail')->where('id', $id)->update($data)
            : $db->table('att_leavegroupdetail')->insert($data + ['create_time' => date('Y-m-d H:i:sP')]));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Entitlement could not be saved.');
        }
        $this->audit($id ? 'leavegroup.detail.update' : 'leavegroup.detail.create', 'group=' . (int) $p->getPost('leave_group_id'));

        return redirect()->to(route_to('leavegroups'))->with('success', 'Entitlement saved.');
    }

    public function deleteDetail(int $id)
    {
        db_connect()->table('att_leavegroupdetail')->where('id', $id)->delete();
        $this->audit('leavegroup.detail.delete', (string) $id);

        return redirect()->to(route_to('leavegroups'))->with('success', 'Entitlement removed.');
    }

    /** Bulk-assign a leave group to a set of employees. */
    public function assign()
    {
        $groupId = (int) $this->request->getPost('leave_group_id');
        $empIds  = array_map('intval', (array) $this->request->getPost('employee_ids'));
        if (! $groupId || $empIds === []) {
            return redirect()->back()->with('error', 'Pick a group and at least one employee.');
        }
        db_connect()->table('personnel_employee')->whereIn('id', $empIds)->update(['leave_group' => $groupId]);
        $this->audit('leavegroup.assign', 'group=' . $groupId, count($empIds) . ' employees');

        return redirect()->to(route_to('leavegroups'))->with('success', count($empIds) . ' employee(s) assigned.');
    }
}

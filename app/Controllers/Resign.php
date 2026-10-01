<?php

namespace App\Controllers;

/**
 * Resignation register — ZKBioTime's Personnel ▸ Employee ▸ Resign.
 * One row per resigned employee in personnel_resign
 * (resign_date, resign_type 1-5, disableatt, reason). Reinstating deletes
 * the row and re-activates the employee.
 */
class Resign extends BaseController
{
    public const TYPES = [
        1 => 'Quit',
        2 => 'Dismissed',
        3 => 'Resignation',
        4 => 'Transfer',
        5 => 'Retain job without salary',
    ];

    public function index()
    {
        $data = $this->listData();

        if ($this->wantsJson()) {
            return view('admin/resign/_list', $data);
        }

        $db = db_connect();

        return view('admin/resign/index', $data + [
            'title'     => 'Resign',
            'subtitle'  => 'Resigned employees register',
            'active'    => 'resign',
            'employees' => $db->query(
                "SELECT id, emp_code, NULLIF(TRIM(CONCAT(first_name,' ',last_name)),'') name
                   FROM personnel_employee
                  WHERE id NOT IN (SELECT employee_id FROM personnel_resign)
               ORDER BY emp_code_digit"
            )->getResultArray(),
        ]);
    }

    private function listData(): array
    {
        $b = db_connect()->table('personnel_resign r')
            ->select("r.id, r.resign_date, r.resign_type, r.disableatt, r.reason,
                      e.id AS emp_id, e.emp_code,
                      NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name,
                      d.dept_name, p.position_name")
            ->join('personnel_employee e', 'e.id = r.employee_id')
            ->join('personnel_department d', 'd.id = e.department_id', 'left')
            ->join('personnel_position p', 'p.id = e.position_id', 'left')
            ->orderBy('r.resign_date', 'DESC')->orderBy('r.id', 'DESC');

        $fbApplied = $this->filterBuilder($b, [
            'emp_code'    => ['e.emp_code', 'text'],
            'name'        => ["TRIM(CONCAT(e.first_name,' ',e.last_name))", 'text'],
            'dept_name'   => ['d.dept_name', 'text'],
            'resign_type' => ['r.resign_type', 'select'],
            'resign_date' => ['r.resign_date', 'date'],
            'disableatt'  => ['r.disableatt', 'bool'],
        ]);

        [$rows, $pager] = $this->paged($b, 20);

        return [
            'rows'      => $rows,
            'pager'     => $pager,
            'types'     => self::TYPES,
            'fbApplied' => $fbApplied,
        ];
    }

    public function save()
    {
        $id = (int) $this->request->getPost('id');

        $rules = ['resign_date' => 'required|valid_date', 'resign_type' => 'required|in_list[1,2,3,4,5]'];
        if ($id === 0) {
            $rules['employee_id'] = 'required|is_natural_no_zero';
        }
        if (! $this->validate($rules)) {
            return $this->wantsJson()
                ? $this->jsonFail('Please correct the highlighted fields.', $this->validator->getErrors())
                : redirect()->back()->withInput()->with('errors', $this->validator->getErrors())->with('reopen', 'resignForm');
        }

        $p       = $this->request;
        $disable = (bool) $p->getPost('disableatt');
        $data    = [
            'resign_date' => $p->getPost('resign_date'),
            'resign_type' => (int) $p->getPost('resign_type'),
            'disableatt'  => $disable,
            'reason'      => trim((string) $p->getPost('reason')) ?: null,
        ];

        $empId = (int) $p->getPost('employee_id');
        $ok    = $this->tx(function ($db) use ($id, $data, $empId, $disable): void {
            if ($id) {
                $db->table('personnel_resign')->where('id', $id)->update($data);
                $row = $db->table('personnel_resign')->select('employee_id')->where('id', $id)->get()->getRowArray();
                $eid = (int) ($row['employee_id'] ?? 0);
            } else {
                $db->table('personnel_resign')->where('employee_id', $empId)->delete();
                $db->table('personnel_resign')->insert($data + ['employee_id' => $empId]);
                $eid = $empId;
            }
            if ($eid) {
                $db->table('personnel_employee')->where('id', $eid)->update(['is_active' => ! $disable]);
                if ($disable) {
                    $db->table('att_attemployee')->where('emp_id', $eid)->update(['enable_attendance' => false]);
                }
            }
        });

        if (! $ok) {
            return $this->wantsJson()
                ? $this->jsonFail('The resignation could not be saved.')
                : redirect()->back()->with('error', 'The resignation could not be saved.');
        }
        $this->audit($id ? 'resign.update' : 'resign.create', (string) ($empId ?: $id));

        return $this->done($id ? 'Resignation updated.' : 'Employee marked as resigned.');
    }

    public function reinstate(int $id)
    {
        $db  = db_connect();
        $row = $db->table('personnel_resign')->select('employee_id')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            return $this->wantsJson() ? $this->jsonFail('Record not found.') : redirect()->back()->with('error', 'Record not found.');
        }
        $eid = (int) $row['employee_id'];

        $ok = $this->tx(function ($db) use ($id, $eid): void {
            $db->table('personnel_resign')->where('id', $id)->delete();
            $db->table('personnel_employee')->where('id', $eid)->update(['is_active' => true]);
            $db->table('att_attemployee')->where('emp_id', $eid)->update(['enable_attendance' => true]);
        });

        if (! $ok) {
            return $this->wantsJson() ? $this->jsonFail('Could not reinstate the employee.') : redirect()->back()->with('error', 'Could not reinstate the employee.');
        }
        $this->audit('resign.reinstate', (string) $eid);

        return $this->done('Employee reinstated.');
    }

    private function done(string $message)
    {
        return $this->crudDone(route_to('resign'), 'success', $message, view('admin/resign/_list', $this->listData()));
    }
}

<?php

namespace App\Controllers;

class Holidays extends BaseController
{
    public function index(): string
    {
        $db = db_connect();
        $b  = $db->table('att_holiday h')
            ->select('h.id, h.alias, h.start_date, h.end_date, h.duration_day, h.enable_ot_rule, h.department_id, d.dept_name')
            ->join('personnel_department d', 'd.id = h.department_id', 'left')
            ->orderBy('h.start_date', 'DESC');
        [$rows, $pager] = $this->paged($b, 20);

        return view('admin/holidays/index', [
            'title'       => 'Holidays',
            'subtitle'    => 'Days excluded from the attendance calculation',
            'active'      => 'holidays',
            'rows'        => $rows,
            'pager'       => $pager,
            'departments' => $db->query('SELECT id, dept_name FROM personnel_department ORDER BY dept_name')->getResultArray(),
        ]);
    }

    public function save()
    {
        $rules = [
            'alias'      => 'required|max_length[50]',
            'start_date' => 'required|valid_date',
            'end_date'   => 'required|valid_date',
        ];
        if (! $this->validate($rules)) {
            $id = (int) $this->request->getPost('id');
            return redirect()->back()->with('errors', $this->validator->getErrors())
                ->with('reopen', $id ? 'holEdit' . $id : 'holNew');
        }

        $p     = $this->request;
        $start = $p->getPost('start_date');
        $end   = $p->getPost('end_date');
        if ($end < $start) {
            return redirect()->back()->with('error', 'End date must be on or after the start date.');
        }
        $days = (int) ((strtotime($end) - strtotime($start)) / 86400) + 1;

        $data = [
            'alias'          => $p->getPost('alias'),
            'start_date'     => $start,
            'end_date'       => $end,
            'duration_day'   => $days,
            'enable_ot_rule' => (bool) $p->getPost('enable_ot_rule'),
            'department_id'  => (int) $p->getPost('department_id') ?: null,
        ];

        $db = db_connect();
        $id = (int) $p->getPost('id');
        $ok = $this->dbGuard(fn () => $id
            ? $db->table('att_holiday')->where('id', $id)->update($data)
            : $db->table('att_holiday')->insert($data));
        if ($ok === null) {
            return redirect()->back()->withInput()->with('error', 'Holiday could not be saved — check the department you picked.');
        }
        $this->audit($id ? 'holiday.update' : 'holiday.create', $data['alias']);

        return redirect()->to(route_to('holidays'))->with('success', $id ? 'Holiday updated.' : 'Holiday added.');
    }

    public function delete(int $id)
    {
        db_connect()->table('att_holiday')->where('id', $id)->delete();
        $this->audit('holiday.delete', (string) $id);

        return redirect()->to(route_to('holidays'))->with('success', 'Holiday deleted.');
    }
}

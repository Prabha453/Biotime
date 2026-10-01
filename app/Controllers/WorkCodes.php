<?php

namespace App\Controllers;

/**
 * Work codes (iclock_terminalworkcode) — short reason / task codes a
 * device or a manual-punch correction can be tagged with (e.g. OT-PROJECT,
 * FIELD, TRAINING). Referenced by att_manuallog.work_code_id.
 */
class WorkCodes extends BaseController
{
    public function index(): string
    {
        $db = db_connect();
        $b  = $db->table('iclock_terminalworkcode w')
            ->select("w.id, w.code, w.alias, w.company_id, c.company_name,
                      (SELECT count(*) FROM att_manuallog m WHERE m.work_code_id = w.id) uses")
            ->join('personnel_company c', 'c.id = w.company_id', 'left')
            ->orderBy('w.code', 'ASC');
        [$rows, $pager] = $this->paged($b, 20);

        return view('admin/workcodes/index', [
            'title'    => 'Work Codes',
            'subtitle' => 'Task / reason codes for punches and corrections',
            'active'   => 'workcodes',
            'rows'     => $rows,
            'pager'    => $pager,
            'companies' => $db->query('SELECT id, company_name FROM personnel_company ORDER BY is_default DESC, company_name')->getResultArray(),
        ]);
    }

    public function save()
    {
        if (! $this->validate(['code' => 'required|max_length[8]', 'alias' => 'required|max_length[24]'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'wcForm');
        }
        $p    = $this->request;
        $id   = (int) $p->getPost('id');
        $data = [
            'code'       => trim($p->getPost('code')),
            'alias'      => trim($p->getPost('alias')),
            'company_id' => (int) $p->getPost('company_id') ?: null,
        ];

        $db = db_connect();
        $ok = $this->dbGuard(fn () => $id
            ? $db->table('iclock_terminalworkcode')->where('id', $id)->update($data + ['change_time' => date('Y-m-d H:i:sP'), 'change_user' => session()->get('userName')])
            : $db->table('iclock_terminalworkcode')->insert($data + ['status' => 0, 'create_time' => date('Y-m-d H:i:sP'), 'create_user' => session()->get('userName')]));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Work code could not be saved — the code must be unique.');
        }
        $this->audit($id ? 'workcode.update' : 'workcode.create', $data['code']);

        return redirect()->to(route_to('workcodes'))->with('success', 'Work code saved.');
    }

    public function delete(int $id)
    {
        $db = db_connect();
        if ($db->table('att_manuallog')->where('work_code_id', $id)->countAllResults()) {
            return redirect()->back()->with('error', 'This work code is used by manual-punch records and cannot be deleted.');
        }
        $db->table('iclock_terminalworkcode')->where('id', $id)->delete();
        $this->audit('workcode.delete', (string) $id);

        return redirect()->to(route_to('workcodes'))->with('success', 'Work code deleted.');
    }
}

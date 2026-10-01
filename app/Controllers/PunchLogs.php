<?php

namespace App\Controllers;

use App\Models\TransactionModel;

class PunchLogs extends BaseController
{
    public function index(): string
    {
        $model = new TransactionModel();
        $b     = $model->feed();

        $q     = trim((string) $this->request->getGet('q'));
        $state = $this->request->getGet('state');
        $dev   = (int) $this->request->getGet('device');
        $from  = $this->request->getGet('from');
        $to    = $this->request->getGet('to');
        $src   = $this->request->getGet('src');

        if ($q !== '') {
            $b->groupStart()
                ->like('iclock_transaction.emp_code', $q)
                ->orLike('e.first_name', $q)->orLike('e.last_name', $q)
                ->groupEnd();
        }
        if ($state !== null && $state !== '') {
            $b->where('iclock_transaction.punch_state', (string) (int) $state);
        }
        if ($dev) {
            $b->where('iclock_transaction.terminal_id', $dev);
        }
        if ($src === 'manual') {
            $b->where('iclock_transaction.source', 5);
        }
        if ($from) {
            $b->where('iclock_transaction.punch_time >=', $from . ' 00:00:00');
        }
        if ($to) {
            $b->where('iclock_transaction.punch_time <=', $to . ' 23:59:59');
        }

        $db = db_connect();

        $per = (int) ($this->request->getGet('per') ?: 30);
        if (! in_array($per, self::PER_PAGE_OPTIONS, true)) {
            $per = 30;
        }

        return view('admin/punchlogs/index', [
            'title'       => 'Punch Logs',
            'subtitle'    => 'Raw biometric events + manual corrections',
            'active'      => 'punchlogs',
            'rows'        => $b->orderBy('iclock_transaction.punch_time', 'DESC')->paginate($per),
            'pager'       => $model->pager,
            'filters'     => compact('q', 'state', 'dev', 'from', 'to', 'src'),
            'devices'     => $db->query('SELECT id, alias FROM iclock_terminal ORDER BY alias')->getResultArray(),
            'employees'   => $db->query("SELECT id, emp_code, NULLIF(TRIM(CONCAT(first_name,' ',last_name)),'') name FROM personnel_employee ORDER BY emp_code_digit")->getResultArray(),
            'stateLabels' => config('Attendance')->stateLabels,
        ]);
    }

    public function add()
    {
        $rules = [
            'employee_id' => 'required|is_natural_no_zero',
            'punch_date'  => 'required|valid_date',
            'punch_time'  => 'required',
            'punch_state' => 'required|in_list[0,1,2,3,4,5]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'pAdd');
        }

        $db  = db_connect();
        $emp = $db->query(
            'SELECT e.emp_code, c.company_code
               FROM personnel_employee e
          LEFT JOIN personnel_company c ON c.id = e.company_id
              WHERE e.id = ?',
            [(int) $this->request->getPost('employee_id')]
        )->getRowArray();
        if (! $emp) {
            return redirect()->back()->with('error', 'Employee not found.');
        }

        $ts = $this->request->getPost('punch_date') . ' ' . $this->request->getPost('punch_time') . ':00';

        $ok = $this->dbGuard(fn () => $db->table('iclock_transaction')->insert([
            'company_code'  => $emp['company_code'],
            'emp_code'      => $emp['emp_code'],
            'emp_id'        => (int) $this->request->getPost('employee_id'),
            'punch_time'    => $ts,
            'punch_state'   => (string) (int) $this->request->getPost('punch_state'),
            'verify_type'   => 200,
            'source'        => 5,
            'is_attendance' => 1,
            'upload_time'   => date('Y-m-d H:i:sP'),
            'reserved'      => 'manual:' . session()->get('userName'),
        ]));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Could not add punch (a punch at that exact time may already exist).');
        }
        $this->audit('punch.manual.add', $emp['emp_code'], $ts . ' state=' . (int) $this->request->getPost('punch_state'));

        return redirect()->back()->with('success', 'Manual punch added — recalculated.');
    }

    public function delete(int $id)
    {
        $db  = db_connect();
        $row = $db->query('SELECT source FROM iclock_transaction WHERE id = ?', [$id])->getRowArray();
        if (! $row) {
            return redirect()->back()->with('error', 'Punch not found.');
        }
        if ((int) $row['source'] !== 5) {
            return redirect()->back()->with('error', 'Only manual punches can be deleted here.');
        }
        $db->table('iclock_transaction')->where('id', $id)->delete();
        $this->audit('punch.manual.delete', (string) $id);

        return redirect()->back()->with('success', 'Manual punch removed.');
    }
}

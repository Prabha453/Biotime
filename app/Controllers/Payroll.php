<?php

namespace App\Controllers;

use App\Libraries\PayrollCalculator;

class Payroll extends BaseController
{
    private function ctx(): array
    {
        $now = new PayrollCalculator();

        return [
            'active'   => 'payroll',
            'cfg'      => config('Payroll'),
            'calc'     => $now,
        ];
    }

    private function period(): array
    {
        // defaults to the current month — per client spec, every module
        // defaults to the current date/month
        $y = (int) ($this->request->getGet('y') ?: date('Y'));
        $m = (int) ($this->request->getGet('m') ?: date('n'));
        $dept = (int) $this->request->getGet('dept') ?: null;

        return [$y, $m, $dept];
    }

    private function departments(): array
    {
        return db_connect()->query('SELECT id, dept_name FROM personnel_department ORDER BY dept_name')->getResultArray();
    }

    // ------------------------------------------------------------------
    //  Payslips
    // ------------------------------------------------------------------

    public function index(): string
    {
        [$y, $m, $dept] = $this->period();
        $calc  = new PayrollCalculator();
        $slips = $calc->slips($y, $m, $dept);

        // totals are across every payslip for the month, computed before
        // paginating so they don't shrink to just the visible page
        $totNet = array_sum(array_column($slips, 'net_pay'));
        $totBasic = array_sum(array_column($slips, 'basic_salary'));
        $totDed = array_sum(array_column($slips, 'total_deduction'));

        [$slips, $pager] = $this->pagedArray($slips, 25);

        return view('admin/payroll/index', [
            'title'       => 'Payroll',
            'subtitle'    => 'Attendance-driven payslips',
            'active'      => 'payroll',
            'tab'         => 'payslips',
            'y'           => $y,
            'm'           => $m,
            'dept'        => $dept,
            'slips'       => $slips,
            'pager'       => $pager,
            'departments' => $this->departments(),
            'cfg'         => config('Payroll'),
            'totals'      => ['net' => $totNet, 'basic' => $totBasic, 'ded' => $totDed],
        ]);
    }

    public function generate()
    {
        $y    = (int) $this->request->getPost('y');
        $m    = (int) $this->request->getPost('m');
        $dept = (int) $this->request->getPost('dept') ?: null;
        if ($y < 2000 || $m < 1 || $m > 12) {
            return redirect()->back()->with('error', 'Pick a valid month and year.');
        }

        $res = $this->dbGuard(fn () => (new PayrollCalculator())->run($y, $m, $dept));
        if ($res === null) {
            return redirect()->back()->with('error', 'Payroll run failed — nothing was written.');
        }
        $this->audit('payroll.run', "{$y}-{$m}", "{$res['count']} slips, net " . number_format($res['net'], 2));

        return redirect()->to(route_to('payroll') . '?' . http_build_query(['y' => $y, 'm' => $m, 'dept' => $dept]))
            ->with('success', "Payroll run complete — {$res['count']} payslips, net "
                . config('Payroll')->currency . ' ' . number_format($res['net'], 2) . '.');
    }

    public function slip(int $empId)
    {
        $y = (int) ($this->request->getGet('y') ?: date('Y'));
        $m = (int) ($this->request->getGet('m') ?: date('n'));

        $calc   = new PayrollCalculator();
        $stored = $calc->slip($empId, $y, $m);
        $live   = $calc->compute($empId, $y, $m);

        // prefer the persisted breakdown when present
        $breakdown = $live;
        if ($stored && ! empty($stored['format_dict'])) {
            $fd = json_decode($stored['format_dict'], true) ?: [];
            $breakdown = array_merge($live, [
                'base_salary'      => (float) $stored['basic_salary'],
                'net_pay'          => (float) $stored['net_pay'],
                'total_earnings'   => (float) $stored['total_salary'],
                'gross'            => (float) $stored['total_salary'],
                'total_deductions' => (float) $stored['total_deduction'],
                'earnings'         => $fd['earnings'] ?? $live['earnings'],
                'deductions'       => $fd['deductions'] ?? $live['deductions'],
                'generated_at'     => $fd['generated_at'] ?? null,
            ]);
        }

        return view('admin/payroll/slip', [
            'title'    => 'Payslip · ' . emp_name($live['employee']),
            'active'   => 'payroll',
            's'        => $breakdown,
            'stored'   => $stored,
            'y'        => $y,
            'm'        => $m,
            'cfg'      => config('Payroll'),
        ]);
    }

    // ------------------------------------------------------------------
    //  Salary structures
    // ------------------------------------------------------------------

    /** formula kind (matches payroll_salarystructure_{kind}formula / {kind}formula_id) => [table, label] */
    private const FORMULA_KINDS = [
        'overtime'     => ['payroll_overtimeformula',     'Overtime'],
        'exception'    => ['payroll_exceptionformula',    'Exception (late / early)'],
        'leave'        => ['payroll_leaveformula',         'Leave'],
        'deduction'    => ['payroll_deductionformula',     'Deduction'],
        'increasement' => ['payroll_increasementformula',  'Increase'],
    ];

    public function structures(): string
    {
        $db = db_connect();
        $b  = $db->table('payroll_salarystructure s')
            ->select("s.id, s.salary_amount, s.effective_date, s.salary_remark, s.employee_id,
                      e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name, e.first_name")
            ->join('personnel_employee e', 'e.id = s.employee_id')
            ->where('s.status', 0)
            ->orderBy('e.emp_code_digit', 'ASC')
            ->orderBy('s.effective_date', 'DESC');

        [$rows, $pager] = $this->paged($b, 20);

        foreach ($rows as &$r) {
            foreach (array_keys(self::FORMULA_KINDS) as $kind) {
                $r['formulas'][$kind] = $db->query(
                    "SELECT f.id FROM payroll_salarystructure_{$kind}formula j WHERE j.salarystructure_id = ?",
                    [$r['id']]
                )->getResultArray();
            }
        }
        unset($r);

        $formulaLists = [];
        foreach (self::FORMULA_KINDS as $kind => [$table, ]) {
            $formulaLists[$kind] = $db->query("SELECT id, name FROM {$table} ORDER BY name")->getResultArray();
        }

        return view('admin/payroll/structures', [
            'title'     => 'Salary structures',
            'subtitle'  => 'Base monthly salary per employee (effective-dated)',
            'active'    => 'payroll',
            'tab'       => 'salary',
            'rows'      => $rows,
            'pager'     => $pager,
            'employees' => $this->employeePickList(),
            'cfg'       => config('Payroll'),
            'formulaKinds' => self::FORMULA_KINDS,
            'formulaLists' => $formulaLists,
        ]);
    }

    public function saveStructure()
    {
        $rules = [
            'employee_id'    => 'required|is_natural_no_zero',
            'salary_amount'  => 'required|greater_than_equal_to[0]',
            'effective_date' => 'required|valid_date',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'salNew');
        }

        $p  = $this->request;
        $db = db_connect();
        $id = (int) $p->getPost('id');
        $data = [
            'employee_id'    => (int) $p->getPost('employee_id'),
            'salary_amount'  => (float) $p->getPost('salary_amount'),
            'effective_date' => $p->getPost('effective_date'),
            'salary_remark'  => $p->getPost('salary_remark') ?: null,
            'status'         => 0,
        ];
        $ok = $this->dbGuard(function () use ($db, $id, $data) {
            if ($id) {
                return $db->table('payroll_salarystructure')->where('id', $id)->update($data);
            }

            return $db->table('payroll_salarystructure')->insert($data + ['create_time' => date('Y-m-d H:i:sP')]);
        });
        if ($ok === null) {
            return redirect()->back()->with('error', 'Salary structure could not be saved — check the employee.');
        }
        $ssId = $id ?: (int) $db->insertID();

        // formula attachment (ZKBioTime: each formula kind is opt-in PER
        // salary structure via a join table) — replace-all on every save.
        foreach (array_keys(self::FORMULA_KINDS) as $kind) {
            $picked = array_values(array_filter(array_map('intval', (array) $p->getPost($kind . '_ids'))));
            $db->table("payroll_salarystructure_{$kind}formula")->where('salarystructure_id', $ssId)->delete();
            if ($picked !== []) {
                $rows = array_map(static fn ($fid) => ['salarystructure_id' => $ssId, "{$kind}formula_id" => $fid], $picked);
                $db->table("payroll_salarystructure_{$kind}formula")->insertBatch($rows);
            }
        }

        $this->audit($id ? 'payroll.structure.update' : 'payroll.structure.create',
            'emp=' . (int) $p->getPost('employee_id'), $data['salary_amount'] . ' from ' . $data['effective_date']);

        return redirect()->to(route_to('payroll.structures'))->with('success', $id ? 'Salary structure updated.' : 'Salary structure added.');
    }

    public function deleteStructure(int $id)
    {
        db_connect()->table('payroll_salarystructure')->where('id', $id)->delete();
        $this->audit('payroll.structure.delete', (string) $id);

        return redirect()->to(route_to('payroll.structures'))->with('success', 'Salary structure removed.');
    }

    // ------------------------------------------------------------------
    //  Loans & advances
    // ------------------------------------------------------------------

    public function loans(): string
    {
        $db = db_connect();
        $loanB = $db->table('payroll_emploan l')
            ->select("l.id, l.loan_amount, l.loan_time, l.refund_cycle, l.per_cycle_refund,
                      l.loan_clean_time, l.remark, l.employee_id,
                      e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name, e.first_name")
            ->join('personnel_employee e', 'e.id = l.employee_id')
            ->where('l.status', 0)
            ->orderBy('l.loan_time', 'DESC');
        [$loans, $loanPager] = $this->paged($loanB, 15, 'loans');

        return view('admin/payroll/loans', [
            'title'     => 'Loan',
            'subtitle'  => 'Recurring loan installments, deducted every payroll run until settled',
            'active'    => 'payroll',
            'tab'       => 'loans',
            'loans'     => $loans,
            'loanPager' => $loanPager,
            'employees' => $this->employeePickList(),
            'cfg'       => config('Payroll'),
        ]);
    }

    public function advances(): string
    {
        $db = db_connect();
        $advB = $db->table('payroll_salaryadvance a')
            ->select("a.id, a.advance_amount, a.advance_time, a.advance_remark, a.employee_id,
                      e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name, e.first_name")
            ->join('personnel_employee e', 'e.id = a.employee_id')
            ->orderBy('a.advance_time', 'DESC');
        [$adv, $advPager] = $this->paged($advB, 15, 'adv');

        return view('admin/payroll/advances', [
            'title'     => 'Salary Advance',
            'subtitle'  => 'One-off advances, deducted in full in the month they fall in',
            'active'    => 'payroll',
            'tab'       => 'advances',
            'advances'  => $adv,
            'advPager'  => $advPager,
            'employees' => $this->employeePickList(),
            'cfg'       => config('Payroll'),
        ]);
    }

    public function saveLoan()
    {
        $rules = [
            'employee_id'     => 'required|is_natural_no_zero',
            'loan_amount'     => 'required|greater_than[0]',
            'loan_time'       => 'required|valid_date',
            'refund_cycle'    => 'required|is_natural_no_zero',
            'per_cycle_refund'=> 'required|greater_than[0]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'loanNew');
        }

        $p  = $this->request;
        $ok = $this->dbGuard(fn () => db_connect()->table('payroll_emploan')->insert([
            'employee_id'     => (int) $p->getPost('employee_id'),
            'loan_amount'     => (float) $p->getPost('loan_amount'),
            'loan_time'       => $p->getPost('loan_time') . ' 00:00:00+00',
            'refund_cycle'    => (int) $p->getPost('refund_cycle'),
            'per_cycle_refund'=> (float) $p->getPost('per_cycle_refund'),
            'remark'          => $p->getPost('remark') ?: null,
            'status'          => 0,
            'create_time'     => date('Y-m-d H:i:sP'),
        ]));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Loan could not be recorded — check the employee.');
        }
        $this->audit('payroll.loan.create', 'emp=' . (int) $p->getPost('employee_id'), (float) $p->getPost('loan_amount'));

        return redirect()->to(route_to('payroll.loans'))->with('success', 'Loan recorded.');
    }

    public function settleLoan(int $id)
    {
        db_connect()->table('payroll_emploan')->where('id', $id)
            ->update(['loan_clean_time' => date('Y-m-d H:i:sP')]);
        $this->audit('payroll.loan.settle', (string) $id);

        return redirect()->to(route_to('payroll.loans'))->with('success', 'Loan marked as settled — no further installments.');
    }

    public function deleteLoan(int $id)
    {
        db_connect()->table('payroll_emploan')->where('id', $id)->delete();
        $this->audit('payroll.loan.delete', (string) $id);

        return redirect()->to(route_to('payroll.loans'))->with('success', 'Loan removed.');
    }

    public function saveAdvance()
    {
        $rules = [
            'employee_id'    => 'required|is_natural_no_zero',
            'advance_amount' => 'required|greater_than[0]',
            'advance_time'   => 'required|valid_date',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'advNew');
        }

        $p  = $this->request;
        $ok = $this->dbGuard(fn () => db_connect()->table('payroll_salaryadvance')->insert([
            'employee_id'    => (int) $p->getPost('employee_id'),
            'advance_amount' => (float) $p->getPost('advance_amount'),
            'advance_time'   => $p->getPost('advance_time') . ' 00:00:00+00',
            'advance_remark' => $p->getPost('advance_remark') ?: null,
        ]));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Advance could not be recorded — check the employee.');
        }
        $this->audit('payroll.advance.create', 'emp=' . (int) $p->getPost('employee_id'), (float) $p->getPost('advance_amount'));

        return redirect()->to(route_to('payroll.advances'))->with('success', 'Advance recorded — deducted in that month\'s run.');
    }

    public function deleteAdvance(int $id)
    {
        db_connect()->table('payroll_salaryadvance')->where('id', $id)->delete();
        $this->audit('payroll.advance.delete', (string) $id);

        return redirect()->to(route_to('payroll.advances'))->with('success', 'Advance removed.');
    }

    // ------------------------------------------------------------------
    //  One-off adjustments  (extra increase / deduction / reimbursement)
    // ------------------------------------------------------------------

    /** kind => [table, page route name] */
    private const ADJ_KINDS = [
        'increase'      => ['payroll_extraincrease', 'payroll.increases'],
        'deduction'     => ['payroll_extradeduction', 'payroll.deductions'],
        'reimbursement' => ['payroll_reimbursement', 'payroll.reimbursements'],
    ];

    public function increases(): string
    {
        $db = db_connect();
        $incB = $db->table('payroll_extraincrease x')
            ->select("x.id, x.amount, x.issued_time, x.remark, sp.name AS type_name,
                      e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name")
            ->join('personnel_employee e', 'e.id = x.employee_id')
            ->join('payroll_specialpayment sp', 'sp.id = x.special_type_id', 'left')
            ->orderBy('x.issued_time', 'DESC');
        [$inc, $incPager] = $this->paged($incB, 12, 'inc');

        return view('admin/payroll/increases', [
            'title'     => 'Extra Increase',
            'subtitle'  => 'Bonuses, allowances, back-pay — added to earnings for the month they fall in',
            'active'    => 'payroll',
            'tab'       => 'increases',
            'inc'       => $inc,  'incPager' => $incPager,
            'employees' => $this->employeePickList(),
            'types'     => $db->query('SELECT id, name FROM payroll_specialpayment ORDER BY name')->getResultArray(),
            'cfg'       => config('Payroll'),
        ]);
    }

    public function deductions(): string
    {
        $db = db_connect();
        $dedB = $db->table('payroll_extradeduction x')
            ->select("x.id, x.amount, x.issued_time, x.remark, sp.name AS type_name,
                      e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name")
            ->join('personnel_employee e', 'e.id = x.employee_id')
            ->join('payroll_specialpayment sp', 'sp.id = x.special_type_id', 'left')
            ->orderBy('x.issued_time', 'DESC');
        [$ded, $dedPager] = $this->paged($dedB, 12, 'ded');

        return view('admin/payroll/deductions', [
            'title'     => 'Extra Deduction',
            'subtitle'  => 'Fines, recoveries and other one-off deductions — subtracted from net pay for the month they fall in',
            'active'    => 'payroll',
            'tab'       => 'deductions',
            'ded'       => $ded,  'dedPager' => $dedPager,
            'employees' => $this->employeePickList(),
            'types'     => $db->query('SELECT id, name FROM payroll_specialpayment ORDER BY name')->getResultArray(),
            'cfg'       => config('Payroll'),
        ]);
    }

    public function reimbursements(): string
    {
        $rmbB = db_connect()->table('payroll_reimbursement x')
            ->select("x.id, x.rmb_amount, x.rmb_time, x.rmb_remark,
                      e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name")
            ->join('personnel_employee e', 'e.id = x.employee_id')
            ->orderBy('x.rmb_time', 'DESC');
        [$rmb, $rmbPager] = $this->paged($rmbB, 12, 'rmb');

        return view('admin/payroll/reimbursements', [
            'title'     => 'Reimbursement',
            'subtitle'  => 'Expense claims — added to earnings for the month they fall in',
            'active'    => 'payroll',
            'tab'       => 'reimbursements',
            'rmb'       => $rmb,  'rmbPager' => $rmbPager,
            'employees' => $this->employeePickList(),
            'cfg'       => config('Payroll'),
        ]);
    }

    public function saveAdjustment(string $kind)
    {
        if (! isset(self::ADJ_KINDS[$kind])) {
            return redirect()->back()->with('error', 'Unknown adjustment type.');
        }
        [$table, $route] = self::ADJ_KINDS[$kind];
        $rules = ['employee_id' => 'required|is_natural_no_zero', 'amount' => 'required|greater_than[0]', 'issued_date' => 'required|valid_date'];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'adj_' . $kind);
        }
        $p   = $this->request;
        $amt = (float) $p->getPost('amount');
        $ts  = $p->getPost('issued_date') . ' 12:00:00+00';
        $typeId = (int) $p->getPost('special_type_id') ?: null;

        $data = $kind === 'reimbursement'
            ? ['employee_id' => (int) $p->getPost('employee_id'), 'rmb_amount' => $amt, 'rmb_time' => $ts, 'rmb_remark' => $p->getPost('remark') ?: null]
            : ['employee_id' => (int) $p->getPost('employee_id'), 'amount' => $amt, 'issued_time' => $ts, 'remark' => $p->getPost('remark') ?: null, 'special_type_id' => $typeId, 'status' => 0];

        $ok = $this->dbGuard(fn () => db_connect()->table($table)->insert($data));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Adjustment could not be saved — check the employee.');
        }
        $this->audit('payroll.adjustment.' . $kind, 'emp=' . (int) $p->getPost('employee_id'), (string) $amt);

        return redirect()->to(route_to($route))->with('success', ucfirst($kind) . ' recorded — applied to that month\'s run.');
    }

    public function deleteAdjustment(string $kind, int $id)
    {
        if (! isset(self::ADJ_KINDS[$kind])) {
            return redirect()->back()->with('error', 'Unknown adjustment type.');
        }
        [$table, $route] = self::ADJ_KINDS[$kind];
        db_connect()->table($table)->where('id', $id)->delete();
        $this->audit('payroll.adjustment.' . $kind . '.delete', (string) $id);

        return redirect()->to(route_to($route))->with('success', 'Adjustment removed.');
    }

    public function saveAdjustmentType()
    {
        if (! $this->validate(['name' => 'required|max_length[255]'])) {
            return redirect()->back()->with('error', 'A name is required.')->with('reopen', 'adjType');
        }
        $ok = $this->dbGuard(fn () => db_connect()->table('payroll_specialpayment')->insert([
            'name'       => trim($this->request->getPost('name')),
            'remark'     => $this->request->getPost('remark') ?: null,
            'created_at' => date('Y-m-d H:i:sP'),
        ]));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Category could not be saved — the name must be unique.');
        }
        $this->audit('payroll.adjustment.type.create', (string) $this->request->getPost('name'));

        return redirect()->back()->with('success', 'Category added.');
    }

    // ------------------------------------------------------------------
    //  Bank / payment details
    // ------------------------------------------------------------------

    public function bank(): string
    {
        $rows = db_connect()->query(
            "SELECT pr.id, pr.payment_mode, pr.payment_type, pr.bank_name, pr.bank_account,
                    pr.personnel_id, pr.agent_id, pr.agent_account, pr.employee_id,
                    e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name, e.first_name
               FROM payroll_emppayrollprofile pr
               JOIN personnel_employee e ON e.id = pr.employee_id
           ORDER BY e.emp_code_digit ASC"
        )->getResultArray();

        return view('admin/payroll/bank', [
            'title'    => 'Bank details',
            'subtitle' => 'Payment method and bank account per employee',
            'active'   => 'payroll',
            'tab'      => 'bank',
            'rows'     => $rows,
        ]);
    }

    public function saveBank(int $empId)
    {
        if (! $this->validate(['payment_mode' => 'required|in_list[1,2,3]'])) {
            return redirect()->back()->with('error', 'Pick a valid payment mode.');
        }
        $p  = $this->request;
        $db = db_connect();
        $data = [
            'payment_mode'  => (int) $p->getPost('payment_mode'),
            'payment_type'  => (int) $p->getPost('payment_type') ?: 1,
            'bank_name'     => (string) $p->getPost('bank_name'),
            'bank_account'  => (string) $p->getPost('bank_account'),
            'personnel_id'  => (string) $p->getPost('personnel_id'),
            'agent_id'      => (string) $p->getPost('agent_id'),
            'agent_account' => (string) $p->getPost('agent_account'),
        ];
        $exists = $db->table('payroll_emppayrollprofile')->where('employee_id', $empId)->countAllResults();
        $ok = $this->dbGuard(fn () => $exists
            ? $db->table('payroll_emppayrollprofile')->where('employee_id', $empId)->update($data)
            : $db->table('payroll_emppayrollprofile')->insert($data + ['employee_id' => $empId]));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Bank details could not be saved.');
        }
        $this->audit('payroll.bank.update', 'emp=' . $empId);

        return redirect()->to(route_to('payroll.bank'))->with('success', 'Bank details saved.');
    }

    // ------------------------------------------------------------------
    //  Export
    // ------------------------------------------------------------------

    public function export()
    {
        [$y, $m, $dept] = $this->period();
        $slips = (new PayrollCalculator())->slips($y, $m, $dept);
        $cur   = config('Payroll')->currency;

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['Code', 'Employee', 'Department', 'Basic (' . $cur . ')', 'Additions', 'Deductions', 'Loan', 'Advance', 'Net (' . $cur . ')']);
        foreach ($slips as $s) {
            fputcsv($out, [
                $s['emp_code'],
                $s['name'] ?: $s['first_name'],
                $s['dept_name'],
                number_format((float) $s['basic_salary'], 2, '.', ''),
                number_format((float) $s['total_increase'], 2, '.', ''),
                number_format((float) $s['total_deduction'], 2, '.', ''),
                number_format((float) $s['loan_deduction'], 2, '.', ''),
                number_format((float) $s['advance_deduction'], 2, '.', ''),
                number_format((float) $s['net_pay'], 2, '.', ''),
            ]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->response
            ->setHeader('Content-Type', 'text/csv')
            ->setHeader('Content-Disposition', "attachment; filename=payroll-{$y}-{$m}.csv")
            ->setBody($csv);
    }

    private function employeePickList(): array
    {
        return db_connect()->query(
            "SELECT e.id, e.emp_code,
                    NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name, e.first_name
               FROM personnel_employee e
              WHERE e.is_active = true
           ORDER BY e.emp_code_digit ASC, e.emp_code"
        )->getResultArray();
    }

    // ------------------------------------------------------------------
    //  Report — ZKBioTime's Payroll ▸ Report: Salary Structure, Increases,
    //  Deductions, Salary Details, Attendance Parameters
    // ------------------------------------------------------------------

    private const REPORT_TABS = [
        'structure'  => 'Salary Structure',
        'increases'  => 'Increases',
        'deductions' => 'Deductions',
        'details'    => 'Salary Details',
        'attendance' => 'Attendance Parameters',
    ];

    public function reports(string $tab = 'structure'): string
    {
        if (! isset(self::REPORT_TABS[$tab])) {
            $tab = 'structure';
        }
        $db = db_connect();
        [$y, $m, $dept] = $this->period();
        $d  = [
            'title'    => 'Payroll Report',
            'subtitle' => self::REPORT_TABS[$tab],
            'active'   => 'payroll',
            'tab'      => 'reports',
            'reportTab'=> $tab,
            'reportTabs' => self::REPORT_TABS,
            'y' => $y, 'm' => $m, 'dept' => $dept,
            'departments' => $this->departments(),
            'cfg' => config('Payroll'),
        ];

        if ($tab === 'structure') {
            $b = $db->table('payroll_salarystructure s')
                ->select("s.salary_amount, s.effective_date, s.salary_remark,
                          e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name, e.first_name, de.dept_name")
                ->join('personnel_employee e', 'e.id = s.employee_id')
                ->join('personnel_department de', 'de.id = e.department_id', 'left')
                ->where('s.status', 0)
                ->orderBy('e.emp_code_digit', 'ASC')
                ->orderBy('s.effective_date', 'DESC');
            if ($dept) {
                $b->where('e.department_id', $dept);
            }
            [$d['rows'], $d['pager']] = $this->paged($b, 25);
        } elseif ($tab === 'increases' || $tab === 'deductions') {
            $table = $tab === 'increases' ? 'payroll_extraincrease' : 'payroll_extradeduction';
            $b = $db->table("{$table} x")
                ->select("x.amount, x.issued_time, x.remark, sp.name AS type_name,
                          e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name, e.first_name, de.dept_name")
                ->join('personnel_employee e', 'e.id = x.employee_id')
                ->join('personnel_department de', 'de.id = e.department_id', 'left')
                ->join('payroll_specialpayment sp', 'sp.id = x.special_type_id', 'left')
                ->where('EXTRACT(YEAR FROM x.issued_time)', $y)
                ->where('EXTRACT(MONTH FROM x.issued_time)', $m)
                ->orderBy('x.issued_time', 'DESC');
            if ($dept) {
                $b->where('e.department_id', $dept);
            }
            [$d['rows'], $d['pager']] = $this->paged($b, 25);
            $d['total'] = array_sum(array_column($d['rows'], 'amount'));
        } elseif ($tab === 'details') {
            $calc = new PayrollCalculator();
            $d['rows'] = $calc->slips($y, $m, $dept);
        } else { // attendance parameters
            $calc = new PayrollCalculator();
            $rows = [];
            foreach ($calc->slips($y, $m, $dept) as $s) {
                $fd = json_decode((string) ($s['format_dict'] ?? '{}'), true) ?: [];
                $rows[] = $s + $fd;
            }
            $d['rows'] = $rows;
        }

        return view('admin/payroll/reports', $d);
    }
}

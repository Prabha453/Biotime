<?php

namespace App\Controllers;

use App\Libraries\FormulaEval;

/**
 * Payroll setup — the ZKBioTime "Payroll ▸ Setting" area:
 *   formulas (OT / leave / exception / deduction / increase) ·
 *   tax slabs · social-security slabs · annual expense exemptions ·
 *   payslip settings (company stamp, slip date, OT multipliers).
 *
 * Formulas are opt-in: PayrollCalculator uses them only when
 * base_sysparam(payroll, use_formulas) = 1, otherwise the flat
 * Config\Payroll multipliers apply (unchanged behaviour).
 */
class PayrollConfig extends BaseController
{
    private const TABS = [
        'tax'        => 'Tax Deduction',
        'social'     => 'Social Security',
        'exemptions' => 'Expense Exemptions',
        'settings'   => 'Payslip Settings',
    ];

    /** formula kind => [table, needs pay_code, label] */
    private const KINDS = [
        'overtime'  => ['payroll_overtimeformula',      true,  'Overtime'],
        'leave'     => ['payroll_leaveformula',         true,  'Leave'],
        'exception' => ['payroll_exceptionformula',     true,  'Exception (late / early)'],
        'deduction' => ['payroll_deductionformula',     false, 'Deduction'],
        'increase'  => ['payroll_increasementformula',  false, 'Increase'],
    ];

    public function index(string $tab = 'tax'): string
    {
        if (! isset(self::TABS[$tab])) {
            $tab = 'tax';
        }
        $db = db_connect();
        $d  = [
            'title'    => 'Payroll Setup',
            'subtitle' => self::TABS[$tab],
            'active'   => 'payrollconfig',
            'tab'      => $tab,
            'tabs'     => self::TABS,
            'tokens'   => FormulaEval::tokens(),
        ];

        if ($tab === 'tax' || $tab === 'social') {
            $table       = $tab === 'tax' ? 'payroll_taxdeduction' : 'payroll_socialsecuritydeduction';
            $row         = $db->query("SELECT * FROM {$table} ORDER BY id LIMIT 1")->getRowArray();
            $d['row']    = $row ?: ['country_code' => 'default', 'used' => 0, 'data' => '{}'];
            $parsed      = json_decode($d['row']['data'] ?: '{}', true) ?: [];
            // ZKBioTime's own JS names the bracket keys differently per
            // screen — tax_deduction_setting.html reads
            // min_tax_deduction/max_tax_deduction/percent_tax_deduction,
            // social_security_deduction_setting.html reads plain
            // min/max/percent. Normalize to min/max/percent here so the view
            // (shared between both tabs) only ever deals with one shape;
            // saveSlabs() converts back to the real per-table shape on write.
            if ($tab === 'tax' && isset($parsed['min_max_percent'])) {
                $parsed['min_max_percent'] = array_map(static fn ($s) => [
                    'min'     => $s['min_tax_deduction']     ?? ($s['min'] ?? ''),
                    'max'     => $s['max_tax_deduction']     ?? ($s['max'] ?? ''),
                    'percent' => $s['percent_tax_deduction'] ?? ($s['percent'] ?? ''),
                ], $parsed['min_max_percent']);
            }
            $d['parsed'] = $parsed;
        } elseif ($tab === 'exemptions') {
            $b = $db->table('payroll_empexpenseexemption x')
                ->select("x.*, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS emp_name, e.emp_code")
                ->join('personnel_employee e', 'e.id = x.employee_id', 'left')
                ->orderBy('x.year', 'DESC')->orderBy('x.issued_time', 'DESC');
            [$d['rows'], $d['pager']] = $this->paged($b, 20);
            $d['employees'] = $db->query("SELECT id, emp_code, NULLIF(TRIM(CONCAT(first_name,' ',last_name)),'') name FROM personnel_employee WHERE is_active=true ORDER BY emp_code_digit")->getResultArray();
        } else { // settings
            $d['kv'] = [
                'use_formulas'   => $this->sysparam('use_formulas', '0'),
                'ot_normal_x'    => $this->sysparam('ot_normal_x', (string) config('Payroll')->overtimeMultiplier),
                'ot_weekend_x'   => $this->sysparam('ot_weekend_x', (string) config('Payroll')->weekendOtMultiplier),
                'ot_holiday_x'   => $this->sysparam('ot_holiday_x', (string) config('Payroll')->holidayOtMultiplier),
                'slip_day'       => $this->sysparam('slip_day', '1'),
                'company_stamp'  => $this->sysparam('company_stamp', ''),
                'payslip_note'   => $this->sysparam('payslip_note', ''),
            ];
        }

        return view('admin/payrollconfig/index', $d);
    }

    // ---------------------------------------------------------- formulas

    public function formula(string $kind): string
    {
        if (! isset(self::KINDS[$kind])) {
            $kind = 'overtime';
        }
        [$table, $needsPc, $label] = self::KINDS[$kind];
        $db = db_connect();

        return view('admin/payrollconfig/formula', [
            'title'    => $label . ' Formula',
            'subtitle' => 'Priced when formula-driven payroll is enabled — see Payslip Settings',
            'active'   => 'payrollconfig',
            'tab'      => $kind,
            'kinds'    => self::KINDS,
            'kind'     => $kind,
            'table'    => $table,
            'needsPc'  => $needsPc,
            'label'    => $label,
            'tokens'   => FormulaEval::tokens(),
            'paycodes' => $db->query('SELECT id, code, name FROM att_paycode ORDER BY display_order')->getResultArray(),
            'types'    => $db->query('SELECT id, name FROM payroll_specialpayment ORDER BY name')->getResultArray(),
            'formulas' => $db->query(
                "SELECT f.*" . ($needsPc ? ', pc.code AS pay_code' : '') . "
                   FROM {$table} f" . ($needsPc ? ' LEFT JOIN att_paycode pc ON pc.id = f.pay_code_id' : '') . '
               ORDER BY f.name'
            )->getResultArray(),
            'useFormulas' => (int) $this->sysparam('use_formulas', '0') === 1,
        ]);
    }

    public function saveFormula(string $kind)
    {
        if (! isset(self::KINDS[$kind])) {
            return redirect()->back()->with('error', 'Unknown formula type.');
        }
        [$table, $needsPc] = self::KINDS[$kind];

        if (! $this->validate(['name' => 'required|max_length[30]', 'formula' => 'required|max_length[100]'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'fx-' . $kind);
        }
        $p       = $this->request;
        $id      = (int) $p->getPost('id');
        $formula = trim($p->getPost('formula'));

        if (FormulaEval::run($formula, ['Basic Salary' => 5000, 'NOT' => 10, 'WOT' => 5, 'HOT' => 2, 'L' => 30, 'E' => 15, 'A' => 1, 'Present' => 22, 'Leave' => 1, 'WorkDays' => 26]) === null) {
            return redirect()->back()->with('error', 'That formula is not valid arithmetic — use only + - * / ( ) and {Tokens}.')->with('reopen', 'fx-' . $kind);
        }

        $data = ['name' => trim($p->getPost('name')), 'formula' => $formula, 'remark' => $p->getPost('remark') ?: null];
        if ($needsPc) {
            $data['pay_code_id'] = (int) $p->getPost('pay_code_id') ?: null;
        }
        if ($kind === 'increase') {
            $data['apply_mode']      = (int) $p->getPost('apply_mode');
            $data['special_type_id'] = (int) $p->getPost('special_type_id') ?: null;
        }

        $rid = $this->saveRow($table, $data, $id);
        if ($rid === null) {
            return redirect()->back()->with('error', 'Formula could not be saved — the name must be unique.');
        }
        $this->audit($id ? 'payroll.formula.update' : 'payroll.formula.create', $kind . ':' . $data['name']);

        return redirect()->to(route_to('payrollconfig.formula', $kind))->with('success', 'Formula saved.');
    }

    public function deleteFormula(string $kind, int $id)
    {
        if (! isset(self::KINDS[$kind])) {
            return redirect()->back();
        }
        $this->deleteRow(self::KINDS[$kind][0], $id);
        $this->audit('payroll.formula.delete', $kind . ':' . $id);

        return redirect()->to(route_to('payrollconfig.formula', $kind))->with('success', 'Formula deleted.');
    }

    // -------------------------------------------------------- tax / social

    public function saveSlabs(string $which)
    {
        $table = $which === 'tax' ? 'payroll_taxdeduction' : 'payroll_socialsecuritydeduction';
        $p     = $this->request;

        $mins = (array) $p->getPost('min');
        $maxs = (array) $p->getPost('max');
        $pcts = (array) $p->getPost('pct');
        // ZKBioTime's own tax_deduction_setting.html reads
        // min_tax_deduction/max_tax_deduction/percent_tax_deduction from
        // this JSON; social_security_deduction_setting.html reads plain
        // min/max/percent. Write whichever shape the real product for this
        // table actually parses, so a slab table saved here isn't silently
        // blank when opened in the real ZKBioTime admin.
        [$kMin, $kMax, $kPct] = $which === 'tax'
            ? ['min_tax_deduction', 'max_tax_deduction', 'percent_tax_deduction']
            : ['min', 'max', 'percent'];
        $slabs = [];
        foreach ($mins as $i => $m) {
            if ($m === '' && ($maxs[$i] ?? '') === '') {
                continue;
            }
            $slabs[] = [$kMin => (float) $m, $kMax => (float) ($maxs[$i] ?? 0), $kPct => (float) ($pcts[$i] ?? 0)];
        }
        // Tax has one top rate above a threshold (maximum/maximum_percent);
        // Social Security instead has a flat floor AND ceiling deduction
        // (minimum/minimum_social_security below the bands,
        // maximum/maximum_social_security above them) — genuinely different
        // field sets in the real product, not just a naming difference.
        $payload = json_encode($which === 'tax' ? [
            'min_max_percent' => $slabs,
            'maximum'         => (float) $p->getPost('maximum'),
            'maximum_percent' => (float) $p->getPost('maximum_percent'),
        ] : [
            'minimum'                 => (float) $p->getPost('minimum'),
            'minimum_social_security' => (float) $p->getPost('minimum_social_security'),
            'min_max_percent'         => $slabs,
            'maximum'                 => (float) $p->getPost('maximum'),
            'maximum_social_security' => (float) $p->getPost('maximum_social_security'),
        ]);

        $db  = db_connect();
        $row = $db->table($table)->orderBy('id')->get(1)->getRowArray();
        $data = ['country_code' => $p->getPost('country_code') ?: 'default', 'used' => (int) (bool) $p->getPost('used'), 'data' => $payload];
        $ok  = $this->dbGuard(static fn () => $row
            ? $db->table($table)->where('id', $row['id'])->update($data)
            : $db->table($table)->insert($data));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Slabs could not be saved.');
        }
        $this->audit('payroll.' . $which . '.update', $which . ' slabs');

        return redirect()->to(route_to('payrollconfig.tab', $which))->with('success', ucfirst($which) . ' slabs saved.');
    }

    // --------------------------------------------------------- exemptions

    public function saveExemption()
    {
        if (! $this->validate(['exemption_name' => 'required|max_length[255]', 'amount' => 'required|numeric', 'issued_time' => 'required|valid_date'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'exForm');
        }
        $p    = $this->request;
        $id   = (int) $p->getPost('id');
        // ZKBioTime's own form only asks for Issued Time — year is derived
        // from it, not entered separately (this table is shared with the
        // real app, so a mismatched year would confuse its own reports).
        $issuedTime = $p->getPost('issued_time') . ' 00:00:00+00';
        $data = [
            'exemption_name' => trim($p->getPost('exemption_name')),
            'amount'         => (float) $p->getPost('amount'),
            'year'           => (int) date('Y', strtotime((string) $p->getPost('issued_time'))),
            'issued_time'    => $issuedTime,
            'employee_id'    => (int) $p->getPost('employee_id') ?: null,
            'remark'         => $p->getPost('remark') ?: null,
        ];
        $rid = $this->saveRow('payroll_empexpenseexemption', $data, $id);
        if ($rid === null) {
            return redirect()->back()->with('error', 'Exemption could not be saved.');
        }
        $this->audit($id ? 'payroll.exemption.update' : 'payroll.exemption.create', $data['exemption_name']);

        return redirect()->to(route_to('payrollconfig.tab', 'exemptions'))->with('success', 'Exemption saved.');
    }

    public function deleteExemption(int $id)
    {
        $this->deleteRow('payroll_empexpenseexemption', $id);
        $this->audit('payroll.exemption.delete', (string) $id);

        return redirect()->to(route_to('payrollconfig.tab', 'exemptions'))->with('success', 'Exemption deleted.');
    }

    // ----------------------------------------------------------- settings

    public function saveSettings()
    {
        $p  = $this->request;
        $kv = [
            'use_formulas'  => (int) (bool) $p->getPost('use_formulas'),
            'ot_normal_x'   => (float) $p->getPost('ot_normal_x'),
            'ot_weekend_x'  => (float) $p->getPost('ot_weekend_x'),
            'ot_holiday_x'  => (float) $p->getPost('ot_holiday_x'),
            'slip_day'      => max(1, min(28, (int) $p->getPost('slip_day'))),
            'company_stamp' => trim((string) $p->getPost('company_stamp')),
            'payslip_note'  => trim((string) $p->getPost('payslip_note')),
        ];
        foreach ($kv as $name => $value) {
            $this->setSysparam($name, (string) $value);
        }
        $this->audit('payroll.settings.update', 'payslip settings');

        return redirect()->to(route_to('payrollconfig.tab', 'settings'))->with('success', 'Payslip settings saved.');
    }

    // ----------------------------------------------------------- internals

    private function sysparam(string $name, string $default): string
    {
        $row = db_connect()->table('base_sysparam')->where('para_type', 'payroll')->where('para_name', $name)->get()->getRowArray();

        return $row['para_value'] ?? $default;
    }

    private function setSysparam(string $name, string $value): void
    {
        $db  = db_connect();
        $has = $db->table('base_sysparam')->where('para_type', 'payroll')->where('para_name', $name)->countAllResults();
        $row = ['para_type' => 'payroll', 'para_name' => $name, 'para_value' => mb_substr($value, 0, 250)];
        $this->dbGuard(static fn () => $has
            ? $db->table('base_sysparam')->where('para_type', 'payroll')->where('para_name', $name)->update($row)
            : $db->table('base_sysparam')->insert($row));
    }
}

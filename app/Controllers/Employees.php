<?php

namespace App\Controllers;

use App\Libraries\AttendanceCalculator;
use App\Models\EmployeeModel;

class Employees extends BaseController
{
    private const PER_OPTS   = [10, 15, 25, 50, 100];
    private const SORT_MAP    = [
        'name'   => ['name', 'ASC'],
        'recent' => ['personnel_employee.hire_date', 'DESC'],
        'code'   => ['personnel_employee.emp_code_digit', 'ASC'],
    ];

    /**
     * Employee directory. The results card (table / grid + pager) is a
     * partial that is returned on its own for every AJAX filter / page / sort
     * request, so the shell (KPIs, filter bar) is rendered just once.
     */
    public function index(): string
    {
        $model = new EmployeeModel();

        $f = [
            'q'      => trim((string) $this->request->getGet('q')),
            'dept'   => (int) $this->request->getGet('dept'),
            'pos'    => (int) $this->request->getGet('pos'),
            'status' => (string) $this->request->getGet('status'),   // '' | active | resigned | inactive
            'sort'   => (string) $this->request->getGet('sort'),     // '' | code | name | recent
        ];

        $b = $this->filtered($model, $f);

        // ZKBioTime advanced Filter builder (?fb[N][field|op|val])
        $fbApplied = $this->filterBuilder($b, [
            'emp_code'   => ['personnel_employee.emp_code', 'text'],
            'first_name' => ['personnel_employee.first_name', 'text'],
            'last_name'  => ['personnel_employee.last_name', 'text'],
            'email'      => ['personnel_employee.email', 'text'],
            'mobile'     => ['personnel_employee.mobile', 'text'],
            'card_no'    => ['personnel_employee.card_no', 'text'],
            'hire_date'  => ['personnel_employee.hire_date', 'date'],
            'birthday'   => ['personnel_employee.birthday', 'date'],
            'gender'     => ['personnel_employee.gender', 'select'],
            'emp_type'   => ['personnel_employee.emp_type', 'select'],
            'is_active'  => ['personnel_employee.is_active', 'bool'],
        ]);

        [$col, $dir] = self::SORT_MAP[$f['sort']] ?? self::SORT_MAP['code'];
        $b->orderBy($col, $dir);

        $per = (int) ($this->request->getGet('per') ?: 10);
        if (! in_array($per, self::PER_OPTS, true)) {
            $per = 10;
        }

        $list = [
            'rows'      => $b->paginate($per),
            'pager'     => $model->pager,
            'fbApplied' => $fbApplied,
        ] + $f;

        // AJAX → just the swappable results region
        if ($this->request->isAJAX()) {
            return view('admin/employees/_list', $list);
        }

        return view('admin/employees/index', $list + [
            'title'       => 'Employees',
            'subtitle'    => 'Enrolled on the biometric device',
            'active'      => 'employees',
            'departments' => $model->departments(),
            'positions'   => $model->positions(),
            'areas'       => db_connect()->query('SELECT id, area_name FROM personnel_area ORDER BY area_name')->getResultArray(),
            'stats'       => $this->headcount(),
        ]);
    }

    /** Apply the directory filters to the withMeta() builder. */
    private function filtered(EmployeeModel $model, array $f)
    {
        $b = $model->withMeta();

        if ($f['q'] !== '') {
            // case-insensitive (Postgres LIKE is case-sensitive without this)
            $b->groupStart()
                ->like('personnel_employee.emp_code', $f['q'], 'both', null, true)
                ->orLike('personnel_employee.first_name', $f['q'], 'both', null, true)
                ->orLike('personnel_employee.last_name', $f['q'], 'both', null, true)
                ->orLike('personnel_employee.email', $f['q'], 'both', null, true)
                ->orLike('personnel_employee.mobile', $f['q'], 'both', null, true)
                ->groupEnd();
        }
        if ($f['dept']) {
            $b->where('personnel_employee.department_id', $f['dept']);
        }
        if ($f['pos']) {
            $b->where('personnel_employee.position_id', $f['pos']);
        }
        if ($f['status'] === 'active') {
            $b->where('personnel_employee.id NOT IN (SELECT employee_id FROM personnel_resign)', null, false);
        } elseif ($f['status'] === 'resigned') {
            $b->where('personnel_employee.id IN (SELECT employee_id FROM personnel_resign)', null, false);
        } elseif ($f['status'] === 'inactive') {
            $b->where('personnel_employee.is_active', false);
        }

        return $b;
    }

    /** One query for the four KPI tiles (unaffected by the row filters). */
    private function headcount(): array
    {
        return db_connect()->query(
            "SELECT count(*) total,
                    count(*) FILTER (WHERE e.id NOT IN (SELECT employee_id FROM personnel_resign)) active,
                    count(*) FILTER (WHERE e.id IN (SELECT employee_id FROM personnel_resign)) resigned,
                    count(*) FILTER (WHERE NOT e.is_active) inactive,
                    count(*) FILTER (WHERE e.hire_date >= (CURRENT_DATE - INTERVAL '90 days')) recent
               FROM personnel_employee e"
        )->getRowArray() ?: ['total' => 0, 'active' => 0, 'resigned' => 0, 'inactive' => 0, 'recent' => 0];
    }

    public function view(int $id): string
    {
        $calc = new AttendanceCalculator();
        $emp  = $calc->employee($id);
        if ($emp === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        // defaults to the current month — per client spec, every module
        // defaults to the current date/month
        $month = (int) ($this->request->getGet('m') ?: date('n'));
        $year  = (int) ($this->request->getGet('y') ?: date('Y'));

        $data             = $calc->monthly($id, $year, $month);
        $data['title']    = emp_name($emp);
        $data['subtitle'] = 'Employee #' . $emp['emp_code'];
        $data['active']   = 'employees';
        $data['emp']      = $emp;
        $data['year']     = $year;
        $data['month']    = $month;
        $data['daysInMonth'] = (int) date('t', mktime(0, 0, 0, $month, 1, $year));

        // weekends in the selected month, per the resolved weekend-day rule
        // (not just the raw weekend1/weekend2 columns) — same fallback/kv
        // resolution the calculator itself uses.
        $weekendDays  = \App\Libraries\AttPolicy::load(db_connect())->weekendDays;
        $weekendCount = 0;
        for ($day = 1; $day <= $data['daysInMonth']; $day++) {
            if (in_array((int) date('w', mktime(0, 0, 0, $month, $day, $year)), $weekendDays, true)) {
                $weekendCount++;
            }
        }
        $data['weekendCount'] = $weekendCount;

        return view('admin/employees/view', $data);
    }

    /** psnl_config_setting.num_emp_code — employee codes must be numeric. */
    private function numericCodeRequired(): bool
    {
        return ! empty($this->personnelConfig()['num_emp_code']);
    }

    /** psnl_config_setting.emp_default_password — self-service password for a new hire left blank. */
    private function defaultSelfPassword(): string
    {
        return (string) ($this->personnelConfig()['emp_default_password'] ?? '');
    }

    private function personnelConfig(): array
    {
        $row = db_connect()->table('base_systemsetting')->select('value')
            ->where('name', 'psnl_config_setting')->get()->getRowArray();
        $cfg = $row ? json_decode((string) $row['value'], true) : [];

        return is_array($cfg) ? $cfg : [];
    }

    public function form(?int $id = null): string
    {
        $db  = db_connect();
        $emp = $id ? $db->table('personnel_employee')->where('id', $id)->get()->getRowArray() : null;
        if ($id && ! $emp) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        // ZKBioTime custom attributes (personnel_employeecustomattribute) + this
        // employee's saved values (personnel_employeeextrainfo.value = JSON id=>value)
        $customAttrs = $db->query('SELECT id, attr_name, attr_type, attr_value FROM personnel_employeecustomattribute WHERE enable = true ORDER BY attr_name')->getResultArray();
        $extra       = [];
        if ($id) {
            $row = $db->table('personnel_employeeextrainfo')->select('value')->where('employee_id', $id)->get()->getRowArray();
            $extra = $row ? (array) json_decode((string) $row['value'], true) : [];
        }

        return view('admin/employees/form', [
            'title'       => $id ? 'Edit employee' : 'Add employee',
            'active'      => 'employees',
            'emp'         => $emp,
            'customAttrs' => $customAttrs,
            'extra'       => $extra,
            'calendars'   => $id ? array_column($db->table('personnel_employeecalendar')->select('calendar')->where('employee_id', $id)->get()->getResultArray(), 'calendar') : [],
            'att'         => $id ? $db->table('att_attemployee')->where('emp_id', $id)->get()->getRowArray() : null,
            'pay'         => $id ? $db->table('payroll_emppayrollprofile')->where('employee_id', $id)->get()->getRowArray() : null,
            'employment'  => $id ? $db->table('personnel_employment')->where('employee_id', $id)->get()->getRowArray() : null,
            'areaId'      => $id ? (int) ($db->table('personnel_employee_area')->select('area_id')->where('employee_id', $id)->get()->getRowArray()['area_id'] ?? 0) : 0,
            'companies'   => $db->query('SELECT id, company_name FROM personnel_company ORDER BY is_default DESC, company_name')->getResultArray(),
            'departments' => $db->query('SELECT id, dept_name FROM personnel_department ORDER BY dept_name')->getResultArray(),
            'positions'   => $db->query('SELECT id, position_name FROM personnel_position ORDER BY position_name')->getResultArray(),
            'areas'       => $db->query('SELECT id, area_name FROM personnel_area ORDER BY area_name')->getResultArray(),
            'shifts'      => $db->query('SELECT id, alias FROM att_attshift ORDER BY alias')->getResultArray(),
            'leaveGroups' => $db->query('SELECT id, name FROM att_leavegroup ORDER BY name')->getResultArray(),
            'attGroups'   => $db->query('SELECT id, name FROM att_attgroup ORDER BY name')->getResultArray(),
            'flowRoles'   => $db->query('SELECT id, role_name FROM workflow_workflowrole ORDER BY role_name')->getResultArray(),
            'flowRoleIds' => $id ? array_map('intval', array_column(
                $db->table('personnel_employee_flow_role')->select('workflowrole_id')->where('employee_id', $id)->get()->getResultArray(),
                'workflowrole_id'
            )) : [],
            'numEmpCode'  => $this->numericCodeRequired(),
            'superiors'   => $db->query(
                "SELECT id, emp_code, NULLIF(TRIM(CONCAT(first_name,' ',last_name)),'') name
                   FROM personnel_employee WHERE is_active = true" . ($id ? " AND id <> {$id}" : '') . '
               ORDER BY emp_code_digit'
            )->getResultArray(),
        ]);
    }

    public function save()
    {
        $id = (int) $this->request->getPost('id');

        $codeRule = 'required|max_length[20]|is_unique[personnel_employee.emp_code,id,' . $id . ']';
        if ($this->numericCodeRequired()) {
            $codeRule .= '|numeric';
        }
        if (! $this->validate([
            'emp_code'      => $codeRule,
            'first_name'    => 'permit_empty|max_length[100]',
            'department_id' => 'required|is_natural_no_zero',
            'area_id'       => 'required|is_natural_no_zero',
            'email'         => 'permit_empty|valid_email',
            'photo'         => 'permit_empty|is_image[photo]|max_size[photo,4096]|ext_in[photo,jpg,jpeg,png]',
        ], [
            'emp_code' => ['numeric' => 'The employee code must be numeric (Personnel ▸ Configurations).'],
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $p    = $this->request;
        $code = trim((string) $p->getPost('emp_code'));

        // ZKBio: an employment effective date cannot precede the hire date
        $hire = trim((string) $p->getPost('hire_date'));
        $effF = trim((string) $p->getPost('effective_start'));
        if ($hire !== '' && $effF !== '' && $effF < $hire) {
            return redirect()->back()->withInput()->with('errors', [
                'effective_start' => 'The effective start date cannot be earlier than the hire date.',
            ]);
        }
        $nn   = static fn (string $k) => (int) $p->getPost($k) ?: null;
        $sn   = static fn (string $k) => ($v = trim((string) $p->getPost($k))) !== '' ? $v : null;

        // ---- profile photo ----------------------------------------------
        $photoPath = null;
        $file      = $p->getFile('photo');
        if ($file && $file->isValid() && ! $file->hasMoved()) {
            $dir  = FCPATH . 'assets/photos';
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $code) ?: ('emp' . $id);
            $name = $safe . '.' . strtolower($file->getExtension() ?: 'jpg');
            try {
                $file->move($dir, $name, true);
                $photoPath = 'photo/' . $name;
            } catch (\Throwable $e) {
                log_message('error', 'employee photo upload: ' . $e->getMessage());
            }
        }

        $emp = [
            'emp_code'        => $code,
            'emp_code_digit'  => is_numeric($code) ? (int) $code : null,
            'first_name'      => $sn('first_name'),
            'last_name'       => $sn('last_name'),
            'nickname'        => $sn('nickname'),
            'company_id'      => $nn('company_id'),
            'department_id'   => $nn('department_id'),
            'position_id'     => $nn('position_id'),
            'superior_id'     => $nn('superior_id'),
            'leave_group'     => $nn('leave_group'),
            'emp_type'        => $nn('emp_type'),
            'hire_date'       => $sn('hire_date'),
            'gender'          => $sn('gender'),
            'birthday'        => $sn('birthday'),
            'mobile'          => $sn('mobile'),
            'contact_tel'     => $sn('contact_tel'),
            'office_tel'      => $sn('office_tel'),
            'email'           => $sn('email'),
            'national'        => $sn('national'),
            'religion'        => $sn('religion'),
            'title'           => $sn('title'),
            'address'         => $sn('address'),
            'city'            => $sn('city'),
            'postcode'        => $sn('postcode'),
            'verify_mode'     => (int) $p->getPost('verify_mode'),
            'dev_privilege'   => (int) $p->getPost('dev_privilege'),
            'card_no'         => $sn('card_no'),
            'device_password' => $sn('device_password'),
            'enroll_sn'       => $sn('enroll_sn'),
            'acc_group'       => $sn('acc_group'),
            'acc_timezone'    => $sn('acc_timezone'),
            'app_status'      => (int) $p->getPost('app_status'),
            'app_role'        => (int) ($p->getPost('app_role') ?: 1),
            'is_active'       => (bool) $p->getPost('is_active'),
            'enable_payroll'  => (bool) $p->getPost('enable_payroll'),
            'update_time'     => date('Y-m-d H:i:sP'),
        ];
        $selfPw = trim((string) $p->getPost('self_password'));
        if ($selfPw === '' && ! $id) {
            // psnl_config_setting.emp_default_password — a new hire left
            // blank still gets a working self-service login.
            $selfPw = $this->defaultSelfPassword();
        }
        if ($selfPw !== '') {
            $emp['self_password'] = \App\Models\AuthUserModel::makeDjangoPassword($selfPw);
        }
        if ($photoPath !== null) {
            $emp['photo'] = $photoPath;
        } elseif ($p->getPost('photo_remove')) {
            $emp['photo'] = null;
        }

        $attRow = [
            'enable_attendance'   => (bool) $p->getPost('enable_attendance'),
            'enable_schedule'     => (bool) $p->getPost('enable_schedule'),
            'enable_overtime'     => (bool) $p->getPost('enable_overtime'),
            'enable_holiday'      => (bool) $p->getPost('enable_holiday'),
            'enable_compensatory' => (bool) $p->getPost('enable_compensatory'),
            'group_id'            => $nn('att_group_id'),
        ];
        $payRow = [
            'payment_mode'  => (int) ($p->getPost('payment_mode') ?: 1),
            'payment_type'  => (int) ($p->getPost('payment_type') ?: 1),
            'bank_name'     => $sn('bank_name'),
            'bank_account'  => $sn('bank_account'),
            'agent_id'         => $sn('agent_id'),
            'agent_account'    => $sn('agent_account'),
            'personnel_id'     => $sn('payroll_number'),
            'national_num'     => $sn('national_num'),
            'internal_emp_num' => $sn('internal_emp_num'),
        ];
        $areaId = (int) $p->getPost('area_id');
        $empId  = $id;

        // snapshot the device-relevant fields so we can push a USERINFO update
        // to the terminals when they change (ZKBioTime's emp_change_flag)
        $rdb    = db_connect();
        $before = $id ? $rdb->table('personnel_employee')
            ->select('department_id, company_id, card_no, verify_mode, dev_privilege, first_name, last_name')
            ->where('id', $id)->get()->getRowArray() : null;
        $beforeArea = $id ? (int) ($rdb->table('personnel_employee_area')->select('area_id')
            ->where('employee_id', $id)->get()->getRowArray()['area_id'] ?? 0) : 0;

        $ok = $this->tx(function ($db) use ($id, &$empId, $emp, $attRow, $payRow, $areaId, $p): void {
            $now = date('Y-m-d H:i:sP');

            if (empty($emp['company_id'])) {
                $emp['company_id'] = (int) ($db->query('SELECT id FROM personnel_company ORDER BY id LIMIT 1')->getRow()->id ?? 1);
            }

            if ($id) {
                $db->table('personnel_employee')->where('id', $id)->update($emp);
                $this->upsert($db, 'att_attemployee', ['emp_id' => $id], $attRow + ['change_time' => $now], ['status' => 0]);
                $this->upsert($db, 'payroll_emppayrollprofile', ['employee_id' => $id], $payRow, []);
            } else {
                $db->table('personnel_employee')->insert($emp + ['status' => 0, 'create_time' => $now]);
                $empId = (int) $db->insertID();
                $db->table('att_attemployee')->insert($attRow + ['status' => 0, 'emp_id' => $empId]);
                $db->table('personnel_employeeprofile')->insert(['column_order' => '', 'disabled_fields' => '', 'preferences' => '', 'emp_id' => $empId]);
                $db->table('payroll_emppayrollprofile')->insert($payRow + ['employee_id' => $empId]);
            }

            if ($areaId) {
                $db->table('personnel_employee_area')->where('employee_id', $empId)->delete();
                $db->table('personnel_employee_area')->insert(['employee_id' => $empId, 'area_id' => $areaId]);
            }

            // probation → effective dates (personnel_employment, ZKBio's Employment model)
            $etype = (int) ($emp['emp_type'] ?? 0);
            $effFrom = $p->getPost('effective_start');
            $effTo   = $p->getPost('effective_end');
            if ($etype >= 2 && $effFrom && $effTo) {
                $this->upsert($db, 'personnel_employment', ['employee_id' => $empId], [
                    'employment_type' => $etype, 'start_date' => $effFrom, 'end_date' => $effTo,
                ], []);
            } else {
                $db->table('personnel_employment')->where('employee_id', $empId)->delete();
            }

            // workflow roles — ZKBioTime's flow_role is a M2M (an employee can
            // sit in several approval roles at once)
            $roleIds = array_values(array_unique(array_filter(array_map('intval', (array) $p->getPost('flow_role_id')))));
            $db->table('personnel_employee_flow_role')->where('employee_id', $empId)->delete();
            foreach ($roleIds as $rid) {
                $db->table('personnel_employee_flow_role')->insert(['employee_id' => $empId, 'workflowrole_id' => $rid]);
            }

            // custom attribute values → personnel_employeeextrainfo (JSON id => value)
            $attr = array_filter((array) $p->getPost('attr'), static fn ($v) => trim((string) $v) !== '');
            if ($attr) {
                $json = json_encode(array_map('strval', $attr));
                $this->upsert($db, 'personnel_employeeextrainfo', ['employee_id' => $empId], ['value' => $json], []);
            } else {
                $db->table('personnel_employeeextrainfo')->where('employee_id', $empId)->delete();
            }

            // holiday calendars → personnel_employeecalendar (one row per name)
            $cals = array_values(array_unique(array_filter(array_map(
                static fn ($c) => trim((string) $c),
                preg_split('/[\r\n,]+/', (string) $p->getPost('calendars')) ?: []
            ))));
            $db->table('personnel_employeecalendar')->where('employee_id', $empId)->delete();
            foreach ($cals as $c) {
                $db->table('personnel_employeecalendar')->insert([
                    'employee_id' => $empId, 'calendar' => mb_substr($c, 0, 100),
                    'status' => 0, 'create_time' => $now, 'change_time' => $now,
                ]);
            }

            if ((int) $p->getPost('shift_id') && $p->getPost('sched_from') && $p->getPost('sched_to')) {
                $db->table('att_attschedule')->insert([
                    'employee_id' => $empId, 'shift_id' => (int) $p->getPost('shift_id'),
                    'start_date'  => $p->getPost('sched_from'), 'end_date' => $p->getPost('sched_to'),
                ]);
            }
        });

        if (! $ok) {
            return redirect()->back()->withInput()
                ->with('error', 'Employee could not be saved — check the department / position / area you picked still exist.');
        }

        // push the record to the employee's terminals when a device-relevant
        // field changed (or the employee is brand new)
        $changed = ! $id || $before === null
            || (int) ($before['department_id'] ?? 0) !== (int) ($emp['department_id'] ?? 0)
            || (int) ($before['company_id'] ?? 0) !== (int) ($emp['company_id'] ?? 0)
            || (string) ($before['card_no'] ?? '') !== (string) ($emp['card_no'] ?? '')
            || (int) ($before['verify_mode'] ?? 0) !== (int) ($emp['verify_mode'] ?? 0)
            || (int) ($before['dev_privilege'] ?? 0) !== (int) ($emp['dev_privilege'] ?? 0)
            || (string) ($before['first_name'] ?? '') !== (string) ($emp['first_name'] ?? '')
            || (string) ($before['last_name'] ?? '') !== (string) ($emp['last_name'] ?? '')
            || $beforeArea !== $areaId;
        if ($changed) {
            $this->dbGuard(fn () => $this->queueDeviceSync(db_connect(), [$empId], 'update'));
        }

        $this->audit($id ? 'employee.update' : 'employee.create', $code, 'id=' . $empId);

        return redirect()->to(route_to('employee.view', $empId))
            ->with('success', $id ? 'Employee updated.' : 'Employee created.');
    }

    // =====================================================================
    //  Bulk actions (multi-select on the list) — ZKBioTime's employee toolbar
    // =====================================================================

    /** @var array<string,string> op => human label (for the audit trail) */
    private const BULK_OPS = [
        'dept'        => 'Adjust department',
        'position'    => 'Position transfer',
        'area_set'    => 'Adjust area',
        'area_add'    => 'Assign area',
        'probation'   => 'Pass probation',
        'app_enable'  => 'Enable app',
        'app_disable' => 'Disable app',
        'resign'      => 'Resignation',
        'resync'      => 'Re-sync to device',
        'reupload'    => 'Re-upload from device',
        'clear_bio'   => 'Delete biometric templates',
        'delete'      => 'Delete',
    ];

    public function bulk()
    {
        $p   = $this->request;
        $op  = (string) $p->getPost('op');
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $p->getPost('ids')))));

        if (! isset(self::BULK_OPS[$op])) {
            return $this->bulkBack('Unknown action.', true);
        }
        if (! $ids) {
            return $this->bulkBack('Select at least one employee.', true);
        }

        $db  = db_connect();
        $now = date('Y-m-d H:i:sP');
        $val = null;

        switch ($op) {
            case 'dept':
                $val = (int) $p->getPost('department_id');
                if (! $val || ! $db->table('personnel_department')->where('id', $val)->countAllResults()) {
                    return $this->bulkBack('Pick a department.', true);
                }
                break;
            case 'position':
                $val = (int) $p->getPost('position_id') ?: null;
                break;
            case 'area_set':
            case 'area_add':
                $val = (int) $p->getPost('area_id');
                if (! $val || ! $db->table('personnel_area')->where('id', $val)->countAllResults()) {
                    return $this->bulkBack('Pick an area.', true);
                }
                break;
        }

        $ok = $this->tx(function ($db) use ($op, $ids, $val, $now, $p) {
            switch ($op) {
                case 'dept':
                    $db->table('personnel_employee')->whereIn('id', $ids)->update(['department_id' => $val, 'update_time' => $now]);
                    $this->queueDeviceSync($db, $ids, 'update');   // ZKBio Personnel Transfer → push to device
                    break;

                case 'position':
                    $db->table('personnel_employee')->whereIn('id', $ids)->update(['position_id' => $val, 'update_time' => $now]);
                    break;

                case 'area_set':
                    $db->table('personnel_employee_area')->whereIn('employee_id', $ids)->delete();
                    foreach ($ids as $eid) {
                        $db->table('personnel_employee_area')->insert(['employee_id' => $eid, 'area_id' => $val]);
                        $db->table('personnel_assignareaemployee')->insert(['employee_id' => $eid, 'area_id' => $val, 'assigned_time' => $now]);
                    }
                    $this->queueDeviceSync($db, $ids, 'update');
                    break;

                case 'area_add':
                    foreach ($ids as $eid) {
                        $has = $db->table('personnel_employee_area')->where(['employee_id' => $eid, 'area_id' => $val])->countAllResults();
                        if (! $has) {
                            $db->table('personnel_employee_area')->insert(['employee_id' => $eid, 'area_id' => $val]);
                        }
                        $db->table('personnel_assignareaemployee')->insert(['employee_id' => $eid, 'area_id' => $val, 'assigned_time' => $now]);
                    }
                    break;

                case 'probation':
                    // Probation / Temporary → Permanent, drop the employment window
                    $db->table('personnel_employee')->whereIn('id', $ids)->whereIn('emp_type', [2, 3])
                        ->update(['emp_type' => 1, 'update_time' => $now]);
                    $db->table('personnel_employment')->whereIn('employee_id', $ids)->delete();
                    break;

                case 'app_enable':
                case 'app_disable':
                    $db->table('personnel_employee')->whereIn('id', $ids)
                        ->update(['app_status' => $op === 'app_enable' ? 1 : 0, 'update_time' => $now]);
                    break;

                case 'resign':
                    $date   = $p->getPost('resign_date') ?: date('Y-m-d');
                    $type   = (int) ($p->getPost('resign_type') ?: 1);
                    $reason = $p->getPost('reason') ?: null;
                    $disatt = (bool) $p->getPost('disable_att');
                    foreach ($ids as $eid) {
                        $db->table('personnel_resign')->where('employee_id', $eid)->delete();
                        $db->table('personnel_resign')->insert([
                            'employee_id' => $eid, 'resign_date' => $date, 'resign_type' => $type,
                            'disableatt' => $disatt, 'reason' => $reason,
                        ]);
                    }
                    $db->table('personnel_employee')->whereIn('id', $ids)->update(['is_active' => false, 'update_time' => $now]);
                    if ($disatt) {
                        $db->table('att_attemployee')->whereIn('emp_id', $ids)->update(['enable_attendance' => false]);
                    }
                    break;

                case 'clear_bio':
                    $db->table('iclock_biodata')->whereIn('employee_id', $ids)->delete();
                    $db->table('iclock_biophoto')->whereIn('employee_id', $ids)->delete();
                    // fall through to also re-queue a device sync
                case 'resync':
                case 'reupload':
                    $this->queueDeviceSync($db, $ids, $op === 'reupload' ? 'query' : 'update');
                    break;

                case 'delete':
                    // only rows with zero history — silently skip the rest
                    $safe = array_values(array_filter($ids, fn ($id) => ! (int) $db->query(
                        'SELECT (SELECT count(*) FROM iclock_transaction WHERE emp_id = ?)
                              + (SELECT count(*) FROM workflow_workflowinstance WHERE employee_id = ?)
                              + (SELECT count(*) FROM payroll_payrollpayload WHERE employee_id = ?) c',
                        [$id, $id, $id]
                    )->getRow()->c));
                    if ($safe) {
                        foreach ([
                            'att_attschedule' => 'employee_id', 'att_attemployee' => 'emp_id',
                            'personnel_employeeprofile' => 'emp_id', 'personnel_employee_area' => 'employee_id',
                            'personnel_employeeextrainfo' => 'employee_id', 'personnel_employeecalendar' => 'employee_id',
                            'personnel_employee_flow_role' => 'employee_id', 'personnel_employment' => 'employee_id',
                            'personnel_assignareaemployee' => 'employee_id', 'personnel_resign' => 'employee_id',
                            'payroll_emppayrollprofile' => 'employee_id',
                        ] as $t => $col) {
                            $db->table($t)->whereIn($col, $safe)->delete();
                        }
                        $db->table('personnel_employee')->whereIn('id', $safe)->delete();
                    }
                    session()->setFlashdata('_bulk_deleted', count($safe));
                    session()->setFlashdata('_bulk_skipped', count($ids) - count($safe));
                    break;
            }
        });

        if (! $ok) {
            return $this->bulkBack('The bulk action could not be completed.', true);
        }
        $this->audit('employee.bulk.' . $op, self::BULK_OPS[$op], count($ids) . ' employees');

        if ($op === 'delete') {
            $d = (int) session()->getFlashdata('_bulk_deleted');
            $s = (int) session()->getFlashdata('_bulk_skipped');
            return $this->bulkBack("Deleted {$d} employee(s)." . ($s ? " {$s} kept (history exists — use Resign)." : ''));
        }

        return $this->bulkBack(self::BULK_OPS[$op] . ' applied to ' . count($ids) . ' employee(s).');
    }

    /** Queue a per-employee device command on every terminal in the employee's area(s). */
    /**
     * Queue per-terminal commands for the given employees.
     *   query  → DATA QUERY USERINFO   (pull the record back from the device)
     *   update → DATA UPDATE USERINFO   (push the record to the device)
     *   delete → DATA DELETE USERINFO   (remove the PIN from the device)
     * $pin overrides the code sent (used when the code has just changed).
     */
    private function queueDeviceSync($db, array $ids, string $mode, ?string $pin = null): void
    {
        $rows = $db->query(
            "SELECT DISTINCT e.emp_code, t.id term_id
               FROM personnel_employee e
               JOIN personnel_employee_area ea ON ea.employee_id = e.id
               JOIN iclock_terminal t          ON t.area_id = ea.area_id
              WHERE e.id IN (" . implode(',', array_map('intval', $ids)) . ')'
        )->getResultArray();
        $verb = ['query' => 'QUERY', 'delete' => 'DELETE'][$mode] ?? 'UPDATE';
        $now  = date('Y-m-d H:i:sP');
        foreach ($rows as $r) {
            $db->table('iclock_terminalcommand')->insert([
                'content'     => "DATA {$verb} USERINFO PIN=" . ($pin ?? $r['emp_code']),
                'commit_time' => $now,
                'terminal_id' => (int) $r['term_id'],
            ]);
        }
    }

    private function bulkBack(string $msg, bool $error = false)
    {
        return redirect()->to(route_to('employees'))->with($error ? 'error' : 'success', $msg);
    }

    /** insert-or-update a child row keyed by $where. */
    private function upsert($db, string $table, array $where, array $data, array $onInsert): void
    {
        $b = $db->table($table);
        if ((clone $b)->where($where)->countAllResults()) {
            $b->where($where)->update($data);
        } else {
            $b->insert($data + $where + $onInsert);
        }
    }

    public function resign(int $id)
    {
        $p  = $this->request;
        $ok = $this->tx(function ($db) use ($id, $p) {
            $db->table('personnel_resign')->where('employee_id', $id)->delete();
            $db->table('personnel_resign')->insert([
                'employee_id' => $id,
                'resign_date' => $p->getPost('resign_date') ?: date('Y-m-d'),
                'resign_type' => 1,
                'disableatt'  => (bool) $p->getPost('disable_att'),
                'reason'      => $p->getPost('reason') ?: null,
            ]);
            $db->table('personnel_employee')->where('id', $id)->update(['is_active' => false]);
        });
        if (! $ok) {
            return redirect()->back()->with('error', 'Could not record the resignation.');
        }
        $this->audit('employee.resign', (string) $id);

        return redirect()->back()->with('success', 'Employee marked as resigned.');
    }

    /**
     * Change an employee's code (device PIN). ZKBioTime's "Change Employee Code"
     * action — cascades the denormalised emp_code into the transaction / device
     * link tables and queues a device delete-old + push-new.
     */
    public function changeCode(int $id)
    {
        $new = trim((string) $this->request->getPost('new_code'));
        if ($new === '' || ! preg_match('/^[A-Za-z0-9_-]{1,30}$/', $new)) {
            return redirect()->back()->with('error', 'Enter a valid new code (letters, digits, - or _; max 30).');
        }
        $db  = db_connect();
        $row = $db->table('personnel_employee')->select('emp_code')->where('id', $id)->get()->getRowArray();
        if (! $row) {
            return redirect()->back()->with('error', 'Employee not found.');
        }
        $old = $row['emp_code'];
        if ($new === $old) {
            return redirect()->back()->with('error', 'That is already the employee code.');
        }
        if ($db->table('personnel_employee')->where('emp_code', $new)->countAllResults()) {
            return redirect()->back()->with('error', "Code {$new} is already in use.");
        }

        $ok = $this->tx(function ($db) use ($id, $old, $new) {
            $db->table('personnel_employee')->where('id', $id)->update([
                'emp_code'       => $new,
                'emp_code_digit' => is_numeric($new) ? (int) $new : null,
                'update_time'    => date('Y-m-d H:i:sP'),
            ]);
            foreach (['iclock_transaction', 'iclock_terminalemployee', 'ep_eptransaction', 'meeting_meetingtransaction', 'sync_employee'] as $t) {
                $db->table($t)->where('emp_code', $old)->update(['emp_code' => $new]);
            }
            // device: drop the old PIN, push the record back under the new one
            $this->queueDeviceSync($db, [$id], 'delete', $old);
            $this->queueDeviceSync($db, [$id], 'update');
        });
        if (! $ok) {
            return redirect()->back()->with('error', 'Could not change the employee code.');
        }
        $this->audit('employee.change_code', (string) $id, "{$old} → {$new}");

        return redirect()->to(route_to('employee.edit', $id))->with('success', "Employee code changed from {$old} to {$new}.");
    }

    public function reinstate(int $id)
    {
        $ok = $this->tx(function ($db) use ($id) {
            $db->table('personnel_resign')->where('employee_id', $id)->delete();
            $db->table('personnel_employee')->where('id', $id)->update(['is_active' => true]);
        });
        if (! $ok) {
            return redirect()->back()->with('error', 'Could not reinstate the employee.');
        }
        $this->audit('employee.reinstate', (string) $id);

        return redirect()->back()->with('success', 'Employee reinstated.');
    }

    public function delete(int $id)
    {
        $db = db_connect();

        // Blockers: any row that FK-references this employee and carries history.
        $blockers = [
            'punch records'      => 'SELECT count(*) c FROM iclock_transaction WHERE emp_id = ?',
            'access privileges'  => 'SELECT count(*) c FROM acc_accprivilege WHERE employee_id = ?',
            'payslips'           => 'SELECT count(*) c FROM payroll_payrollpayload WHERE employee_id = ?',
            'leave / requests'   => 'SELECT count(*) c FROM workflow_workflowinstance WHERE employee_id = ?',
            'salary structures'  => 'SELECT count(*) c FROM payroll_salarystructure WHERE employee_id = ?',
        ];
        foreach ($blockers as $label => $sql) {
            if ((int) $db->query($sql, [$id])->getRow()->c > 0) {
                return redirect()->back()->with('error',
                    "Cannot delete — {$label} exist for this employee. Use Resign instead.");
            }
        }

        $ok = $this->tx(function ($db) use ($id) {
            foreach ([
                'att_attschedule' => 'employee_id', 'att_attemployee' => 'emp_id',
                'personnel_employeeprofile' => 'emp_id', 'personnel_employee_area' => 'employee_id',
                'personnel_employeeextrainfo' => 'employee_id', 'personnel_employeecalendar' => 'employee_id',
                'personnel_employee_flow_role' => 'employee_id', 'personnel_employment' => 'employee_id',
                'personnel_assignareaemployee' => 'employee_id',
                'personnel_resign' => 'employee_id', 'payroll_emppayrollprofile' => 'employee_id',
            ] as $t => $col) {
                $db->table($t)->where($col, $id)->delete();
            }
            $db->table('personnel_employee')->where('id', $id)->delete();
        });
        if (! $ok) {
            return redirect()->back()->with('error', 'Delete failed — other records still reference this employee.');
        }
        $this->audit('employee.delete', (string) $id);

        return redirect()->to(route_to('employees'))->with('success', 'Employee deleted.');
    }
}

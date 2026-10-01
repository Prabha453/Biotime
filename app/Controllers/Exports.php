<?php

namespace App\Controllers;

use App\Libraries\Export;

/**
 * "Export" toolbar button — streams the current list as an .xlsx, mirroring
 * ZKBioTime's export. Each key maps to a SELECT + column labels; GET filters
 * that the list page already uses are honoured so the export matches the view.
 */
class Exports extends BaseController
{
    /** key => [label, sheet, sql, params-from-filters callback] */
    private function views(): array
    {
        $g = fn (string $k) => trim((string) $this->request->getGet($k));

        return [
            'employees' => [
                'sheet' => 'Employees',
                'cols'  => ['emp_code' => 'Employee ID', 'first_name' => 'First Name', 'last_name' => 'Last Name',
                    'dept_name' => 'Department', 'position_name' => 'Position', 'area_name' => 'Area',
                    'email' => 'Email', 'mobile' => 'Mobile', 'gender' => 'Gender', 'hire_date' => 'Hired Date',
                    'emp_type' => 'Employment Type', 'status' => 'Status'],
                'build' => function ($db) use ($g) {
                    $b = $db->table('personnel_employee e')
                        ->select("e.emp_code, e.first_name, e.last_name, d.dept_name, p.position_name,
                                  ar.area_name, e.email, e.mobile,
                                  CASE e.gender WHEN 'M' THEN 'Male' WHEN 'F' THEN 'Female' WHEN 'O' THEN 'Other' ELSE '' END gender,
                                  e.hire_date,
                                  CASE e.emp_type WHEN 1 THEN 'Permanent' WHEN 2 THEN 'Temporary' WHEN 3 THEN 'Probation' ELSE '' END emp_type,
                                  CASE WHEN r.employee_id IS NOT NULL THEN 'Resigned' WHEN e.is_active THEN 'Active' ELSE 'Disabled' END status")
                        ->join('personnel_department d', 'd.id = e.department_id', 'left')
                        ->join('personnel_position p', 'p.id = e.position_id', 'left')
                        ->join('personnel_employee_area ea', 'ea.employee_id = e.id', 'left')
                        ->join('personnel_area ar', 'ar.id = ea.area_id', 'left')
                        ->join('personnel_resign r', 'r.employee_id = e.id', 'left')
                        ->orderBy('e.emp_code_digit');
                    if ($g('q') !== '') {
                        $b->groupStart()->like('e.emp_code', $g('q'), 'both', null, true)
                            ->orLike('e.first_name', $g('q'), 'both', null, true)
                            ->orLike('e.last_name', $g('q'), 'both', null, true)
                            ->orLike('e.email', $g('q'), 'both', null, true)->groupEnd();
                    }
                    if ((int) $g('dept')) { $b->where('e.department_id', (int) $g('dept')); }
                    if ((int) $g('pos')) { $b->where('e.position_id', (int) $g('pos')); }
                    if ($g('status') === 'active') { $b->where('r.employee_id IS NULL', null, false)->where('e.is_active', true); }
                    if ($g('status') === 'resigned') { $b->where('r.employee_id IS NOT NULL', null, false); }
                    if ($g('status') === 'inactive') { $b->where('e.is_active', false); }

                    return $b;
                },
            ],
            'departments' => [
                'sheet' => 'Departments',
                'cols'  => ['dept_code' => 'Department Code', 'dept_name' => 'Department Name', 'parent' => 'Superior', 'company_name' => 'Company'],
                'build' => fn ($db) => $db->table('personnel_department d')
                    ->select('d.dept_code, d.dept_name, pd.dept_name parent, c.company_name')
                    ->join('personnel_department pd', 'pd.id = d.parent_dept_id', 'left')
                    ->join('personnel_company c', 'c.id = d.company_id', 'left')
                    ->orderBy('d.dept_code'),
            ],
            'positions' => [
                'sheet' => 'Positions',
                'cols'  => ['position_code' => 'Position Code', 'position_name' => 'Position Name', 'company_name' => 'Company'],
                'build' => fn ($db) => $db->table('personnel_position p')
                    ->select('p.position_code, p.position_name, c.company_name')
                    ->join('personnel_company c', 'c.id = p.company_id', 'left')
                    ->orderBy('p.position_code'),
            ],
            'areas' => [
                'sheet' => 'Areas',
                'cols'  => ['area_code' => 'Area Code', 'area_name' => 'Area Name', 'parent' => 'Superior', 'company_name' => 'Company'],
                'build' => fn ($db) => $db->table('personnel_area a')
                    ->select('a.area_code, a.area_name, pa.area_name parent, c.company_name')
                    ->join('personnel_area pa', 'pa.id = a.parent_area_id', 'left')
                    ->join('personnel_company c', 'c.id = a.company_id', 'left')
                    ->orderBy('a.area_code'),
            ],
            'companies' => [
                'sheet' => 'Companies',
                'cols'  => ['company_code' => 'Code', 'company_name' => 'Name', 'is_default' => 'Default'],
                'build' => fn ($db) => $db->table('personnel_company')
                    ->select("company_code, company_name, CASE WHEN is_default THEN 'Yes' ELSE '' END is_default")
                    ->orderBy('company_name'),
            ],
            'resign' => [
                'sheet' => 'Resignations',
                'cols'  => ['emp_code' => 'Employee ID', 'emp_name' => 'Name', 'resign_date' => 'Resign Date',
                    'resign_type' => 'Resign Type', 'reason' => 'Reason', 'disableatt' => 'Attendance Disabled'],
                'build' => fn ($db) => $db->table('personnel_resign r')
                    ->select("e.emp_code, NULLIF(TRIM(e.first_name||' '||e.last_name),'') emp_name, r.resign_date,
                              CASE r.resign_type WHEN 1 THEN 'Quit' WHEN 2 THEN 'Dismissed' WHEN 3 THEN 'Resignation'
                                   WHEN 4 THEN 'Transfer' WHEN 5 THEN 'Retain job without salary' ELSE '' END resign_type,
                              r.reason, CASE WHEN r.disableatt THEN 'Yes' ELSE '' END disableatt")
                    ->join('personnel_employee e', 'e.id = r.employee_id')
                    ->orderBy('r.resign_date', 'DESC'),
            ],
            'cert_types' => [
                'sheet' => 'Certification types',
                'cols'  => ['cert_code' => 'Code', 'cert_name' => 'Name', 'held' => 'Held by'],
                'build' => fn ($db) => $db->table('personnel_certification t')
                    ->select('t.cert_code, t.cert_name, (SELECT count(*) FROM personnel_employeecertification ec WHERE ec.certification_id = t.id) held')
                    ->orderBy('t.cert_name'),
            ],
            'employee_certs' => [
                'sheet' => 'Employee certifications',
                'cols'  => ['emp_code' => 'Employee ID', 'emp_name' => 'Name', 'cert_name' => 'Certification',
                    'expire_on' => 'Expire On', 'email_alert' => 'Email Alert', 'before' => 'Reminder (days)'],
                'build' => fn ($db) => $db->table('personnel_employeecertification ec')
                    ->select("e.emp_code, NULLIF(TRIM(e.first_name||' '||e.last_name),'') emp_name, t.cert_name,
                              ec.expire_on, CASE WHEN ec.email_alert THEN 'Yes' ELSE '' END email_alert, ec.before")
                    ->join('personnel_certification t', 't.id = ec.certification_id')
                    ->join('personnel_employee e', 'e.id = ec.employee_id')
                    ->orderBy('ec.expire_on'),
            ],
            'custom_attrs' => [
                'sheet' => 'Custom attributes',
                'cols'  => ['attr_name' => 'Name', 'attr_type' => 'Type', 'attr_value' => 'Options', 'is_unique' => 'Unique', 'enable' => 'Enabled'],
                'build' => fn ($db) => $db->table('personnel_employeecustomattribute')
                    ->select("attr_name, CASE attr_type WHEN 1 THEN 'Text' WHEN 2 THEN 'Combobox' ELSE '' END attr_type,
                              attr_value, CASE WHEN is_unique THEN 'Yes' ELSE '' END is_unique,
                              CASE WHEN enable THEN 'Yes' ELSE '' END enable")
                    ->orderBy('attr_name'),
            ],
            'leave_balance' => [
                'sheet' => 'Leave balances',
                'cols'  => ['emp_code' => 'Employee ID', 'emp_name' => 'Name', 'pay_code' => 'Leave Type', 'year' => 'Year',
                    'entitlement_days' => 'Entitlement', 'pre_balance' => 'Carried', 'taken' => 'Taken', 'balance' => 'Balance'],
                'build' => function ($db) use ($g) {
                    $b = $db->table('att_leaveyearbalance b')
                        ->select("e.emp_code, NULLIF(TRIM(e.first_name||' '||e.last_name),'') emp_name,
                                  p.code||' — '||p.name pay_code, b.year, b.entitlement_days, b.pre_balance,
                                  COALESCE((SELECT SUM(l.leave_day) FROM att_leave l
                                             JOIN workflow_workflowinstance w ON w.id = l.workflowinstance_ptr_id
                                            WHERE w.employee_id = b.employee_id AND l.pay_code_id = b.pay_code_id
                                              AND w.approval_status = 1 AND EXTRACT(YEAR FROM l.start_time) = b.year),0) taken,
                                  (b.entitlement_days + b.pre_balance
                                   - COALESCE((SELECT SUM(l.leave_day) FROM att_leave l
                                               JOIN workflow_workflowinstance w ON w.id = l.workflowinstance_ptr_id
                                              WHERE w.employee_id = b.employee_id AND l.pay_code_id = b.pay_code_id
                                                AND w.approval_status = 1 AND EXTRACT(YEAR FROM l.start_time) = b.year),0)) balance")
                        ->join('att_paycode p', 'p.id = b.pay_code_id', 'left')
                        ->join('personnel_employee e', 'e.id = b.employee_id')
                        ->orderBy('e.emp_code_digit');
                    $b->where('b.year', (int) ($g('year') ?: date('Y')));
                    if ((int) $g('dept')) { $b->where('e.department_id', (int) $g('dept')); }

                    return $b;
                },
            ],

            // ---------- Attendance ▸ Setup ----------
            'att_paycode' => [
                'sheet' => 'Pay Code',
                'cols'  => ['code' => 'Code', 'name' => 'Name', 'code_type' => 'Type', 'tag' => 'Multiplier Tag',
                    'is_work' => 'Is Work', 'is_paid' => 'Is Paid', 'is_benefit' => 'Is Benefit',
                    'fixed_hours' => 'Fixed Hrs', 'display_format' => 'Format', 'display_order' => 'Order'],
                'build' => fn ($db) => $db->table('att_paycode')
                    ->select("code, name,
                              CASE code_type WHEN 1 THEN 'Normal' WHEN 2 THEN 'Overtime' WHEN 3 THEN 'Leave' WHEN 4 THEN 'Exception' WHEN 5 THEN 'Training' ELSE '—' END code_type,
                              COALESCE(tag::text,'—') tag,
                              CASE WHEN is_work THEN 'Yes' ELSE 'No' END is_work,
                              CASE WHEN is_paid THEN 'Yes' ELSE 'No' END is_paid,
                              CASE WHEN is_benefit THEN 'Yes' ELSE 'No' END is_benefit,
                              fixed_hours,
                              CASE display_format WHEN 1 THEN 'Minutes' WHEN 2 THEN 'Hours' WHEN 3 THEN 'Work day' WHEN 4 THEN 'hh:mm' ELSE '—' END display_format,
                              display_order")
                    ->orderBy('display_order')->orderBy('id'),
            ],

            // ---------- Attendance ▸ Shift ----------
            'att_breaktime' => [
                'sheet' => 'Break Time',
                'cols'  => ['alias' => 'Name', 'period_start' => 'Start Time', 'end_time' => 'End Time',
                    'duration' => 'Duration', 'with_salary' => 'With Salary', 'calc_type' => 'Calculation Type'],
                'build' => fn ($db) => $db->table('att_breaktime')
                    ->select("alias, period_start, (period_start + (duration || ' minutes')::interval)::time end_time, duration,
                              CASE with_salary WHEN 1 THEN 'Paid' ELSE 'Unpaid' END with_salary,
                              CASE calc_type WHEN 1 THEN 'Required Punch' ELSE 'Auto Deduct' END calc_type")
                    ->orderBy('period_start'),
            ],
            'att_timeinterval' => [
                'sheet' => 'Timetable',
                'cols'  => ['alias' => 'Name', 'use_mode' => 'Type', 'in_time' => 'Check-in', 'out_time' => 'Check-out',
                    'duration' => 'Work (min)', 'work_day' => 'Work Day', 'allow_late' => 'Grace Late', 'allow_leave_early' => 'Grace Early'],
                'build' => fn ($db) => $db->table('att_timeinterval')
                    ->select("alias, CASE use_mode WHEN 1 THEN 'Flexible' ELSE 'Normal' END use_mode, in_time,
                              (in_time + (duration || ' minutes')::interval)::time out_time, duration, work_day, allow_late, allow_leave_early")
                    ->orderBy('alias'),
            ],
            'att_shift' => [
                'sheet' => 'Shift',
                'cols'  => ['alias' => 'Name', 'cycle' => 'Cycle', 'timetables' => 'Timetables', 'assigned' => 'Assigned'],
                'build' => fn ($db) => $db->table('att_attshift s')
                    ->select("s.alias, s.shift_cycle || ' ' || CASE s.cycle_unit WHEN 0 THEN 'day' WHEN 2 THEN 'month' ELSE 'week' END cycle,
                              (SELECT count(*) FROM att_shiftdetail d WHERE d.shift_id = s.id) timetables,
                              (SELECT count(*) FROM att_attschedule sc WHERE sc.shift_id = s.id) assigned")
                    ->orderBy('s.alias'),
            ],

            // ---------- Device module ----------
            'devices' => [
                'sheet' => 'Devices',
                'cols'  => ['alias' => 'Device Name', 'sn' => 'Serial Number', 'area_name' => 'Area', 'ip_address' => 'Device IP',
                    'state' => 'State', 'last_seen' => 'Last Activity', 'user_count' => 'User Qty.', 'fp_count' => 'FP Qty.',
                    'face_count' => 'Face Qty.', 'palm_count' => 'Palm Qty.', 'transaction_count' => 'Transaction Qty.', 'fw_ver' => 'Firmware'],
                'build' => fn ($db) => $db->table('iclock_terminal t')
                    ->select("t.alias, t.sn, a.area_name, t.ip_address,
                              CASE WHEN t.last_activity IS NOT NULL AND now() - t.last_activity < interval '10 minutes' THEN 'Online' ELSE 'Offline' END state,
                              to_char(t.last_activity,'YYYY-MM-DD HH24:MI:SS') last_seen,
                              t.user_count, t.fp_count, t.face_count, t.palm_count, t.transaction_count, t.fw_ver")
                    ->join('personnel_area a', 'a.id = t.area_id', 'left')
                    ->orderBy('t.alias'),
            ],
            'devlog_command' => [
                'sheet' => 'Device Command',
                'cols'  => ['device' => 'Device', 'content' => 'Command', 'commit_at' => 'Committed', 'transfer_at' => 'Transferred'],
                'build' => fn ($db) => $db->table('iclock_terminalcommand c')
                    ->select("t.alias device, c.content, to_char(c.commit_time,'YYYY-MM-DD HH24:MI:SS') commit_at, to_char(c.transfer_time,'YYYY-MM-DD HH24:MI:SS') transfer_at")
                    ->join('iclock_terminal t', 't.id = c.terminal_id', 'left')->orderBy('c.commit_time', 'DESC'),
            ],
            'devlog_cmdlog' => [
                'sheet' => 'Command Log',
                'cols'  => ['device' => 'Device', 'content' => 'Command', 'return_value' => 'Return', 'commit_at' => 'Committed', 'return_at' => 'Returned'],
                'build' => fn ($db) => $db->table('iclock_terminalcommandlog c')
                    ->select("t.alias device, c.content, c.return_value, to_char(c.commit_time,'YYYY-MM-DD HH24:MI:SS') commit_at, to_char(c.return_time,'YYYY-MM-DD HH24:MI:SS') return_at")
                    ->join('iclock_terminal t', 't.id = c.terminal_id', 'left')->orderBy('c.commit_time', 'DESC'),
            ],
            'devlog_devlog' => [
                'sheet' => 'Device Log',
                'cols'  => ['device' => 'Device', 'action_name' => 'Operation', 'admin' => 'Operator', 'object' => 'Object', 'action_at' => 'Time'],
                'build' => fn ($db) => $db->table('iclock_terminallog l')
                    ->select("t.alias device, l.action_name, l.admin, l.object, to_char(l.action_time,'YYYY-MM-DD HH24:MI:SS') action_at")
                    ->join('iclock_terminal t', 't.id = l.terminal_id', 'left')->orderBy('l.action_time', 'DESC'),
            ],
            'devlog_upload' => [
                'sheet' => 'Upload Log',
                'cols'  => ['device' => 'Device', 'event' => 'Event', 'content' => 'Content', 'upload_count' => 'Count', 'error_count' => 'Errors', 'upload_at' => 'Uploaded'],
                'build' => fn ($db) => $db->table('iclock_terminaluploadlog u')
                    ->select("t.alias device, u.event, u.content, u.upload_count, u.error_count, to_char(u.upload_time,'YYYY-MM-DD HH24:MI:SS') upload_at")
                    ->join('iclock_terminal t', 't.id = u.terminal_id', 'left')->orderBy('u.upload_time', 'DESC'),
            ],
            'devlog_errorcmd' => [
                'sheet' => 'Error Command',
                'cols'  => ['device' => 'Device', 'cmd' => 'Command', 'error_code' => 'Error code', 'error_msg' => 'Message', 'upload_at' => 'Uploaded'],
                'build' => fn ($db) => $db->table('iclock_errorcommandlog e')
                    ->select("t.alias device, e.cmd, e.error_code, e.error_msg, to_char(e.upload_time,'YYYY-MM-DD HH24:MI:SS') upload_at")
                    ->join('iclock_terminal t', 't.id = e.terminal_id', 'left')->orderBy('e.upload_time', 'DESC'),
            ],
            'biodata' => [
                'sheet' => 'Biometric Template',
                'cols'  => ['emp_code' => 'Employee ID', 'emp_name' => 'Name', 'bio_type' => 'Type', 'bio_no' => 'Index',
                    'ver' => 'Algorithm', 'enroll_sn' => 'Enroll device', 'valid' => 'Valid'],
                'build' => fn ($db) => $db->table('iclock_biodata d')
                    ->select("e.emp_code, NULLIF(TRIM(e.first_name||' '||e.last_name),'') emp_name,
                              CASE d.bio_type WHEN 1 THEN 'Fingerprint' WHEN 2 THEN 'Face' WHEN 6 THEN 'Palm print' WHEN 7 THEN 'Finger vein' WHEN 9 THEN 'Visible face' ELSE d.bio_type::text END bio_type,
                              d.bio_no, d.major_ver||'.'||d.minor_ver ver, d.sn enroll_sn,
                              CASE WHEN d.valid = 1 THEN 'Yes' ELSE 'No' END valid")
                    ->join('personnel_employee e', 'e.id = d.employee_id', 'left')->orderBy('d.id', 'DESC'),
            ],
            'devemployee' => [
                'sheet' => 'Device Employee',
                'cols'  => ['emp_code' => 'Employee ID', 'first_name' => 'First Name', 'last_name' => 'Last Name', 'dept_name' => 'Department',
                    'card_no' => 'Card NO.', 'fp' => 'Fingerprint', 'face' => 'Face', 'palm' => 'Palm', 'priv' => 'Device Privilege', 'verify' => 'Verify Mode'],
                'build' => fn ($db) => $db->table('personnel_employee e')
                    ->select("e.emp_code, e.first_name, e.last_name, d.dept_name, e.card_no,
                              (SELECT count(*) FROM iclock_biodata b WHERE b.employee_id = e.id AND b.bio_type = 1) fp,
                              (SELECT count(*) FROM iclock_biodata b WHERE b.employee_id = e.id AND b.bio_type = 2) face,
                              (SELECT count(*) FROM iclock_biodata b WHERE b.employee_id = e.id AND b.bio_type = 6) palm,
                              CASE e.dev_privilege WHEN 0 THEN 'Employee' WHEN 2 THEN 'Registrar' WHEN 6 THEN 'System administrator' WHEN 14 THEN 'Super administrator' ELSE e.dev_privilege::text END priv,
                              CASE e.verify_mode WHEN 0 THEN 'Any' WHEN 1 THEN 'Fingerprint' WHEN 15 THEN 'Face' WHEN 25 THEN 'Palm' ELSE e.verify_mode::text END verify")
                    ->join('personnel_department d', 'd.id = e.department_id', 'left')
                    ->where('e.id NOT IN (SELECT employee_id FROM personnel_resign)', null, false)
                    ->orderBy('e.emp_code_digit'),
            ],
            'biophoto' => [
                'sheet' => 'Bio-Photo',
                'cols'  => ['emp_code' => 'Employee ID', 'emp_name' => 'Name', 'enroll_sn' => 'Enroll device',
                    'approval_state' => 'Approval', 'registered_at' => 'Registered'],
                'build' => fn ($db) => $db->table('iclock_biophoto p')
                    ->select("e.emp_code, NULLIF(TRIM(e.first_name||' '||e.last_name),'') emp_name, p.enroll_sn,
                              CASE p.approval_state WHEN 0 THEN 'Pending' WHEN 1 THEN 'Approved' WHEN 2 THEN 'Rejected' WHEN 3 THEN 'Auto-approved' ELSE '-' END approval_state,
                              to_char(p.register_time,'YYYY-MM-DD HH24:MI') registered_at")
                    ->join('personnel_employee e', 'e.id = p.employee_id', 'left')->orderBy('p.register_time', 'DESC'),
            ],
        ];
    }

    public function run(string $key)
    {
        $views = $this->views();
        if (! isset($views[$key])) {
            return redirect()->back()->with('error', 'Unknown export.');
        }
        $v      = $views[$key];
        $format = strtolower((string) $this->request->getGet('format')) ?: 'xlsx';
        $rows   = $v['build'](db_connect())->get()->getResultArray();

        $headers = array_values($v['cols']);
        $keys    = array_keys($v['cols']);
        $data    = array_map(
            static fn ($r) => array_map(static fn ($k) => (string) ($r[$k] ?? ''), $keys),
            $rows
        );
        $fname = $key . '_' . date('Ymd_His');
        $this->audit('export.' . $key, count($rows) . " rows ({$format})");

        return Export::stream($format, $fname, $v['sheet'], $headers, $data, [
            'title'  => $v['sheet'],
            'meta'   => count($rows) . ' records · exported ' . date('Y-m-d H:i'),
            'freeze' => true,
        ]);
    }
}

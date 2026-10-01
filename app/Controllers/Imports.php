<?php

namespace App\Controllers;

use App\Libraries\Xlsx;
use App\Libraries\XlsxReader;

/**
 * Personnel bulk import — ZKBioTime's per-list "Import" action.
 *
 *   TABLE → employees / departments / positions / areas / certifications /
 *           employeecerts / resign / leavebalance   (.xlsx or .csv)
 *   ZIP   → employee photos (<emp_code>.jpg)  ·  certification documents
 *
 * Every importer is upsert-by-code, wrapped in one transaction, and reports
 * a created / updated / skipped tally. Each table type ships a downloadable
 * .xlsx template (headers + a description row + example rows).
 */
class Imports extends BaseController
{
    /**
     * Per-type column spec. 'cols' maps the human header (as it appears in the
     * template / a ZKBioTime export) to the internal key the importer reads;
     * the reader also accepts the internal key itself. 'req' lists the headers
     * that must carry a value. 'sample' is the example data rows. 'notes' is
     * the required-field help shown in the import dialog.
     */
    private const SPECS = [
        'employees' => [
            'label' => 'Employees',
            'cols'  => [
                'Employee ID' => 'emp_code', 'First Name' => 'first_name', 'Last Name' => 'last_name',
                'Department' => 'department', 'Position' => 'position', 'Area' => 'area',
                'Email' => 'email', 'Mobile' => 'mobile', 'Gender' => 'gender',
                'Hired Date' => 'hire_date', 'Employment Type' => 'emp_type', 'Company' => 'company',
            ],
            'req'    => ['Employee ID'],
            'sample' => [
                ['10001', 'John', 'Doe', 'Development', 'Engineer', 'Head Office', 'john@corp.com', '0501234567', 'M', '2024-01-15', '1', ''],
                ['10002', 'Jane', 'Smith', 'HR', 'Manager', 'Head Office', '', '', 'F', '2023-06-01', '1', ''],
            ],
            'notes' => [
                'The header row is required.',
                "'Employee ID' is required and must be unique.",
                'Department / Position / Area / Company accept either the code or the name.',
                'Gender is M, F or O. Employment Type is 1 (Permanent), 2 (Temporary) or 3 (Probation).',
                'Existing employees (matched on Employee ID) are updated; new ones are created.',
            ],
        ],
        'departments' => [
            'label'  => 'Departments',
            'cols'   => ['Department Code' => 'dept_code', 'Department Name' => 'dept_name', 'Parent Department' => 'parent_code', 'Company' => 'company'],
            'req'    => ['Department Code', 'Department Name'],
            'sample' => [['DEV', 'Development', '', ''], ['DEV-BE', 'Backend', 'DEV', '']],
            'notes'  => ['The header row is required.', "'Department Code' and 'Department Name' are required.", 'Parent Department is the unique code of the superior department.'],
        ],
        'positions' => [
            'label'  => 'Positions',
            'cols'   => ['Position Code' => 'position_code', 'Position Name' => 'position_name', 'Parent Position' => 'parent_code', 'Company' => 'company'],
            'req'    => ['Position Code', 'Position Name'],
            'sample' => [['ENG', 'Engineer', '', ''], ['SR-ENG', 'Senior Engineer', 'ENG', '']],
            'notes'  => ['The header row is required.', "'Position Code' and 'Position Name' are required.", 'Parent Position is the unique code of the superior position.'],
        ],
        'areas' => [
            'label'  => 'Areas',
            'cols'   => ['Area Code' => 'area_code', 'Area Name' => 'area_name', 'Parent Area' => 'parent_code', 'Company' => 'company'],
            'req'    => ['Area Code', 'Area Name'],
            'sample' => [['HO', 'Head Office', '', ''], ['HO-2F', 'Head Office 2F', 'HO', '']],
            'notes'  => ['The header row is required.', "'Area Code' and 'Area Name' are required.", 'Parent Area is the unique code of the superior area.'],
        ],
        'certifications' => [
            'label'  => 'Certification types',
            'cols'   => ['Code' => 'cert_code', 'Name' => 'cert_name'],
            'req'    => ['Code', 'Name'],
            'sample' => [['FIRSTAID', 'First Aid'], ['FORKLIFT', 'Forklift Licence']],
            'notes'  => ['The header row is required.', "'Code' and 'Name' are required."],
        ],
        'employeecerts' => [
            'label'  => 'Employee certifications',
            'cols'   => ['Employee ID' => 'emp_code', 'Certification' => 'cert', 'Expire On' => 'expire_on', 'Email Alert' => 'email_alert', 'Before (days)' => 'before'],
            'req'    => ['Employee ID', 'Certification'],
            'sample' => [['10001', 'FIRSTAID', '2026-12-31', 'Y', '30'], ['10002', 'Forklift Licence', '2027-03-01', 'N', '']],
            'notes'  => ['The header row is required.', "'Employee ID' and 'Certification' are required.", 'Certification accepts the type code or name.', 'Email Alert is Y/N. Before is the reminder window in days.'],
        ],
        'resign' => [
            'label'  => 'Resignations',
            'cols'   => ['Employee ID' => 'emp_code', 'Resign Date' => 'resign_date', 'Resign Type' => 'resign_type', 'Reason' => 'reason', 'Disable Attendance' => 'disable_att'],
            'req'    => ['Employee ID', 'Resign Date'],
            'sample' => [['10001', '2026-01-31', 'Resignation', 'Relocating', 'Y'], ['10002', '2026-02-15', 'Dismissed', '', 'Y']],
            'notes'  => ['The header row is required.', "'Employee ID' and 'Resign Date' are required.", 'Resign Type: Quit, Dismissed, Resignation, Transfer or Retain job without salary (or 1-5).', 'Disable Attendance is Y/N.'],
        ],
        'leavebalance' => [
            'label'  => 'Leave balances',
            'cols'   => ['Employee ID' => 'emp_code', 'Leave Type' => 'leave_type', 'Year' => 'year', 'Entitlement Days' => 'entitlement_days', 'Carried Forward' => 'pre_balance'],
            'req'    => ['Employee ID', 'Leave Type'],
            'sample' => [['10001', 'Annual Leave', '2026', '21', '3'], ['10001', 'Sick Leave', '2026', '10', '0']],
            'notes'  => ['The header row is required.', "'Employee ID' and 'Leave Type' are required.", 'Leave Type accepts the pay-code (e.g. AL) or its name.', 'Year defaults to the current year. Days may be decimal.'],
        ],
    ];

    private const TYPES = [
        'employees'      => 'Employees',
        'departments'    => 'Departments',
        'positions'      => 'Positions',
        'areas'          => 'Areas',
        'certifications' => 'Certification types',
        'employeecerts'  => 'Employee certifications',
        'resign'         => 'Resignations',
        'leavebalance'   => 'Leave balances',
        'photos'         => 'Employee photos (ZIP)',
        'documents'      => 'Certification documents (ZIP)',
    ];

    public function index(string $type = 'employees'): string
    {
        if (! isset(self::TYPES[$type])) {
            $type = 'employees';
        }

        return view('admin/imports/index', [
            'title'    => 'Import',
            'subtitle' => self::TYPES[$type],
            'active'   => 'imports',
            'type'     => $type,
            'types'    => self::TYPES,
            'columns'  => isset(self::SPECS[$type]) ? implode(', ', array_keys(self::SPECS[$type]['cols'])) : null,
            'notes'    => self::SPECS[$type]['notes'] ?? [],
        ]);
    }

    /** Downloadable .xlsx template for a table import type. */
    public function template(string $type)
    {
        $spec = self::SPECS[$type] ?? null;
        if (! $spec) {
            return redirect()->to(route_to('imports'))->with('error', 'No template for that import type.');
        }
        $heads = array_keys($spec['cols']);
        $xlsx  = (new Xlsx($spec['label']))->headers($heads);
        foreach ($spec['sample'] as $row) {
            $xlsx->row($row);
        }

        return $xlsx->download($type . '_import_template.xlsx');
    }

    /** Public accessor for the per-type column spec (import modal demo table). */
    public static function spec(string $type): ?array
    {
        return self::SPECS[$type] ?? null;
    }

    public static function types(): array
    {
        return self::TYPES;
    }

    /** Map a file's header row onto internal keys, tolerating label or key, case and spacing. */
    private function mapHeader(string $type, array $rawHeader): array
    {
        $spec = self::SPECS[$type]['cols'] ?? [];
        $norm = static fn (string $s): string => preg_replace('/[^a-z0-9]/', '', strtolower(trim($s)));
        $byLabel = [];
        foreach ($spec as $label => $key) {
            $byLabel[$norm($label)] = $key;
            $byLabel[$norm($key)]   = $key;
        }

        return array_map(static fn ($h) => $byLabel[$norm((string) $h)] ?? $norm((string) $h), $rawHeader);
    }

    public function run(string $type)
    {
        if (! isset(self::TYPES[$type])) {
            return redirect()->to(route_to('imports'))->with('error', 'Unknown import type.');
        }
        $file = $this->request->getFile('file');
        if (! $file || ! $file->isValid()) {
            return redirect()->back()->with('error', 'Choose a file to import.');
        }

        $overwrite = $this->request->getPost('existing') === 'overwrite';

        try {
            $result = in_array($type, ['photos', 'documents'], true)
                ? $this->importZip($type, $file)
                : $this->importTable($type, $file, $overwrite);
        } catch (\Throwable $e) {
            log_message('error', "import {$type}: " . $e->getMessage());

            return redirect()->back()->with('error', 'Import failed: ' . $e->getMessage());
        }

        $this->audit('personnel.import.' . $type, implode(' / ', array_map(
            static fn ($k, $v) => "{$v} {$k}",
            array_keys($result),
            $result
        )));

        $msg = self::TYPES[$type] . ' import — '
            . implode(', ', array_map(static fn ($k, $v) => "{$v} {$k}", array_keys($result), $result)) . '.';

        // came from a per-list Import modal? go back to that list
        $back = (string) ($this->request->getPost('return') ?: '');

        return redirect()->to($back ?: route_to('imports.tab', $type))->with('success', $msg);
    }

    // ----------------------------------------------------------------- TABLE

    /** Read a .csv or .xlsx file into a list of raw string rows. */
    private function rawRows($file): array
    {
        $ext = strtolower($file->getExtension() ?: '');
        if ($ext === 'xlsx') {
            return XlsxReader::rows($file->getTempName());
        }
        if (in_array($ext, ['csv', 'txt'], true)) {
            $fh = fopen($file->getTempName(), 'r');
            if (! $fh) {
                throw new \RuntimeException('Could not read the file.');
            }
            $out = [];
            while (($line = fgetcsv($fh)) !== false) {
                $out[] = array_map(static fn ($v) => (string) $v, $line);
            }
            fclose($fh);

            return $out;
        }

        throw new \RuntimeException('Expected a .xlsx or .csv file.');
    }

    private function importTable(string $type, $file, bool $overwrite = true): array
    {
        $raw = $this->rawRows($file);
        // drop leading fully-empty rows, then take the first as the header
        while ($raw && count(array_filter($raw[0], static fn ($v) => trim((string) $v) !== '')) === 0) {
            array_shift($raw);
        }
        if (! $raw) {
            throw new \RuntimeException('The file is empty.');
        }
        $header = $this->mapHeader($type, array_shift($raw));

        $rows = [];
        foreach ($raw as $line) {
            if (count(array_filter($line, static fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $line   = array_pad(array_slice($line, 0, count($header)), count($header), null);
            $row    = array_combine($header, $line);
            // an optional "description" row (ZKBioTime templates carry one) — skip it
            if (($row['emp_code'] ?? $row['dept_code'] ?? $row['position_code'] ?? $row['area_code'] ?? $row['cert_code'] ?? '') !== ''
                && in_array(strtolower(trim((string) ($row['emp_code'] ?? $row['dept_code'] ?? $row['position_code'] ?? $row['area_code'] ?? $row['cert_code'] ?? ''))), ['employee id', 'department code', 'position code', 'area code', 'code'], true)) {
                continue;
            }
            $rows[] = $row;
        }
        if (! $rows) {
            throw new \RuntimeException('No data rows found.');
        }

        $db  = db_connect();
        $res = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $now = date('Y-m-d H:i:sP');

        $companyId = static function (?string $v) use ($db): int {
            $v = trim((string) $v);
            if ($v !== '') {
                $r = $db->table('personnel_company')->select('id')
                    ->groupStart()->where('company_code', $v)->orWhere('company_name', $v)->groupEnd()
                    ->get()->getRowArray();
                if ($r) {
                    return (int) $r['id'];
                }
            }

            return (int) ($db->query('SELECT id FROM personnel_company ORDER BY is_default DESC, id LIMIT 1')->getRow()->id ?? 1);
        };
        $lookup = static function (string $table, array $cols, ?string $v) use ($db): ?int {
            $v = trim((string) $v);
            if ($v === '') {
                return null;
            }
            $b = $db->table($table)->select('id')->groupStart();
            foreach ($cols as $i => $c) {
                $i === 0 ? $b->where($c, $v) : $b->orWhere($c, $v);
            }
            $r = $b->groupEnd()->get()->getRowArray();

            return $r ? (int) $r['id'] : null;
        };

        $ok = $this->tx(function ($db) use ($type, $rows, &$res, $now, $companyId, $lookup, $overwrite) {
            foreach ($rows as $r) {
                switch ($type) {
                    case 'employees':
                        $code = trim((string) ($r['emp_code'] ?? ''));
                        if ($code === '') { $res['skipped']++; break; }
                        $data = array_filter([
                            'first_name'    => trim((string) ($r['first_name'] ?? '')) ?: null,
                            'last_name'     => trim((string) ($r['last_name'] ?? '')) ?: null,
                            'department_id' => $lookup('personnel_department', ['dept_code', 'dept_name'], $r['department'] ?? null),
                            'position_id'   => $lookup('personnel_position', ['position_code', 'position_name'], $r['position'] ?? null),
                            'email'         => trim((string) ($r['email'] ?? '')) ?: null,
                            'mobile'        => trim((string) ($r['mobile'] ?? '')) ?: null,
                            'gender'        => in_array(strtoupper(trim((string) ($r['gender'] ?? ''))), ['M', 'F', 'O'], true) ? strtoupper(trim($r['gender'])) : null,
                            'hire_date'     => $this->date($r['hire_date'] ?? null),
                            'emp_type'      => in_array((int) ($r['emp_type'] ?? 0), [1, 2, 3], true) ? (int) $r['emp_type'] : null,
                        ], static fn ($v) => $v !== null);
                        $areaId = $lookup('personnel_area', ['area_code', 'area_name'], $r['area'] ?? null);
                        $existing = $db->table('personnel_employee')->select('id')->where('emp_code', $code)->get()->getRowArray();
                        if ($existing && ! $overwrite) { $res['skipped']++; break; }
                        if ($existing) {
                            $db->table('personnel_employee')->where('id', $existing['id'])->update($data + ['update_time' => $now]);
                            $eid = (int) $existing['id'];
                            $res['updated']++;
                        } else {
                            $db->table('personnel_employee')->insert($data + [
                                'emp_code' => $code, 'emp_code_digit' => is_numeric($code) ? (int) $code : null,
                                'company_id' => $companyId($r['company'] ?? null), 'is_active' => true, 'enable_payroll' => true,
                                'verify_mode' => 0, 'dev_privilege' => 0, 'app_status' => 0, 'app_role' => 1,
                                'hire_date' => $data['hire_date'] ?? date('Y-m-d'),
                                'status' => 0, 'create_time' => $now, 'update_time' => $now,
                            ]);
                            $eid = (int) $db->insertID();
                            $db->table('att_attemployee')->insert(['emp_id' => $eid, 'status' => 0, 'enable_attendance' => true, 'enable_schedule' => true, 'enable_overtime' => true, 'enable_holiday' => true, 'enable_compensatory' => false]);
                            $db->table('personnel_employeeprofile')->insert(['column_order' => '', 'disabled_fields' => '', 'preferences' => '', 'emp_id' => $eid]);
                            $db->table('payroll_emppayrollprofile')->insert(['employee_id' => $eid, 'payment_mode' => 1, 'payment_type' => 1]);
                            $res['created']++;
                        }
                        if ($areaId) {
                            $db->table('personnel_employee_area')->where('employee_id', $eid)->delete();
                            $db->table('personnel_employee_area')->insert(['employee_id' => $eid, 'area_id' => $areaId]);
                        }
                        break;

                    case 'departments':
                        $this->upsertOrg($db, 'personnel_department', 'dept_code', 'dept_name', $r, $res, $companyId, [
                            'parent_dept_id' => $lookup('personnel_department', ['dept_code'], $r['parent_code'] ?? null),
                        ], $overwrite);
                        break;

                    case 'positions':
                        $this->upsertOrg($db, 'personnel_position', 'position_code', 'position_name', $r, $res, $companyId, [], $overwrite);
                        break;

                    case 'areas':
                        $this->upsertOrg($db, 'personnel_area', 'area_code', 'area_name', $r, $res, $companyId, [
                            'parent_area_id' => $lookup('personnel_area', ['area_code'], $r['parent_code'] ?? null),
                        ], $overwrite);
                        break;

                    case 'leavebalance':
                        $emp = $lookup('personnel_employee', ['emp_code'], $r['emp_code'] ?? null);
                        $pc  = $lookup('att_paycode', ['code', 'name'], $r['leave_type'] ?? null);
                        $yr  = (int) ($r['year'] ?? date('Y'));
                        if (! $emp || ! $pc || $yr < 2000) { $res['skipped']++; break; }
                        $row = [
                            'entitlement_days' => (int) round((float) ($r['entitlement_days'] ?? 0)),
                            'pre_balance'      => (int) round((float) ($r['pre_balance'] ?? 0)),
                            'status'           => 0,
                        ];
                        $ex = $db->table('att_leaveyearbalance')->select('id')
                            ->where('employee_id', $emp)->where('pay_code_id', $pc)->where('year', $yr)->get()->getRowArray();
                        if ($ex && ! $overwrite) { $res['skipped']++; break; }
                        if ($ex) {
                            $db->table('att_leaveyearbalance')->where('id', $ex['id'])->update($row + ['change_time' => $now]);
                            $res['updated']++;
                        } else {
                            $db->table('att_leaveyearbalance')->insert($row + [
                                'employee_id' => $emp, 'pay_code_id' => $pc, 'year' => $yr, 'create_time' => $now,
                            ]);
                            $res['created']++;
                        }
                        break;

                    case 'certifications':
                        $code = trim((string) ($r['cert_code'] ?? ''));
                        $name = trim((string) ($r['cert_name'] ?? ''));
                        if ($code === '' || $name === '') { $res['skipped']++; break; }
                        $ex = $db->table('personnel_certification')->select('id')->where('cert_code', $code)->get()->getRowArray();
                        if ($ex && ! $overwrite) { $res['skipped']++; break; }
                        if ($ex) {
                            $db->table('personnel_certification')->where('id', $ex['id'])->update(['cert_name' => $name]);
                            $res['updated']++;
                        } else {
                            $db->table('personnel_certification')->insert(['cert_code' => $code, 'cert_name' => $name]);
                            $res['created']++;
                        }
                        break;

                    case 'employeecerts':
                        $emp  = $lookup('personnel_employee', ['emp_code'], $r['emp_code'] ?? null);
                        $cert = $lookup('personnel_certification', ['cert_code', 'cert_name'], $r['cert'] ?? null);
                        if (! $emp || ! $cert) { $res['skipped']++; break; }
                        $row = [
                            'expire_on'   => $this->date($r['expire_on'] ?? null),
                            'email_alert' => $this->yesNo($r['email_alert'] ?? null),
                            'before'      => ($r['before'] ?? '') !== '' ? (int) $r['before'] : null,
                        ];
                        $ex = $db->table('personnel_employeecertification')->select('id')
                            ->where('employee_id', $emp)->where('certification_id', $cert)->get()->getRowArray();
                        if ($ex && ! $overwrite) { $res['skipped']++; break; }
                        if ($ex) {
                            $db->table('personnel_employeecertification')->where('id', $ex['id'])->update($row);
                            $res['updated']++;
                        } else {
                            $db->table('personnel_employeecertification')->insert($row + ['employee_id' => $emp, 'certification_id' => $cert]);
                            $res['created']++;
                        }
                        break;

                    case 'resign':
                        $emp  = $lookup('personnel_employee', ['emp_code'], $r['emp_code'] ?? null);
                        $date = $this->date($r['resign_date'] ?? null);
                        if (! $emp || ! $date) { $res['skipped']++; break; }
                        $types = [1 => 'quit', 2 => 'dismissed', 3 => 'resignation', 4 => 'transfer', 5 => 'retain job without salary'];
                        $rt    = trim(strtolower((string) ($r['resign_type'] ?? '')));
                        $rtId  = is_numeric($rt) ? (int) $rt : (array_search($rt, $types, true) ?: 3);
                        $disable = $this->yesNo($r['disable_att'] ?? 'Y');
                        $row  = ['resign_date' => $date, 'resign_type' => $rtId, 'disableatt' => $disable, 'reason' => trim((string) ($r['reason'] ?? '')) ?: null];
                        $ex   = $db->table('personnel_resign')->select('id')->where('employee_id', $emp)->get()->getRowArray();
                        if ($ex && ! $overwrite) { $res['skipped']++; break; }
                        if ($ex) {
                            $db->table('personnel_resign')->where('id', $ex['id'])->update($row);
                            $res['updated']++;
                        } else {
                            $db->table('personnel_resign')->insert($row + ['employee_id' => $emp]);
                            $res['created']++;
                        }
                        if ($disable) {
                            $db->table('personnel_employee')->where('id', $emp)->update(['is_active' => false]);
                        }
                        break;
                }
            }
        });
        if (! $ok) {
            throw new \RuntimeException('a database error rolled the import back (duplicate code, or a bad reference).');
        }

        return $res;
    }

    private function upsertOrg($db, string $table, string $codeCol, string $nameCol, array $r, array &$res, callable $companyId, array $extra, bool $overwrite = true): void
    {
        $code = trim((string) ($r[$codeCol] ?? ''));
        $name = trim((string) ($r[$nameCol] ?? ''));
        if ($code === '' || $name === '') {
            $res['skipped']++;

            return;
        }
        $company = $companyId($r['company'] ?? null);
        $data    = array_filter([$nameCol => $name] + $extra, static fn ($v) => $v !== null);
        $ex      = $db->table($table)->select('id')->where($codeCol, $code)->where('company_id', $company)->get()->getRowArray();
        if ($ex && ! $overwrite) {
            $res['skipped']++;

            return;
        }
        if ($ex) {
            $db->table($table)->where('id', $ex['id'])->update($data);
            $res['updated']++;
        } else {
            $db->table($table)->insert($data + [$codeCol => $code, 'company_id' => $company, 'is_default' => false]);
            $res['created']++;
        }
    }

    private function yesNo(?string $v): bool
    {
        return in_array(strtolower(trim((string) $v)), ['y', 'yes', '1', 'true', 't'], true);
    }

    private function date(?string $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        $ts = strtotime($v);

        return $ts ? date('Y-m-d', $ts) : null;
    }

    // ------------------------------------------------------------------- ZIP

    private function importZip(string $type, $file): array
    {
        if (strtolower($file->getExtension() ?: '') !== 'zip') {
            throw new \RuntimeException('Expected a .zip file.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($file->getTempName()) !== true) {
            throw new \RuntimeException('Could not open the archive.');
        }

        $db      = db_connect();
        $res     = ['matched' => 0, 'skipped' => 0];
        $isPhoto = $type === 'photos';
        $dir     = FCPATH . 'assets/' . ($isPhoto ? 'photos' : 'documents');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $okExt = $isPhoto ? ['jpg', 'jpeg', 'png', 'gif'] : ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (substr($name, -1) === '/') {
                continue;
            }
            $base = basename($name);
            $ext  = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            $stem = pathinfo($base, PATHINFO_FILENAME);
            if (! in_array($ext, $okExt, true)) {
                $res['skipped']++;
                continue;
            }

            if ($isPhoto) {
                $emp = $db->table('personnel_employee')->select('id, emp_code')->where('emp_code', $stem)->get()->getRowArray();
                if (! $emp) { $res['skipped']++; continue; }
                $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $emp['emp_code']) . '.' . $ext;
                if (file_put_contents("{$dir}/{$safe}", $zip->getFromIndex($i)) !== false) {
                    $db->table('personnel_employee')->where('id', $emp['id'])->update(['photo' => 'photo/' . $safe, 'update_time' => date('Y-m-d H:i:sP')]);
                    $res['matched']++;
                } else {
                    $res['skipped']++;
                }
            } else {
                // <emp_code>_<cert_code> or just <emp_code>
                [$empCode, $certCode] = array_pad(explode('_', $stem, 2), 2, null);
                $emp = $db->table('personnel_employee')->select('id')->where('emp_code', $empCode)->get()->getRowArray();
                if (! $emp) { $res['skipped']++; continue; }
                $cert = $certCode
                    ? $db->table('personnel_employeecertification ec')->select('ec.id')
                        ->join('personnel_certification c', 'c.id = ec.certification_id')
                        ->where('ec.employee_id', $emp['id'])->where('c.cert_code', $certCode)->get()->getRowArray()
                    : $db->table('personnel_employeecertification')->select('id')->where('employee_id', $emp['id'])->orderBy('id')->get()->getRowArray();
                if (! $cert) { $res['skipped']++; continue; }
                $safe = $emp['id'] . '-' . (int) $cert['id'] . '-' . time() . '.' . $ext;
                if (file_put_contents("{$dir}/{$safe}", $zip->getFromIndex($i)) !== false) {
                    $db->table('personnel_employeecertification')->where('id', $cert['id'])
                        ->update(['file' => 'document/' . $safe, 'file_name' => mb_substr($base, 0, 200)]);
                    $res['matched']++;
                } else {
                    $res['skipped']++;
                }
            }
        }
        $zip->close();

        return $res;
    }
}

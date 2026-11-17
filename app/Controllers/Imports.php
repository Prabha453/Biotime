<?php

namespace App\Controllers;

use App\Libraries\Xlsx;
use App\Libraries\XlsxReader;
use App\Libraries\ImportValidationException;

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
                'Department, Position and Area are optional: unique matching names or codes are linked; missing or ambiguous matches are left unassigned.',
                'For multiple areas, separate their names with commas (for example Head Office, Warehouse).',
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
            'notes'  => ['The header row is required.', "'Employee ID' and 'Leave Type' are required.", 'Leave Type accepts the pay-code (e.g. AL) or its name.', 'Year defaults to the current year. Days must be whole numbers (0-32767).'],
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
        if ($type === 'employees') {
            $byLabel += ['departmentname' => 'department', 'positionname' => 'position', 'areaname' => 'area'];
        }
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
            return redirect()->back()->with('import_errors', ['File: ' . ($file ? $file->getErrorString() : 'Choose a file to import.')]);
        }

        $overwrite = $this->request->getPost('existing') === 'overwrite';

        try {
            $result = in_array($type, ['photos', 'documents'], true)
                ? $this->importZip($type, $file)
                : $this->importTable($type, $file, $overwrite);
        } catch (ImportValidationException $e) {
            return redirect()->back()->with('import_errors', $e->errors);
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
        // XLSX is a ZIP container and can be detected as application/zip.
        // Select the parser by filename; the parser validates the contents.
        $ext = strtolower($file->getClientExtension() ?: '');
        if (in_array($ext, ['xlsx', 'xls'], true)) {
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

        throw new ImportValidationException(['File: Expected Excel (.xlsx), CSV (.csv), or comma-separated text (.txt).']);
    }

    private function importTable(string $type, $file, bool $overwrite = true): array
    {
        try {
            $raw = $this->rawRows($file);
        } catch (ImportValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            throw new ImportValidationException(['File: ' . $e->getMessage()]);
        }
        $headerRow = 1;
        // drop leading fully-empty rows, then take the first as the header
        while ($raw && count(array_filter($raw[0], static fn ($v) => trim((string) $v) !== '')) === 0) {
            array_shift($raw);
            $headerRow++;
        }
        if (! $raw) {
            throw new ImportValidationException(['File: The file is empty.']);
        }
        $header = $this->mapHeader($type, array_shift($raw));
        $missing = array_filter(self::SPECS[$type]['req'],
            static fn ($label) => ! in_array(self::SPECS[$type]['cols'][$label], $header, true));
        if ($missing) {
            throw new ImportValidationException(['Row ' . $headerRow . ': Missing required columns: ' . implode(', ', $missing) . '. Download the import template for the expected headers.']);
        }
        $knownHeaders = array_values(self::SPECS[$type]['cols']);
        $usedHeaders = array_values(array_intersect($header, $knownHeaders));
        if (count($usedHeaders) !== count(array_unique($usedHeaders))) {
            throw new ImportValidationException(['Row ' . $headerRow . ': Duplicate column headers. Keep only one column for each field.']);
        }

        $rows = [];
        foreach ($raw as $offset => $line) {
            if (count(array_filter($line, static fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            if (count($line) > count($header) && array_filter(array_slice($line, count($header)), static fn ($v) => trim((string) $v) !== '')) {
                throw new ImportValidationException(['Row ' . ($headerRow + $offset + 1) . ': Extra values have no column header. Check the template and CSV separators.']);
            }
            $line   = array_pad(array_slice($line, 0, count($header)), count($header), null);
            $row    = array_combine($header, $line);
            // an optional "description" row (ZKBioTime templates carry one) — skip it
            if (($row['emp_code'] ?? $row['dept_code'] ?? $row['position_code'] ?? $row['area_code'] ?? $row['cert_code'] ?? '') !== ''
                && in_array(strtolower(trim((string) ($row['emp_code'] ?? $row['dept_code'] ?? $row['position_code'] ?? $row['area_code'] ?? $row['cert_code'] ?? ''))), ['employee id', 'department code', 'position code', 'area code', 'code'], true)) {
                continue;
            }
            $rows[$headerRow + $offset + 1] = $row;
        }
        if (! $rows) {
            throw new ImportValidationException(['File: No data rows found below the header.']);
        }

        $db  = db_connect();
        $this->validateRows($type, $rows, $db);
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
            $matches = $b->groupEnd()->get()->getResultArray();
            $r = count($matches) === 1 ? $matches[0] : null;

            return $r ? (int) $r['id'] : null;
        };

        $write = function ($db) use ($type, $rows, &$res, $now, $companyId, $lookup, $overwrite) {
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
                        $areaIds = $this->resolveAreas($db, (string) ($r['area'] ?? ''));
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
                        if ($areaIds) {
                            $db->table('personnel_employee_area')->where('employee_id', $eid)->delete();
                            foreach ($areaIds as $areaId) {
                                $db->table('personnel_employee_area')->insert(['employee_id' => $eid, 'area_id' => $areaId]);
                            }
                        }
                        break;

                    case 'departments':
                        $this->upsertOrg($db, 'personnel_department', 'dept_code', 'dept_name', $r, $res, $companyId, [
                            'parent_dept_id' => $lookup('personnel_department', ['dept_code'], $r['parent_code'] ?? null),
                        ], $overwrite);
                        break;

                    case 'positions':
                        $this->upsertOrg($db, 'personnel_position', 'position_code', 'position_name', $r, $res, $companyId, [
                            'parent_position_id' => $lookup('personnel_position', ['position_code'], $r['parent_code'] ?? null),
                        ], $overwrite);
                        break;

                    case 'areas':
                        $this->upsertOrg($db, 'personnel_area', 'area_code', 'area_name', $r, $res, $companyId, [
                            'parent_area_id' => $lookup('personnel_area', ['area_code'], $r['parent_code'] ?? null),
                        ], $overwrite);
                        break;

                    case 'leavebalance':
                        $emp = $lookup('personnel_employee', ['emp_code'], $r['emp_code'] ?? null);
                        $pc  = $lookup('att_paycode', ['code', 'name'], $r['leave_type'] ?? null);
                        $yr  = (int) (trim((string) ($r['year'] ?? '')) ?: date('Y'));
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
        };
        $db->transBegin();
        try {
            $write($db);
            if (! $db->transStatus() || ! $db->transCommit()) {
                throw new \RuntimeException('Database write failed.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Import transaction: ' . $e->getMessage());
            throw new \RuntimeException('No records were saved. A database constraint or linked-record change prevented the import. Check the codes and references, then retry.');
        }

        return $res;
    }

    /** Validate every data row before the first database write. */
    private function validateRows(string $type, array $rows, $db): void
    {
        $spec = self::SPECS[$type];
        $errors = [];
        $seen = [];
        $lengths = ['emp_code' => 20, 'first_name' => 100, 'last_name' => 100,
            'dept_code' => 50, 'dept_name' => 200, 'position_code' => 50,
            'position_name' => 100, 'area_code' => 30, 'area_name' => 100,
            'cert_code' => 20, 'cert_name' => 100, 'reason' => 200];
        $org = [
            'departments' => ['personnel_department', 'dept_code'],
            'positions' => ['personnel_position', 'position_code'],
            'areas' => ['personnel_area', 'area_code'],
        ];
        $refs = [
            'company' => ['personnel_company', ['company_code', 'company_name']],
            'department' => ['personnel_department', ['dept_code', 'dept_name']],
            'position' => ['personnel_position', ['position_code', 'position_name']],
            'cert' => ['personnel_certification', ['cert_code', 'cert_name']],
            'leave_type' => ['att_paycode', ['code', 'name']],
        ];
        if (in_array($type, ['employeecerts', 'resign', 'leavebalance'], true)) {
            $refs['emp_code'] = ['personnel_employee', ['emp_code']];
        }
        $identities = [];
        foreach ($rows as $number => $row) {
            $resolved = [];
            $add = static function (string $field, string $message) use (&$errors, $number): void {
                $errors[] = "Row {$number} — {$field}: {$message}";
            };
            foreach ($spec['req'] as $label) {
                if (trim((string) ($row[$spec['cols'][$label]] ?? '')) === '') {
                    $add($label, 'Required value is missing.');
                }
            }
            foreach ($spec['cols'] as $label => $key) {
                $value = trim((string) ($row[$key] ?? ''));
                if ($key === 'area' && $value !== '' && $type !== 'employees') {
                    try {
                        $this->resolveAreas($db, $value);
                    } catch (\RuntimeException $e) {
                        $add($label, $e->getMessage());
                    }
                }
                if (isset($lengths[$key]) && mb_strlen($value) > $lengths[$key]) {
                    $add($label, 'Maximum length is ' . $lengths[$key] . ' characters.');
                }
                if ($value !== '' && isset($refs[$key])) {
                    [$table, $columns] = $refs[$key];
                    $query = $db->table($table)->groupStart();
                    foreach ($columns as $i => $column) {
                        $i === 0 ? $query->where($column, $value) : $query->orWhere($column, $value);
                    }
                    $matches = $query->select('id')->groupEnd()->get()->getResultArray();
                    $count = count($matches);
                    $resolved[$key] = $count === 1 ? $matches[0]['id'] : $value;
                    if ($count !== 1 && ! ($type === 'employees' && in_array($key, ['department', 'position'], true))) {
                        $add($label, $count === 0 ? 'No matching code or name exists. Import or create it first.' : 'Multiple records match. Use a unique code.');
                    }
                }
                if ($value !== '' && in_array($key, ['hire_date', 'expire_on', 'resign_date'], true)) {
                    $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                    if (! $parsed || $parsed->format('Y-m-d') !== $value) {
                        $add($label, 'Use a valid date in YYYY-MM-DD format.');
                    }
                }
                if ($value !== '' && in_array($key, ['email_alert', 'disable_att'], true)
                    && ! in_array(strtolower($value), ['y', 'yes', '1', 'true', 't', 'n', 'no', '0', 'false', 'f'], true)) {
                    $add($label, 'Use Y or N.');
                }
                $max = ['before' => 2147483647, 'entitlement_days' => 32767, 'pre_balance' => 32767][$key] ?? null;
                if ($value !== '' && $max !== null && (! ctype_digit($value) || (float) $value > $max)) {
                    $add($label, "Use a whole number from 0 to {$max}.");
                }
                if ($key === 'year' && $value !== '' && (! ctype_digit($value) || (int) $value < 2000 || (int) $value > 9999)) {
                    $add($label, 'Use a year from 2000 to 9999, or leave blank for the current year.');
                }
                if ($key === 'resign_type' && $value !== '' && ! in_array(strtolower($value), ['1', '2', '3', '4', '5', 'quit', 'dismissed', 'resignation', 'transfer', 'retain job without salary'], true)) {
                    $add($label, 'Use Quit, Dismissed, Resignation, Transfer, Retain job without salary, or 1-5.');
                }
            }
            $identity = match ($type) {
                'employeecerts' => [$resolved['emp_code'] ?? '', $resolved['cert'] ?? ''],
                'leavebalance' => [$resolved['emp_code'] ?? '', $resolved['leave_type'] ?? '', trim((string) ($row['year'] ?? '')) ?: date('Y')],
                'resign' => [$resolved['emp_code'] ?? ''],
                default => null,
            };
            if ($identity !== null) {
                $key = json_encode($identity);
                if (isset($identities[$key])) { $add('Record', 'Duplicate record; first used on row ' . $identities[$key] . '.'); }
                $identities[$key] = $number;
            }
            $codeKey = $type === 'employees' ? 'emp_code' : ($type === 'certifications' ? 'cert_code' : ($org[$type][1] ?? null));
            $code = trim((string) ($row[$codeKey ?? ''] ?? ''));
            if ($codeKey && $code !== '') {
                if (isset($seen[$code])) {
                    $add(array_search($codeKey, $spec['cols'], true), 'Duplicate code in this file; first used on row ' . $seen[$code] . '.');
                }
            }
            if (isset($org[$type])) {
                $parent = trim((string) ($row['parent_code'] ?? ''));
                if ($parent !== '') {
                    [$table, $column] = $org[$type];
                    if ($parent === $code) {
                        $add('Parent', 'A record cannot be its own parent.');
                    } elseif (! isset($seen[$parent]) && ! $db->table($table)->where($column, $parent)->countAllResults()) {
                        $add('Parent', 'No matching parent code exists. Place the parent before this row or import it first.');
                    }
                }
            }
            if ($code !== '') { $seen[$code] = $number; }
            if ($type === 'employees') {
                $email = trim((string) ($row['email'] ?? ''));
                if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $add('Email', 'Enter a valid email address.');
                }
                $gender = strtoupper(trim((string) ($row['gender'] ?? '')));
                if ($gender !== '' && ! in_array($gender, ['M', 'F', 'O'], true)) {
                    $add('Gender', 'Use M, F or O.');
                }
                $employment = trim((string) ($row['emp_type'] ?? ''));
                if ($employment !== '' && ! in_array($employment, ['1', '2', '3'], true)) {
                    $add('Employment Type', 'Use 1 (Permanent), 2 (Temporary) or 3 (Probation).');
                }
            }
        }
        if ($errors) { throw new ImportValidationException($errors); }
    }

    private function resolveAreas($db, string $value): array
    {
        $value = trim($value);
        if ($value === '') { return []; }
        $find = static fn (string $part): array => $db->table('personnel_area')->select('id')
            ->groupStart()->where('area_code', $part)->orWhere('area_name', $part)->groupEnd()->get()->getResultArray();
        // Preserve existing area names that themselves contain a comma.
        $exact = $find($value);
        if (count($exact) === 1) { return [(int) $exact[0]['id']]; }
        if (count($exact) > 1) { return []; }
        $ids = [];
        foreach (explode(',', $value) as $part) {
            $part = trim($part);
            $matches = $part === '' ? [] : $find($part);
            if (count($matches) !== 1) {
                continue;
            }
            $ids[] = (int) $matches[0]['id'];
        }
        return array_values(array_unique($ids));
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
            $defaults = $table === 'personnel_area' ? ['employee_count' => 0, 'device_count' => 0] : [];
            $db->table($table)->insert($data + [$codeCol => $code, 'company_id' => $company, 'is_default' => false] + $defaults);
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
        if (strtolower($file->getClientExtension()) !== 'zip') {
            throw new ImportValidationException(['File: Expected a ZIP archive (.zip).']);
        }
        $zip = new \ZipArchive();
        if ($zip->open($file->getTempName()) !== true) {
            throw new ImportValidationException(['File: Could not read the ZIP archive.']);
        }
        $db = db_connect();
        $isPhoto = $type === 'photos';
        $dir = FCPATH . 'assets/' . ($isPhoto ? 'photos' : 'documents');
        $extensions = $isPhoto ? ['jpg', 'jpeg', 'png', 'gif'] : ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
        $errors = $pending = $seen = [];
        $total = 0;
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (str_ends_with($name, '/')) { continue; }
                $base = basename(str_replace('\\', '/', $name));
                // Ignore operating-system archive metadata.
                if (str_starts_with($name, '__MACOSX/') || str_starts_with($base, '._') || $base === '.DS_Store') { continue; }
                $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
                $stem = pathinfo($base, PATHINFO_FILENAME);
                if (! in_array($ext, $extensions, true)) {
                    $errors[] = "{$base}: Unsupported file format. Allowed: " . implode(', ', $extensions) . '.';
                    continue;
                }
                $size = (int) ($zip->statIndex($i)['size'] ?? 0);
                $total += $size;
                if ($size > 20 * 1024 * 1024 || $total > 100 * 1024 * 1024) {
                    $errors[] = "{$base}: Maximum size is 20 MB per file and 100 MB per archive after extraction.";
                    break;
                }
                $data = $zip->getFromIndex($i);
                if ($data === false || $data === '') {
                    $errors[] = "{$base}: File is empty or unreadable.";
                    continue;
                }
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
                    $info = @getimagesizefromstring($data);
                    $expected = ['jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'gif' => IMAGETYPE_GIF][$ext];
                    if (! $info || $info[2] !== $expected) {
                        $errors[] = "{$base}: Contents do not match the image format.";
                        continue;
                    }
                }
                if ($isPhoto) {
                    $record = $db->table('personnel_employee')->select('id')->where('emp_code', $stem)->get()->getRowArray();
                } else {
                    // Prefer the complete employee code, including any underscores.
                    $employee = $db->table('personnel_employee')->select('id')->where('emp_code', $stem)->get()->getRowArray();
                    $record = $employee ? $db->table('personnel_employeecertification')->select('id')
                        ->where('employee_id', $employee['id'])->orderBy('id')->get()->getRowArray() : null;
                    if (! $employee) {
                        $matches = [];
                        for ($split = 0; $split < strlen($stem); $split++) {
                            if ($stem[$split] !== '_') { continue; }
                            $match = $db->table('personnel_employeecertification ec')->select('ec.id')
                                ->join('personnel_employee e', 'e.id = ec.employee_id')
                                ->join('personnel_certification c', 'c.id = ec.certification_id')
                                ->where('e.emp_code', substr($stem, 0, $split))
                                ->where('c.cert_code', substr($stem, $split + 1))->get()->getResultArray();
                            foreach ($match as $m) { $matches[$m['id']] = $m; }
                        }
                        if (count($matches) > 1) {
                            $errors[] = "{$base}: Filename matches multiple employee certifications. Use an unambiguous employee/certification code.";
                            continue;
                        }
                        $record = $matches ? reset($matches) : null;
                    }
                }
                if (! $record) {
                    $errors[] = "{$base}: No matching " . ($isPhoto ? 'employee' : 'employee certification') . ' exists. Check the filename and create the record first.';
                    continue;
                }
                $id = (int) $record['id'];
                if (isset($seen[$id])) {
                    $errors[] = "{$base}: Duplicate attachment for the same record as {$seen[$id]}.";
                    continue;
                }
                $seen[$id] = $base;
                $safe = $id . '-' . bin2hex(random_bytes(12)) . '.' . $ext;
                $pending[] = ['id' => $id, 'name' => $base, 'safe' => $safe, 'data' => $data];
            }
        } finally {
            $zip->close();
        }
        if ($errors) { throw new ImportValidationException($errors); }
        if (! $pending) { throw new ImportValidationException(['Archive: No importable files found.']); }
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Could not create the attachment folder. No files were imported.');
        }
        $written = [];
        $db->transBegin();
        try {
            foreach ($pending as $item) {
                $path = $dir . '/' . $item['safe'];
                $written[] = $path;
                if (file_put_contents($path, $item['data']) !== strlen($item['data'])) {
                    throw new \RuntimeException('Could not save ' . $item['name']);
                }
                $table = $isPhoto ? 'personnel_employee' : 'personnel_employeecertification';
                $values = $isPhoto
                    ? ['photo' => 'photo/' . $item['safe'], 'update_time' => date('Y-m-d H:i:sP')]
                    : ['file' => 'document/' . $item['safe'], 'file_name' => mb_substr($item['name'], 0, 200)];
                if (! $db->table($table)->where('id', $item['id'])->update($values) || $db->affectedRows() !== 1) {
                    throw new \RuntimeException('Could not update the attachment record.');
                }
            }
            if (! $db->transStatus() || ! $db->transCommit()) {
                throw new \RuntimeException('Could not commit attachment records.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            foreach ($written as $path) { if (is_file($path)) { unlink($path); } }
            log_message('error', 'ZIP import: ' . $e->getMessage());
            throw new \RuntimeException('No attachments were changed. Could not save the archive; check storage permissions and linked records.');
        }
        return ['matched' => count($pending), 'skipped' => 0];
    }
}

<?php

use App\Controllers\Imports;
use App\Libraries\Xlsx;
use App\Libraries\XlsxReader;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;

final class PersonnelImportTest extends CIUnitTestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) { unlink($file); }
        parent::tearDown();
    }

    private function upload(string $bytes, string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import-test');
        $this->files[] = $path;
        file_put_contents($path, $bytes);
        return new UploadedFile($path, $name, null, strlen($bytes), UPLOAD_ERR_OK);
    }

    private function import(string $type, UploadedFile $file, bool $overwrite = true): array
    {
        return (new ReflectionMethod(Imports::class, 'importTable'))->invoke(new Imports(), $type, $file, $overwrite);
    }

    public function testAbsoluteWorksheetTarget(): void
    {
        $file = $this->upload((new Xlsx())->headers(['Employee ID'])->row(['001'])->build(), 'employees.xlsx');
        $zip = new ZipArchive();
        $zip->open($file->getTempName());
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $zip->addFromString('xl/_rels/workbook.xml.rels', str_replace('Target="worksheets/', 'Target="/xl/worksheets/', $rels));
        $zip->close();
        self::assertSame([['Employee ID'], ['001']], XlsxReader::rows($file->getTempName()));
    }

    public function testMissingHeadersGiveAnActionableError(): void
    {
        $file = $this->upload("Wrong Header,Name\n1,Example\n", 'departments.csv');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Validation failed. No records were saved.');
        $this->import('departments', $file);
    }

    public function testUnreadableFilesGiveValidationErrors(): void
    {
        foreach (['empty.csv' => '', 'broken.xlsx' => 'not a workbook', 'legacy.xls' => 'unsupported format'] as $name => $bytes) {
            try {
                $this->import('employees', $this->upload($bytes, $name));
                self::fail('Unreadable input must fail validation.');
            } catch (\App\Libraries\ImportValidationException $e) {
                self::assertStringStartsWith('File:', $e->errors[0]);
            }
        }
    }

    public function testAllImportsAndValidation(): void
    {
        // CodeIgniter's testing environment selects the isolated in-memory SQLite DB.
        $db = db_connect();
        self::assertSame('SQLite3', $db->DBDriver);
        $prefix = $db->getPrefix();
        $db->setPrefix('');
        $tables = [
            'personnel_company' => 'company_code TEXT, company_name TEXT, is_default INTEGER',
            'personnel_department' => 'dept_code TEXT, dept_name TEXT, company_id INTEGER, is_default INTEGER NOT NULL, parent_dept_id INTEGER',
            'personnel_position' => 'position_code TEXT, position_name TEXT, company_id INTEGER, is_default INTEGER NOT NULL, parent_position_id INTEGER',
            'personnel_area' => 'area_code TEXT, area_name TEXT, company_id INTEGER, is_default INTEGER NOT NULL, parent_area_id INTEGER, employee_count INTEGER NOT NULL, device_count INTEGER NOT NULL',
            'personnel_employee' => 'emp_code TEXT, emp_code_digit INTEGER, first_name TEXT, last_name TEXT, department_id INTEGER, position_id INTEGER, email TEXT, mobile TEXT, gender TEXT, hire_date TEXT, emp_type INTEGER, company_id INTEGER, is_active INTEGER, enable_payroll INTEGER, verify_mode INTEGER, dev_privilege INTEGER, app_status INTEGER, app_role INTEGER, status INTEGER, create_time TEXT, update_time TEXT, photo TEXT',
            'att_attemployee' => 'emp_id INTEGER, status INTEGER, enable_attendance INTEGER, enable_schedule INTEGER, enable_overtime INTEGER, enable_holiday INTEGER, enable_compensatory INTEGER',
            'personnel_employeeprofile' => 'emp_id INTEGER, column_order TEXT, disabled_fields TEXT, preferences TEXT',
            'payroll_emppayrollprofile' => 'employee_id INTEGER, payment_mode INTEGER, payment_type INTEGER',
            'personnel_employee_area' => 'employee_id INTEGER, area_id INTEGER',
            'personnel_certification' => 'cert_code TEXT NOT NULL, cert_name TEXT NOT NULL',
            'personnel_employeecertification' => 'employee_id INTEGER NOT NULL, certification_id INTEGER NOT NULL, expire_on TEXT, email_alert INTEGER NOT NULL, before INTEGER, file TEXT, file_name TEXT',
            'personnel_resign' => 'employee_id INTEGER NOT NULL, resign_date TEXT NOT NULL, resign_type INTEGER, disableatt INTEGER NOT NULL, reason TEXT',
            'att_paycode' => 'code TEXT, name TEXT',
            'att_leaveyearbalance' => 'employee_id INTEGER NOT NULL, pay_code_id INTEGER, year INTEGER, entitlement_days INTEGER, pre_balance INTEGER, status INTEGER NOT NULL, create_time TEXT, change_time TEXT',
        ];
        try {
            foreach ($tables as $name => $columns) {
                $db->query("CREATE TABLE $name (id INTEGER PRIMARY KEY AUTOINCREMENT, $columns)");
            }
            $db->table('personnel_company')->insert(['company_code' => 'TEST', 'company_name' => 'Test', 'is_default' => 1]);
            $db->table('att_paycode')->insert(['code' => 'AL', 'name' => 'Annual Leave']);
            $db->table('att_paycode')->insert(['code' => 'SL', 'name' => 'Sick Leave']);
            foreach (['departments', 'positions', 'areas', 'employees', 'certifications', 'employeecerts', 'resign', 'leavebalance'] as $type) {
                $spec = Imports::spec($type);
                if ($type === 'employees') {
                    $spec['sample'][1][3] = 'Development';
                    $spec['sample'][1][4] = 'Engineer';
                }
                $xlsx = (new Xlsx())->headers(array_keys($spec['cols']));
                foreach ($spec['sample'] as $row) { $xlsx->row($row); }
                $file = $this->upload($xlsx->build(), "$type.xlsx");
                self::assertSame(['created' => 2, 'updated' => 0, 'skipped' => 0], $this->import($type, $file), $type);
                self::assertSame(['created' => 0, 'updated' => 0, 'skipped' => 2], $this->import($type, $file, false), $type);
                $csv = fopen('php://temp', 'r+');
                fputcsv($csv, array_keys($spec['cols']));
                foreach ($spec['sample'] as $row) { fputcsv($csv, $row); }
                rewind($csv);
                $csvFile = $this->upload(stream_get_contents($csv), "$type.csv");
                fclose($csv);
                self::assertSame(['created' => 0, 'updated' => 2, 'skipped' => 0], $this->import($type, $csvFile), $type);
            }
            self::assertSame(0, (int) $db->table('personnel_employee')->where('emp_code', '10001')->get()->getRow()->is_active);
            $invalidFiles = [
                'certifications' => "Code,Name\nNEW,Valid\nBAD,\n",
                'employeecerts' => "Employee ID,Certification,Expire On,Email Alert,Before (days)\n10001,FIRSTAID,2026-02-30,maybe,-1\nUNKNOWN,MISSING,,,\n",
                'resign' => "Employee ID,Resign Date,Resign Type,Disable Attendance\n10001,not-a-date,invalid,maybe\nUNKNOWN,2026-01-01,,\n",
                'leavebalance' => "Employee ID,Leave Type,Year,Entitlement Days,Carried Forward\n10001,AL,1999,1.5,-1\nUNKNOWN,MISSING,2026,999999,\n",
            ];
            $resultTables = ['certifications' => 'personnel_certification', 'employeecerts' => 'personnel_employeecertification', 'resign' => 'personnel_resign', 'leavebalance' => 'att_leaveyearbalance'];
            foreach ($invalidFiles as $type => $csv) {
                try {
                    $this->import($type, $this->upload($csv, "$type.csv"));
                    self::fail("Invalid $type must fail validation.");
                } catch (\App\Libraries\ImportValidationException $e) {
                    self::assertNotEmpty($e->errors);
                    self::assertStringContainsString('Row ', implode(' ', $e->errors));
                }
                self::assertSame(2, $db->table($resultTables[$type])->countAllResults());
            }
            $this->import('leavebalance', $this->upload("Employee ID,Leave Type,Year,Entitlement Days\n10002,AL,,12\n", 'year.csv'));
            self::assertSame((int) date('Y'), (int) $db->table('att_leaveyearbalance')->orderBy('id', 'DESC')->get()->getRow()->year);
            $this->checkZipImports($db);
            $position = $db->table('personnel_position')->where('position_code', 'SR-ENG')->get()->getRowArray();
            self::assertNotNull($position['parent_position_id']);
            self::assertSame(2, $db->table('personnel_employee_area')->countAllResults());
            $multiArea = $this->upload("Employee ID,Area\n10001,\"HO,HO-2F,HO\"\n", 'multi-area.csv');
            $this->import('employees', $multiArea);
            self::assertSame(3, $db->table('personnel_employee_area')->countAllResults(), 'Both areas are assigned without duplicate links.');
            $result = $this->import('employees', $this->upload("Employee ID,Area\n10001,\"HO,MISSING\"\n", 'bad-area.csv'));
            self::assertSame(1, $result['updated']);
            $result = $this->import('employees', $this->upload("Employee ID,Department Name,Position Name,Area Name\nOPTIONAL,Missing,Missing,Missing\n", 'optional.csv'));
            self::assertSame(1, $result['created']);
            $optional = $db->table('personnel_employee')->where('emp_code', 'OPTIONAL')->get()->getRowArray();
            self::assertNull($optional['department_id']);
            self::assertNull($optional['position_id']);
            self::assertSame(2, $db->table('personnel_employee_area')->countAllResults());
            $this->import('employees', $this->upload("Employee ID,Area\n10001,HO\n", 'single-area.csv'), false);
            self::assertSame(2, $db->table('personnel_employee_area')->countAllResults(), 'Ignore preserves existing area assignments.');
            self::assertSame(3, $db->table('att_attemployee')->countAllResults());
            $invalid = $this->upload("Employee ID,Email,Gender,Hired Date,Department,Employment Type\nNEW1,valid@example.com,M,2026-11-14,Development,1\nNEW2,bad-email,X,2026-02-30,Missing,9\nNEW2,,,,,\n", 'invalid.csv');
            try {
                $this->import('employees', $invalid);
                self::fail('Invalid rows must reject the entire import.');
            } catch (\App\Libraries\ImportValidationException $e) {
                $messages = implode("\n", $e->errors);
                foreach (['Row 3 — Email', 'Row 3 — Gender', 'Row 3 — Hired Date', 'Row 3 — Employment Type', 'Row 4 — Employee ID'] as $expected) {
                    self::assertStringContainsString($expected, $messages);
                }
            }
            self::assertSame(3, $db->table('personnel_employee')->countAllResults(), 'No valid rows from an invalid file may be saved.');
            $invalidExcel = (new Xlsx())->headers(['Employee ID', 'Email'])->row(['EXCEL1', 'valid@example.com'])->row([])->row(['EXCEL2', 'bad-email']);
            try {
                $this->import('employees', $this->upload($invalidExcel->build(), 'invalid.xlsx'));
                self::fail('Invalid Excel data must reject the entire workbook.');
            } catch (\App\Libraries\ImportValidationException $e) {
                self::assertStringContainsString('Row 4 — Email', implode("\n", $e->errors));
            }
            self::assertSame(3, $db->table('personnel_employee')->countAllResults());
            foreach (['departments' => 'Department', 'positions' => 'Position', 'areas' => 'Area'] as $type => $label) {
                $invalid = $this->upload("{$label} Code,{$label} Name\nNEW,Valid\nBAD,\n", "$type.csv");
                try {
                    $this->import($type, $invalid);
                    self::fail('Missing names must reject the entire import.');
                } catch (\App\Libraries\ImportValidationException $e) {
                    self::assertStringContainsString("Row 3 — {$label} Name", implode("\n", $e->errors));
                }
                $table = ['departments' => 'personnel_department', 'positions' => 'personnel_position', 'areas' => 'personnel_area'][$type];
                self::assertSame(2, $db->table($table)->countAllResults());
            }
            $db->query("CREATE TRIGGER reject_import BEFORE INSERT ON personnel_department WHEN NEW.dept_code = 'FAIL' BEGIN SELECT RAISE(ABORT, 'Simulated constraint failure'); END");
            try {
                $this->import('departments', $this->upload("Department Code,Department Name\nROLLBACK,First\nFAIL,Second\n", 'rollback.csv'));
                self::fail('Database failures must roll back the import.');
            } catch (RuntimeException $e) {
                self::assertStringContainsString('No records were saved.', $e->getMessage());
            }
            self::assertSame(2, $db->table('personnel_department')->countAllResults());
        } finally {
            foreach (array_reverse(array_keys($tables)) as $table) { $db->query("DROP TABLE IF EXISTS $table"); }
            $db->setPrefix($prefix);
        }
    }

    private function archive(array $entries): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'zip-test');
        $this->files[] = $path;
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($entries as $name => $data) { $zip->addFromString($name, $data); }
        $zip->close();
        return new UploadedFile($path, 'attachments.zip', null, filesize($path), UPLOAD_ERR_OK);
    }

    private function checkZipImports($db): void
    {
        $method = new ReflectionMethod(Imports::class, 'importZip');
        $controller = new Imports();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2ioAAAAASUVORK5CYII=');
        $result = $method->invoke($controller, 'photos', $this->archive(['10001.png' => $png]));
        self::assertSame(['matched' => 1, 'skipped' => 0], $result);
        $photo = $db->table('personnel_employee')->where('emp_code', '10001')->get()->getRow()->photo;
        $photoPath = FCPATH . 'assets/photos/' . basename($photo);
        $this->files[] = $photoPath;
        self::assertSame($png, file_get_contents($photoPath));
        foreach ([['10001.png' => $png, 'MISSING.png' => $png], ['10001.jpg' => 'invalid image'], ['10001.png' => $png, 'folder/10001.png' => $png]] as $entries) {
            try {
                $method->invoke($controller, 'photos', $this->archive($entries));
                self::fail('Invalid ZIP must fail before any write.');
            } catch (\App\Libraries\ImportValidationException $e) {
                self::assertNotEmpty($e->errors);
            }
            self::assertSame($photo, $db->table('personnel_employee')->where('emp_code', '10001')->get()->getRow()->photo);
            self::assertSame($png, file_get_contents($photoPath));
        }
        $filesBefore = glob(FCPATH . 'assets/photos/*');
        $db->query("CREATE TRIGGER reject_photo BEFORE UPDATE OF photo ON personnel_employee WHEN NEW.emp_code = '10002' BEGIN SELECT RAISE(ABORT, 'Simulated photo failure'); END");
        try {
            $method->invoke($controller, 'photos', $this->archive(['10001.png' => $png, '10002.png' => $png]));
            self::fail('A failed ZIP database write must roll back all attachments.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('No attachments were changed.', $e->getMessage());
        } finally {
            $db->query('DROP TRIGGER reject_photo');
            $db->resetTransStatus();
        }
        self::assertSame($photo, $db->table('personnel_employee')->where('emp_code', '10001')->get()->getRow()->photo);
        self::assertSame($filesBefore, glob(FCPATH . 'assets/photos/*'), 'New files must be removed after rollback.');
        self::assertSame($png, file_get_contents($photoPath));
        // Both employee and certification codes may contain underscores.
        $db->table('personnel_employee')->where('emp_code', '10001')->update(['emp_code' => 'EMP_10001']);
        $db->table('personnel_certification')->where('cert_code', 'FIRSTAID')->update(['cert_code' => 'FIRST_AID']);
        try {
            $result = $method->invoke($controller, 'documents', $this->archive(['EMP_10001_FIRST_AID.pdf' => "%PDF-1.4\nTest attachment"]));
            self::assertSame(['matched' => 1, 'skipped' => 0], $result);
            $record = $db->table('personnel_employeecertification')->where('file_name', 'EMP_10001_FIRST_AID.pdf')->get()->getRow();
            $path = FCPATH . 'assets/documents/' . basename($record->file);
            $this->files[] = $path;
            self::assertFileExists($path);
            $result = $method->invoke($controller, 'documents', $this->archive(['EMP_10001.pdf' => "%PDF-1.4\nUpdated attachment"]));
            self::assertSame(['matched' => 1, 'skipped' => 0], $result);
            $updated = $db->table('personnel_employeecertification')->where('id', $record->id)->get()->getRow()->file;
            $this->files[] = FCPATH . 'assets/documents/' . basename($updated);
            self::assertNotSame($record->file, $updated);
            try {
                $method->invoke($controller, 'documents', $this->archive(['UNKNOWN.pdf' => '%PDF-1.4']));
                self::fail('Unknown document targets must fail.');
            } catch (\App\Libraries\ImportValidationException $e) {
                self::assertStringContainsString('No matching employee certification', implode(' ', $e->errors));
            }
        } finally {
            $db->table('personnel_employee')->where('emp_code', 'EMP_10001')->update(['emp_code' => '10001']);
            $db->table('personnel_certification')->where('cert_code', 'FIRST_AID')->update(['cert_code' => 'FIRSTAID']);
        }
    }

    public function testEveryImportPageAndTemplateRenders(): void
    {
        service('routes')->loadRoutes();
        helper(['form', 'url', 'text', 'ui', 'field', 'grid']);
        $controller = new Imports();
        foreach (Imports::types() as $type => $label) {
            $html = $controller->index($type);
            self::assertStringContainsString('multipart/form-data', $html, $type);
            self::assertStringContainsString('/imports/' . $type . '/run', $html, $type);
            self::assertStringContainsString('importFileHint', $html, $type);
            if ($spec = Imports::spec($type)) {
                self::assertStringContainsString('name="existing"', $html, $type);
                self::assertStringContainsString('/imports/' . $type . '/template', $html, $type);
                $response = $controller->template($type);
                $file = $this->upload($response->getBody(), "$type.xlsx");
                self::assertSame(array_keys($spec['cols']), XlsxReader::rows($file->getTempName())[0]);
                self::assertStringContainsString('/imports/' . $type . '/template', str_replace('\\/', '/', grid_import_specs_json([$type])));
            }
        }
        session()->setFlashdata('import_errors', ['Row 3 — Email: Invalid <script>example</script>']);
        $html = $controller->index('employees');
        self::assertStringContainsString('Import validation failed. No records were saved.', $html);
        self::assertStringContainsString('&lt;script&gt;example&lt;/script&gt;', $html);
    }
}





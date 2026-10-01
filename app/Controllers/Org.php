<?php

namespace App\Controllers;

/**
 * Organisation structure — companies, departments, positions and areas
 * (personnel_company / personnel_department / personnel_position /
 * personnel_area). Departments, positions and areas are self-referential
 * trees; a department can also name a manager (an employee).
 *
 * These tables are referenced across the whole product (employees, shifts,
 * holidays, access, devices) but ZKBioTime only ships them as pick-lists —
 * this screen makes them editable, with delete guards so a row that is
 * still in use cannot be removed.
 */
class Org extends BaseController
{
    private const TABS = [
        'departments' => 'Departments',
        'positions'   => 'Positions',
        'areas'       => 'Areas',
        'companies'   => 'Companies',
    ];

    public function index(string $tab = 'departments')
    {
        if (! isset(self::TABS[$tab])) {
            $tab = 'departments';
        }

        // AJAX (tt-crud refresh) → just the results partial
        if ($this->wantsJson()) {
            return view('admin/org/_list', $this->tabData($tab));
        }

        return view('admin/org/index', $this->tabData($tab));
    }

    /** Everything a tab's list + its modal need. */
    private function tabData(string $tab): array
    {
        $db = db_connect();

        $d = [
            'title'     => rtrim(self::TABS[$tab], 's'),
            'subtitle'  => self::TABS[$tab],
            'active'    => $tab,   // 'departments' | 'positions' | 'areas' | 'companies'
            'tab'       => $tab,
            'tabs'      => self::TABS,
            'companies' => $db->query('SELECT id, company_code, company_name, is_default FROM personnel_company ORDER BY is_default DESC, company_name')->getResultArray(),
        ];

        switch ($tab) {
            case 'companies':
                $b = $db->table('personnel_company c')
                    ->select("c.*,
                              (SELECT count(*) FROM personnel_department x WHERE x.company_id = c.id) dept_count,
                              (SELECT count(*) FROM personnel_employee   x WHERE x.company_id = c.id) emp_count")
                    ->orderBy('c.is_default', 'DESC')->orderBy('c.company_name');
                $spec = ['company_code' => ['c.company_code', 'text'], 'company_name' => ['c.company_name', 'text'], 'is_default' => ['c.is_default', 'bool']];
                break;

            case 'positions':
                $b = $db->table('personnel_position p')
                    ->select("p.id, p.position_code, p.position_name, p.is_default, p.company_id,
                              p.parent_position_id, pp.position_name AS parent_name, c.company_name,
                              (SELECT count(*) FROM personnel_employee e WHERE e.position_id = p.id) emp_count")
                    ->join('personnel_position pp', 'pp.id = p.parent_position_id', 'left')
                    ->join('personnel_company c', 'c.id = p.company_id', 'left')
                    ->orderBy('c.company_name')->orderBy('p.position_name');
                $spec = ['position_code' => ['p.position_code', 'text'], 'position_name' => ['p.position_name', 'text'], 'company_name' => ['c.company_name', 'text']];
                break;

            case 'areas':
                $b = $db->table('personnel_area a')
                    ->select("a.id, a.area_code, a.area_name, a.is_default, a.company_id,
                              a.parent_area_id, pa.area_name AS parent_name, c.company_name,
                              (SELECT count(*) FROM personnel_employee_area ea WHERE ea.area_id = a.id) emp_count,
                              (SELECT count(*) FROM iclock_terminal t WHERE t.area_id = a.id) dev_count")
                    ->join('personnel_area pa', 'pa.id = a.parent_area_id', 'left')
                    ->join('personnel_company c', 'c.id = a.company_id', 'left')
                    ->orderBy('c.company_name')->orderBy('a.area_name');
                $spec = ['area_code' => ['a.area_code', 'text'], 'area_name' => ['a.area_name', 'text'], 'company_name' => ['c.company_name', 'text']];
                break;

            default: // departments
                $b = $db->table('personnel_department d')
                    ->select("d.id, d.dept_code, d.dept_name, d.is_default, d.company_id,
                              d.parent_dept_id, pd.dept_name AS parent_name, c.company_name,
                              d.dept_manager_id,
                              NULLIF(TRIM(CONCAT(m.first_name,' ',m.last_name)),'') AS manager_name,
                              (SELECT count(*) FROM personnel_employee e WHERE e.department_id = d.id) emp_count")
                    ->join('personnel_department pd', 'pd.id = d.parent_dept_id', 'left')
                    ->join('personnel_company c', 'c.id = d.company_id', 'left')
                    ->join('personnel_employee m', 'm.id = d.dept_manager_id', 'left')
                    ->orderBy('c.company_name')->orderBy('d.dept_name');
                $spec = [
                    'dept_code'    => ['d.dept_code', 'text'],
                    'dept_name'    => ['d.dept_name', 'text'],
                    'company_name' => ['c.company_name', 'text'],
                    'manager_name' => ["TRIM(CONCAT(m.first_name,' ',m.last_name))", 'text'],
                ];
                $d['managers'] = $db->query(
                    "SELECT id, emp_code, NULLIF(TRIM(CONCAT(first_name,' ',last_name)),'') name
                       FROM personnel_employee WHERE is_active = true ORDER BY emp_code_digit"
                )->getResultArray();
                break;
        }

        $d['fbApplied'] = $this->filterBuilder($b, $spec);
        [$d['rows'], $d['pager']] = $this->paged($b, 20);

        return $d;
    }

    /** End an AJAX/redirect CRUD action, re-rendering the tab's list for AJAX callers. */
    private function done(string $tab, string $flashType, string $message)
    {
        return $this->crudDone(
            route_to('org.tab', $tab),
            $flashType,
            $message,
            $flashType === 'success' ? view('admin/org/_list', $this->tabData($tab)) : null,
        );
    }

    /** Validation failure → inline errors for AJAX, flash + modal-reopen otherwise. */
    private function invalid(string $modal)
    {
        if ($this->wantsJson()) {
            return $this->jsonFail('Please correct the highlighted fields.', $this->validator->getErrors());
        }

        return redirect()->back()->withInput()->with('errors', $this->validator->getErrors())->with('reopen', $modal);
    }

    /** A soft failure (FK blocker, business rule) → toast for AJAX, flash otherwise. */
    private function fail(string $message)
    {
        return $this->wantsJson()
            ? $this->jsonFail($message)
            : redirect()->back()->withInput()->with('error', $message);
    }

    // ----------------------------------------------------------------- companies

    /** ZKBioTime company address / branding position options. */
    public const POS = [0 => 'Not displayed', 1 => 'Left', 2 => 'Centre', 3 => 'Right'];

    public function saveCompany()
    {
        if (! $this->validate([
            'company_code' => 'required|max_length[50]',
            'company_name' => 'required|max_length[100]',
            'email'        => 'permit_empty|valid_email|max_length[50]',
            'logo'         => 'permit_empty|is_image[logo]|max_size[logo,4096]|ext_in[logo,jpg,jpeg,png,gif]',
        ], [
            'company_code' => ['required' => 'Code is required.', 'max_length' => 'Code cannot exceed 50 characters.'],
            'company_name' => ['required' => 'Name is required.', 'max_length' => 'Name cannot exceed 100 characters.'],
            'email'        => ['valid_email' => 'Enter a valid email address.'],
        ])) {
            return $this->invalid('coForm');
        }
        $p   = $this->request;
        $id  = (int) $p->getPost('id');
        $def = (bool) $p->getPost('is_default');
        $sn  = static fn (string $k) => ($v = trim((string) $p->getPost($k))) !== '' ? $v : null;

        $data = [
            'company_code' => trim($p->getPost('company_code')),
            'company_name' => trim($p->getPost('company_name')),
            'country'      => $sn('country'),
            'state'        => $sn('state'),
            'city'         => $sn('city'),
            'address'      => $sn('address'),
            'address2'     => $sn('address2'),
            'postal_code'  => $sn('postal_code'),
            'phone'        => $sn('phone'),
            'fax'          => $sn('fax'),
            'email'        => $sn('email'),
            'website'      => $sn('website'),
            'logo_pos'     => (int) $p->getPost('logo_pos'),
            'name_pos'     => (int) $p->getPost('name_pos'),
            'is_default'   => $def,
        ];

        // logo upload → public/assets/logos/<code>.<ext>, stored as "logo/<name>"
        $file = $p->getFile('logo');
        if ($file && $file->isValid() && ! $file->hasMoved()) {
            $dir = FCPATH . 'assets/logos';
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $data['company_code']) ?: ('co' . $id);
            $name = $safe . '.' . strtolower($file->getExtension() ?: 'png');
            try {
                $file->move($dir, $name, true);
                $data['logo'] = 'logo/' . $name;
            } catch (\Throwable $e) {
                log_message('error', 'company logo: ' . $e->getMessage());
            }
        } elseif ($p->getPost('logo_remove')) {
            $data['logo'] = null;
        }

        $ok = $this->tx(function ($db) use ($id, $def, $data) {
            if ($id) {
                $db->table('personnel_company')->where('id', $id)->update($data);
            } else {
                $db->table('personnel_company')->insert($data + [
                    'status' => 0, 'create_time' => date('Y-m-d H:i:sP'), 'create_user' => session()->get('userName'),
                ]);
                $id = (int) $db->insertID();
            }
            if ($def) {
                $db->table('personnel_company')->where('id !=', $id)->update(['is_default' => false]);
            }
        });
        if (! $ok) {
            return $this->fail('Company could not be saved — the code may already be in use.');
        }
        $this->audit($id ? 'org.company.update' : 'org.company.create', $data['company_code']);

        return $this->done('companies', 'success', 'Company saved.');
    }

    /**
     * ZKBioTime-style "Delete" toolbar button — remove every ticked row that
     * has no dependants; report how many went and how many were kept.
     */
    public function bulkDelete(string $tab)
    {
        $map = [
            'departments' => ['personnel_department', 'org.department', fn ($id) => [
                'in use by employees'   => "SELECT 1 FROM personnel_employee WHERE department_id = {$id} LIMIT 1",
                'has sub-departments'   => "SELECT 1 FROM personnel_department WHERE parent_dept_id = {$id} LIMIT 1",
                'scoped by a holiday'   => "SELECT 1 FROM att_holiday WHERE department_id = {$id} LIMIT 1",
            ]],
            'positions' => ['personnel_position', 'org.position', fn ($id) => [
                'held by employees'   => "SELECT 1 FROM personnel_employee WHERE position_id = {$id} LIMIT 1",
                'has child positions' => "SELECT 1 FROM personnel_position WHERE parent_position_id = {$id} LIMIT 1",
            ]],
            'areas' => ['personnel_area', 'org.area', fn ($id) => [
                'assigned to employees' => "SELECT 1 FROM personnel_employee_area WHERE area_id = {$id} LIMIT 1",
                'holds devices'         => "SELECT 1 FROM iclock_terminal WHERE area_id = {$id} LIMIT 1",
                'has sub-areas'         => "SELECT 1 FROM personnel_area WHERE parent_area_id = {$id} LIMIT 1",
                'used by access rules'  => "SELECT 1 FROM acc_accprivilege WHERE area_id = {$id} LIMIT 1",
            ]],
            'companies' => ['personnel_company', 'org.company', fn ($id) => [
                'has departments' => "SELECT 1 FROM personnel_department WHERE company_id = {$id} LIMIT 1",
                'has positions'   => "SELECT 1 FROM personnel_position WHERE company_id = {$id} LIMIT 1",
                'has areas'       => "SELECT 1 FROM personnel_area WHERE company_id = {$id} LIMIT 1",
                'has employees'   => "SELECT 1 FROM personnel_employee WHERE company_id = {$id} LIMIT 1",
            ]],
        ];
        if (! isset($map[$tab])) {
            return $this->fail('Unknown list.');
        }
        [$table, $auditPfx, $probes] = $map[$tab];

        $ids = array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
        if (! $ids) {
            return redirect()->back()->with('error', 'Nothing selected.');
        }

        $db = db_connect();
        $gone = $kept = 0;
        foreach ($ids as $id) {
            if ($this->firstBlocker($db, $probes($id))
                || ($tab === 'companies' && $db->table('personnel_company')->where('id', $id)->where('is_default', true)->countAllResults())) {
                $kept++;
                continue;
            }
            $db->table($table)->where('id', $id)->delete();
            $gone++;
        }
        $this->audit($auditPfx . '.bulk_delete', "{$gone} deleted, {$kept} kept");

        return $this->done($tab, $gone ? 'success' : 'error',
            "Deleted {$gone}." . ($kept ? " Kept {$kept} still in use." : ''));
    }

    public function deleteCompany(int $id)
    {
        $db  = db_connect();
        $blk = $this->firstBlocker($db, [
            'This company still has departments'          => "SELECT 1 FROM personnel_department WHERE company_id = {$id} LIMIT 1",
            'This company still has positions'            => "SELECT 1 FROM personnel_position   WHERE company_id = {$id} LIMIT 1",
            'This company still has areas'                => "SELECT 1 FROM personnel_area        WHERE company_id = {$id} LIMIT 1",
            'Employees are still assigned to this company' => "SELECT 1 FROM personnel_employee    WHERE company_id = {$id} LIMIT 1",
        ]);
        if ($blk) {
            return $this->fail($blk . ' — reassign or remove them first.');
        }
        if ($db->table('personnel_company')->where('id', $id)->where('is_default', true)->countAllResults()) {
            return $this->fail('The default company cannot be deleted.');
        }
        $db->table('personnel_company')->where('id', $id)->delete();
        $this->audit('org.company.delete', (string) $id);

        return $this->done('companies', 'success', 'Company deleted.');
    }

    // --------------------------------------------------------------- departments

    public function saveDepartment()
    {
        if (! $this->validate(['dept_code' => 'required|max_length[50]', 'dept_name' => 'required|max_length[200]', 'company_id' => 'required|is_natural_no_zero'])) {
            return $this->invalid('deForm');
        }
        $p      = $this->request;
        $id     = (int) $p->getPost('id');
        $parent = (int) $p->getPost('parent_dept_id') ?: null;
        if ($parent && $parent === $id) {
            return $this->fail('A department cannot be its own parent.');
        }
        $def  = (bool) $p->getPost('is_default');
        $data = [
            'dept_code'       => trim($p->getPost('dept_code')),
            'dept_name'       => trim($p->getPost('dept_name')),
            'company_id'      => (int) $p->getPost('company_id'),
            'parent_dept_id'  => $parent,
            'dept_manager_id' => (int) $p->getPost('dept_manager_id') ?: null,
            'is_default'      => $def,
        ];

        $ok = $this->tx(function ($db) use ($id, $def, $data) {
            if ($id) {
                $db->table('personnel_department')->where('id', $id)->update($data);
            } else {
                $db->table('personnel_department')->insert($data);
                $id = (int) $db->insertID();
            }
            if ($def) {
                $db->table('personnel_department')->where('id !=', $id)->update(['is_default' => false]);
            }
        });
        if (! $ok) {
            return $this->fail('Department could not be saved — the code must be unique within the company.');
        }
        $this->audit($id ? 'org.department.update' : 'org.department.create', $data['dept_code']);

        return $this->done('departments', 'success', 'Department saved.');
    }

    public function deleteDepartment(int $id)
    {
        $db  = db_connect();
        $blk = $this->firstBlocker($db, [
            'Employees are still in this department' => "SELECT 1 FROM personnel_employee WHERE department_id = {$id} LIMIT 1",
            'This department has sub-departments'    => "SELECT 1 FROM personnel_department WHERE parent_dept_id = {$id} LIMIT 1",
            'Holidays are scoped to this department' => "SELECT 1 FROM att_holiday WHERE department_id = {$id} LIMIT 1",
        ]);
        if ($blk) {
            return $this->fail($blk . ' — reassign them first.');
        }
        $db->table('personnel_department')->where('id', $id)->delete();
        $this->audit('org.department.delete', (string) $id);

        return $this->done('departments', 'success', 'Department deleted.');
    }

    // ----------------------------------------------------------------- positions

    public function savePosition()
    {
        if (! $this->validate(['position_code' => 'required|max_length[50]', 'position_name' => 'required|max_length[100]', 'company_id' => 'required|is_natural_no_zero'])) {
            return $this->invalid('poForm');
        }
        $p      = $this->request;
        $id     = (int) $p->getPost('id');
        $parent = (int) $p->getPost('parent_position_id') ?: null;
        if ($parent && $parent === $id) {
            return $this->fail('A position cannot report to itself.');
        }
        $def  = (bool) $p->getPost('is_default');
        $data = [
            'position_code'      => trim($p->getPost('position_code')),
            'position_name'      => trim($p->getPost('position_name')),
            'company_id'         => (int) $p->getPost('company_id'),
            'parent_position_id' => $parent,
            'is_default'         => $def,
        ];

        $ok = $this->tx(function ($db) use ($id, $def, $data) {
            if ($id) {
                $db->table('personnel_position')->where('id', $id)->update($data);
            } else {
                $db->table('personnel_position')->insert($data);
                $id = (int) $db->insertID();
            }
            if ($def) {
                $db->table('personnel_position')->where('id !=', $id)->update(['is_default' => false]);
            }
        });
        if (! $ok) {
            return $this->fail('Position could not be saved — the code must be unique within the company.');
        }
        $this->audit($id ? 'org.position.update' : 'org.position.create', $data['position_code']);

        return $this->done('positions', 'success', 'Position saved.');
    }

    public function deletePosition(int $id)
    {
        $db  = db_connect();
        $blk = $this->firstBlocker($db, [
            'Employees still hold this position' => "SELECT 1 FROM personnel_employee WHERE position_id = {$id} LIMIT 1",
            'This position has child positions' => "SELECT 1 FROM personnel_position WHERE parent_position_id = {$id} LIMIT 1",
        ]);
        if ($blk) {
            return $this->fail($blk . ' — reassign them first.');
        }
        $db->table('personnel_position')->where('id', $id)->delete();
        $this->audit('org.position.delete', (string) $id);

        return $this->done('positions', 'success', 'Position deleted.');
    }

    // --------------------------------------------------------------------- areas

    public function saveArea()
    {
        if (! $this->validate(['area_code' => 'required|max_length[30]', 'area_name' => 'required|max_length[100]', 'company_id' => 'required|is_natural_no_zero'])) {
            return $this->invalid('arForm');
        }
        $p      = $this->request;
        $id     = (int) $p->getPost('id');
        $parent = (int) $p->getPost('parent_area_id') ?: null;
        if ($parent && $parent === $id) {
            return $this->fail('An area cannot be its own parent.');
        }
        $def  = (bool) $p->getPost('is_default');
        $data = [
            'area_code'      => trim($p->getPost('area_code')),
            'area_name'      => trim($p->getPost('area_name')),
            'company_id'     => (int) $p->getPost('company_id'),
            'parent_area_id' => $parent,
            'is_default'     => $def,
        ];

        $ok = $this->tx(function ($db) use ($id, $def, $data) {
            if ($id) {
                $db->table('personnel_area')->where('id', $id)->update($data);
            } else {
                $db->table('personnel_area')->insert($data + ['employee_count' => 0, 'device_count' => 0]);
                $id = (int) $db->insertID();
            }
            if ($def) {
                $db->table('personnel_area')->where('id !=', $id)->update(['is_default' => false]);
            }
        });
        if (! $ok) {
            return $this->fail('Area could not be saved — the code must be unique within the company.');
        }
        $this->audit($id ? 'org.area.update' : 'org.area.create', $data['area_code']);

        return $this->done('areas', 'success', 'Area saved.');
    }

    public function deleteArea(int $id)
    {
        $db  = db_connect();
        $blk = $this->firstBlocker($db, [
            'Employees are assigned to this area' => "SELECT 1 FROM personnel_employee_area WHERE area_id = {$id} LIMIT 1",
            'Devices are placed in this area'     => "SELECT 1 FROM iclock_terminal WHERE area_id = {$id} LIMIT 1",
            'This area has sub-areas'             => "SELECT 1 FROM personnel_area WHERE parent_area_id = {$id} LIMIT 1",
            'Access rules reference this area'    => "SELECT 1 FROM acc_accprivilege WHERE area_id = {$id} LIMIT 1",
        ]);
        if ($blk) {
            return $this->fail($blk . ' — reassign them first.');
        }
        $db->table('personnel_area')->where('id', $id)->delete();
        $this->audit('org.area.delete', (string) $id);

        return $this->done('areas', 'success', 'Area deleted.');
    }

}

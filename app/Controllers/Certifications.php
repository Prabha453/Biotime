<?php

namespace App\Controllers;

/**
 * Certifications — a type list (personnel_certification) and, per employee,
 * a held certification with an optional expiry date and reminder window
 * (personnel_employeecertification). The dashboard surfaces the ones that
 * are expired or expiring inside their reminder window.
 */
class Certifications extends BaseController
{
    private const TABS = ['register' => 'Employee certifications', 'types' => 'Certification types'];

    /** default reminder window (days) when a row doesn't set its own */
    public const DEFAULT_BEFORE = 30;

    public function index(string $tab = 'register'): string
    {
        if (! isset(self::TABS[$tab])) {
            $tab = 'register';
        }
        $db  = db_connect();
        $now = date('Y-m-d');

        $d = [
            'title'    => $tab === 'types' ? 'Certification' : 'Employee Certification',
            'subtitle' => $tab === 'types' ? 'Certification types' : 'Certifications held by employees, with expiry tracking',
            'active'   => $tab === 'types' ? 'cert.types' : 'certifications',
            'tab'      => $tab,
            'tabs'     => self::TABS,
            'types'    => $db->query('SELECT id, cert_code, cert_name FROM personnel_certification ORDER BY cert_name')->getResultArray(),
        ];

        if ($tab === 'types') {
            $d['rows'] = $db->query(
                "SELECT t.id, t.cert_code, t.cert_name,
                        (SELECT count(*) FROM personnel_employeecertification ec WHERE ec.certification_id = t.id) held
                   FROM personnel_certification t ORDER BY t.cert_name"
            )->getResultArray();
        } else {
            $status = $this->request->getGet('status');
            $b = $db->table('personnel_employeecertification ec')
                ->select("ec.id, ec.employee_id, ec.certification_id, ec.expire_on, ec.email_alert,
                          ec.before, ec.certification_code, ec.file, ec.file_name, t.cert_name, e.emp_code,
                          NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS emp_name,
                          (ec.expire_on - CURRENT_DATE) AS days_left")
                ->join('personnel_certification t', 't.id = ec.certification_id')
                ->join('personnel_employee e', 'e.id = ec.employee_id')
                ->orderBy('ec.expire_on', 'ASC');   // Postgres sorts NULLs last on ASC

            $win = '(COALESCE(ec.before, ' . self::DEFAULT_BEFORE . ") || ' days')::interval";
            if ($status === 'expired') {
                $b->where('ec.expire_on IS NOT NULL AND ec.expire_on < CURRENT_DATE');
            } elseif ($status === 'expiring') {
                $b->where("ec.expire_on >= CURRENT_DATE AND ec.expire_on <= CURRENT_DATE + {$win}");
            } elseif ($status === 'valid') {
                $b->where("ec.expire_on IS NULL OR ec.expire_on > CURRENT_DATE + {$win}");
            }

            $d['fbApplied'] = $this->filterBuilder($b, [
                'emp_code'  => ['e.emp_code', 'text'],
                'emp_name'  => ["TRIM(CONCAT(e.first_name,' ',e.last_name))", 'text'],
                'cert_name' => ['t.cert_name', 'text'],
                'cert_code' => ['t.cert_code', 'text'],
                'expire_on' => ['ec.expire_on', 'date'],
                'email_alert' => ['ec.email_alert', 'bool'],
            ]);

            [$d['rows'], $d['pager']] = $this->paged($b, 25);
            $d['status']    = $status;
            $d['employees'] = $db->query("SELECT id, emp_code, NULLIF(TRIM(CONCAT(first_name,' ',last_name)),'') name FROM personnel_employee WHERE is_active = true ORDER BY emp_code_digit")->getResultArray();
            $d['counts']    = $db->query(
                "SELECT
                    count(*) FILTER (WHERE expire_on < CURRENT_DATE) AS expired,
                    count(*) FILTER (WHERE expire_on >= CURRENT_DATE AND expire_on <= CURRENT_DATE + (COALESCE(before, " . self::DEFAULT_BEFORE . ") || ' days')::interval) AS expiring
                   FROM personnel_employeecertification"
            )->getRowArray();
        }

        return view('admin/certifications/index', $d);
    }

    // ------------------------------------------------------------------- types

    public function saveType()
    {
        if (! $this->validate(['cert_code' => 'required|max_length[20]', 'cert_name' => 'required|max_length[100]'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'ctForm');
        }
        $p    = $this->request;
        $id   = (int) $p->getPost('id');
        $data = ['cert_code' => trim($p->getPost('cert_code')), 'cert_name' => trim($p->getPost('cert_name'))];

        $db = db_connect();
        $ok = $this->dbGuard(fn () => $id
            ? $db->table('personnel_certification')->where('id', $id)->update($data)
            : $db->table('personnel_certification')->insert($data));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Type could not be saved — the code and name must be unique.');
        }
        $this->audit($id ? 'cert.type.update' : 'cert.type.create', $data['cert_code']);

        return redirect()->to(route_to('certifications.tab', 'types'))->with('success', 'Certification type saved.');
    }

    public function deleteType(int $id)
    {
        $db = db_connect();
        if ($db->table('personnel_employeecertification')->where('certification_id', $id)->countAllResults()) {
            return redirect()->back()->with('error', 'Employees hold this certification — remove those records first.');
        }
        if ($db->table('visitor_visitor')->where('cert_type_id', $id)->countAllResults()
            || $db->table('visitor_reservation')->where('cert_type_id', $id)->countAllResults()) {
            return redirect()->back()->with('error', 'This type is in use as a visitor ID type and cannot be deleted.');
        }
        $db->table('personnel_certification')->where('id', $id)->delete();
        $this->audit('cert.type.delete', (string) $id);

        return redirect()->to(route_to('certifications.tab', 'types'))->with('success', 'Certification type deleted.');
    }

    // ---------------------------------------------------------------- register

    public function save()
    {
        if (! $this->validate([
            'employee_id'      => 'required|is_natural_no_zero',
            'certification_id' => 'required|is_natural_no_zero',
            'expire_on'        => 'permit_empty|valid_date',
            'before'           => 'permit_empty|is_natural',
            'document'         => 'permit_empty|max_size[document,8192]|ext_in[document,pdf,jpg,jpeg,png,doc,docx]',
        ])) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'ecForm');
        }
        $p    = $this->request;
        $id   = (int) $p->getPost('id');
        $data = [
            'employee_id'        => (int) $p->getPost('employee_id'),
            'certification_id'   => (int) $p->getPost('certification_id'),
            'certification_code' => $p->getPost('certification_code') ?: null,
            'expire_on'          => $p->getPost('expire_on') ?: null,
            'before'             => $p->getPost('before') !== '' ? (int) $p->getPost('before') : null,
            'email_alert'        => (bool) $p->getPost('email_alert'),
        ];

        // scanned document → public/assets/documents/<emp>-<cert>-<ts>.<ext>, stored as "document/<name>"
        $doc = $p->getFile('document');
        if ($doc && $doc->isValid() && ! $doc->hasMoved()) {
            $dir = FCPATH . 'assets/documents';
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $name = $data['employee_id'] . '-' . $data['certification_id'] . '-' . time() . '.' . strtolower($doc->getExtension() ?: 'dat');
            try {
                $orig = $doc->getClientName();
                $doc->move($dir, $name, true);
                $data['file']      = 'document/' . $name;
                $data['file_name'] = mb_substr($orig, 0, 200);
            } catch (\Throwable $e) {
                log_message('error', 'certification document: ' . $e->getMessage());
            }
        } elseif ($p->getPost('document_remove')) {
            $data['file']      = null;
            $data['file_name'] = null;
        }

        $db = db_connect();
        $ok = $this->dbGuard(fn () => $id
            ? $db->table('personnel_employeecertification')->where('id', $id)->update($data)
            : $db->table('personnel_employeecertification')->insert($data));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Could not save — this employee may already hold that certification.');
        }
        $this->audit($id ? 'cert.record.update' : 'cert.record.create', 'emp=' . $data['employee_id']);

        return redirect()->to(route_to('certifications'))->with('success', 'Certification record saved.');
    }

    public function delete(int $id)
    {
        db_connect()->table('personnel_employeecertification')->where('id', $id)->delete();
        $this->audit('cert.record.delete', (string) $id);

        return redirect()->to(route_to('certifications'))->with('success', 'Record removed.');
    }

    /** Dashboard helper: [expired, expiring] counts. Static so Dashboard can call it. */
    public static function alertCounts($db): array
    {
        $r = $db->query(
            "SELECT
                count(*) FILTER (WHERE expire_on < CURRENT_DATE) AS expired,
                count(*) FILTER (WHERE expire_on >= CURRENT_DATE AND expire_on <= CURRENT_DATE + (COALESCE(before, " . self::DEFAULT_BEFORE . ") || ' days')::interval) AS expiring
               FROM personnel_employeecertification"
        )->getRowArray();

        return ['expired' => (int) ($r['expired'] ?? 0), 'expiring' => (int) ($r['expiring'] ?? 0)];
    }
}

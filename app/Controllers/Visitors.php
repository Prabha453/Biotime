<?php

namespace App\Controllers;

/**
 * Front-desk visitor register. Writes visitor_visitor (check-in) and sets
 * exit_time on check-out. Certificate types / visit reasons are seeded on
 * first use because ZKBioTime ships them empty.
 */
class Visitors extends BaseController
{
    private function ensureCertType(): int
    {
        $db  = db_connect();
        $row = $db->table('personnel_certification')->get(1)->getRowArray();
        if ($row) {
            return (int) $row['id'];
        }
        $db->table('personnel_certification')->insert(['cert_code' => 'ID', 'cert_name' => 'ID Card']);

        return (int) $db->insertID();
    }

    private function ensureReasons(): array
    {
        $db   = db_connect();
        $rows = $db->table('visitor_reason')->orderBy('id')->get()->getResultArray();
        if ($rows) {
            return $rows;
        }
        foreach (['Meeting', 'Interview', 'Delivery', 'Maintenance', 'Other'] as $r) {
            $db->table('visitor_reason')->insert(['visit_reason' => $r, 'status' => 0, 'create_time' => date('Y-m-d H:i:sP')]);
        }

        return $db->table('visitor_reason')->orderBy('id')->get()->getResultArray();
    }

    public function index(): string
    {
        $db = db_connect();
        $sel = "SELECT v.id, v.visitor_code, v.first_name, v.last_name, v.company, v.cert_no,
                       v.mobile, v.visit_quantity, v.start_time, v.end_time, v.exit_time,
                       v.entry_carrying_goods, r.visit_reason,
                       vd.dept_name AS visit_department,
                       e.emp_code AS host_code,
                       NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS host_name, e.first_name AS host_first
                  FROM visitor_visitor v
             LEFT JOIN visitor_reason r ON r.id = v.visit_reason_id
             LEFT JOIN personnel_department vd ON vd.id = v.visit_department_id
             LEFT JOIN personnel_employee e ON e.id = v.visited_id";

        $onsite = $db->query("$sel WHERE v.exit_time IS NULL ORDER BY v.start_time DESC")->getResultArray();

        $q     = trim((string) $this->request->getGet('q'));
        $where = 'WHERE v.exit_time IS NOT NULL';
        if ($q !== '') {
            $like  = $db->escape("%$q%");
            $where .= " AND (v.first_name ILIKE $like OR v.last_name ILIKE $like OR v.company ILIKE $like)";
        }

        $perPage = 25;
        $page    = max(1, (int) ($this->request->getGet('page') ?: 1));
        $total   = (int) $db->query("SELECT count(*) c FROM visitor_visitor v $where")->getRow()->c;
        $offset  = ($page - 1) * $perPage;
        $hb = $db->query("$sel $where ORDER BY v.start_time DESC LIMIT $perPage OFFSET $offset")->getResultArray();

        $pager = \Config\Services::pager();
        $pager->makeLinks($page, $perPage, $total, 'tt');

        $reservations = $db->query(
            "SELECT rs.workflowinstance_ptr_id id, rs.vis_first_name, rs.vis_last_name, rs.company,
                    rs.cert_no, rs.email, rs.visit_quantity, rs.visit_date, rs.apply_reason,
                    w.approval_status, r.visit_reason, vd.dept_name AS visit_department,
                    e.emp_code AS host_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS host_name
               FROM visitor_reservation rs
               JOIN workflow_workflowinstance w ON w.id = rs.workflowinstance_ptr_id
          LEFT JOIN visitor_reason r ON r.id = rs.visit_reason_id
          LEFT JOIN personnel_department vd ON vd.id = rs.visit_department_id
          LEFT JOIN personnel_employee e ON e.id = w.employee_id
              WHERE NOT EXISTS (SELECT 1 FROM visitor_visitor v WHERE v.cert_no = rs.cert_no
                                  AND v.start_time::date = rs.visit_date::date)
           ORDER BY rs.visit_date DESC LIMIT 50"
        )->getResultArray();

        return view('admin/visitors/index', [
            'title'     => 'Visitors',
            'subtitle'  => 'Front-desk check-in / check-out register',
            'active'    => 'visitors',
            'onsite'    => $onsite,
            'history'   => $hb,
            'reservations' => $reservations,
            'pager'     => $pager,
            'q'         => $q,
            'reasons'   => $this->ensureReasons(),
            'departments' => $db->query('SELECT id, dept_name FROM personnel_department ORDER BY dept_name')->getResultArray(),
            'employees' => $db->query(
                "SELECT e.id, e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name, e.first_name
                   FROM personnel_employee e WHERE e.is_active = true ORDER BY e.emp_code_digit ASC"
            )->getResultArray(),
        ]);
    }

    public function register()
    {
        $rules = [
            'first_name'  => 'required|max_length[60]',
            'cert_no'     => 'required|max_length[50]',
            'visited_id'  => 'required|is_natural_no_zero',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'visNew');
        }

        $p     = $this->request;
        $db    = db_connect();
        $hours = max(1, (int) $p->getPost('expected_hours') ?: 8);
        $now   = date('Y-m-d H:i:sP');

        // visitor_visitorconfig: code_prefix/code_width/code_start drive the
        // visitor code. qr_code_policy governs the QR self-pre-registration
        // flow (not implemented here), not this front-desk check-in path —
        // and `status` itself is ZKBioTime's generic valid/deleted flag
        // (0 = valid), not an approval state, so it always stays 0 here.
        $cfg = $db->table('visitor_visitorconfig')->orderBy('id')->get(1)->getRowArray() ?: [];
        $seq = (int) ($cfg['code_start'] ?? 1) + (int) $db->table('visitor_visitor')->countAllResults();
        $code = ($cfg['code_prefix'] ?: 'V') . str_pad((string) $seq, (int) ($cfg['code_width'] ?: 6), '0', STR_PAD_LEFT);

        $ok = $this->dbGuard(fn () => $db->table('visitor_visitor')->insert([
            'visitor_code'         => $code,
            'first_name'           => $p->getPost('first_name'),
            'last_name'            => $p->getPost('last_name') ?: null,
            'cert_no'              => $p->getPost('cert_no'),
            'cert_type_id'         => $this->ensureCertType(),
            'company'              => $p->getPost('company') ?: null,
            'mobile'               => $p->getPost('mobile') ?: null,
            'email'                => $p->getPost('email') ?: null,
            'gender'               => $p->getPost('gender') ?: null,
            'visit_quantity'       => max(1, (int) $p->getPost('visit_quantity') ?: 1),
            'entry_carrying_goods' => $p->getPost('carrying') ?: null,
            'visit_reason_id'      => (int) $p->getPost('visit_reason_id') ?: null,
            'visit_department_id'  => (int) $p->getPost('visit_department_id') ?: null,
            'visited_id'           => (int) $p->getPost('visited_id'),
            'start_time'           => $now,
            'end_time'             => date('Y-m-d H:i:sP', strtotime("+{$hours} hours")),
            'status'               => 0,
            'create_time'          => $now,
        ]));
        if ($ok === null) {
            return redirect()->back()->withInput()->with('error', 'Visitor could not be checked in — check the selected host.');
        }
        $vid = (int) $db->insertID();
        $db->table('visitor_visitorlog')->insert([
            'visitor_id' => $vid, 'visitor_status' => 0, 'status' => 0, 'create_time' => $now,
        ]);
        $this->audit('visitor.checkin', (string) $p->getPost('first_name'), 'id=' . $vid);

        return redirect()->to(route_to('visitors'))->with('success', 'Visitor checked in.');
    }

    public function checkout(int $id)
    {
        $now = date('Y-m-d H:i:sP');
        $ok  = $this->tx(function ($db) use ($id, $now) {
            $db->table('visitor_visitor')->where('id', $id)->update([
                'exit_time'           => $now,
                'exit_carrying_goods' => $this->request->getPost('exit_carrying') ?: null,
            ]);
            $db->table('visitor_visitorlog')->insert([
                'visitor_id' => $id, 'visitor_status' => 1, 'status' => 0, 'create_time' => $now,
            ]);
        });
        if (! $ok) {
            return redirect()->back()->with('error', 'Check-out failed.');
        }
        $this->audit('visitor.checkout', (string) $id);

        return redirect()->to(route_to('visitors'))->with('success', 'Visitor checked out.');
    }

    // ------------------------------------------------------------- reservations

    public function reserve()
    {
        $rules = [
            'vis_first_name' => 'required|max_length[25]',
            'cert_no'        => 'required|max_length[50]',
            'visit_date'     => 'required|valid_date',
            'host_id'        => 'required|is_natural_no_zero',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'resNew');
        }
        $p   = $this->request;
        $now = date('Y-m-d H:i:sP');

        $ok = $this->tx(function ($db) use ($p, $now) {
            $db->table('workflow_workflowinstance')->insert([
                'approval_status' => (int) $p->getPost('auto_approve') === 1 ? 1 : 0,
                'approval_time'   => (int) $p->getPost('auto_approve') === 1 ? $now : null,
                'approver'        => (int) $p->getPost('auto_approve') === 1 ? session()->get('userName') : null,
                'employee_id'     => (int) $p->getPost('host_id'),
            ]);
            $wid = (int) $db->insertID();
            $db->table('visitor_reservation')->insert([
                'workflowinstance_ptr_id' => $wid,
                'vis_first_name'      => $p->getPost('vis_first_name'),
                'vis_last_name'       => $p->getPost('vis_last_name') ?: null,
                'cert_no'             => $p->getPost('cert_no'),
                'cert_type_id'        => $this->ensureCertType(),
                'company'             => $p->getPost('company') ?: null,
                'email'               => $p->getPost('email') ?: null,
                'gender'              => $p->getPost('gender') ?: null,
                'visit_quantity'      => max(1, (int) $p->getPost('visit_quantity') ?: 1),
                'visit_date'          => $p->getPost('visit_date') . ' 09:00:00',
                'apply_time'          => $now,
                'apply_reason'        => $p->getPost('apply_reason') ?: null,
                'visit_reason_id'     => (int) $p->getPost('visit_reason_id') ?: null,
                'visit_department_id' => (int) $p->getPost('visit_department_id') ?: null,
            ]);
        });
        if (! $ok) {
            return redirect()->back()->with('error', 'Reservation could not be saved — check the host you picked.');
        }
        $this->audit('visitor.reserve', (string) $p->getPost('vis_first_name'));

        return redirect()->to(route_to('visitors'))->with('success', 'Reservation recorded.');
    }

    public function reservationDecide(int $id)
    {
        $map = ['approve' => 1, 'reject' => 2];
        $act = (string) $this->request->getPost('action');
        if (! isset($map[$act])) {
            return redirect()->back()->with('error', 'Unknown action.');
        }
        db_connect()->table('workflow_workflowinstance')->where('id', $id)->update([
            'approval_status' => $map[$act],
            'approval_time'   => date('Y-m-d H:i:sP'),
            'approver'        => session()->get('userName'),
        ]);
        $this->audit('visitor.reservation.' . $act, (string) $id);

        return redirect()->to(route_to('visitors'))->with('success', 'Reservation ' . $act . 'd.');
    }

    /** Turn an approved reservation into an on-site check-in. */
    public function convert(int $id)
    {
        $db = db_connect();
        $rs = $db->query(
            'SELECT rs.*, w.employee_id, w.approval_status
               FROM visitor_reservation rs JOIN workflow_workflowinstance w ON w.id = rs.workflowinstance_ptr_id
              WHERE rs.workflowinstance_ptr_id = ?', [$id]
        )->getRowArray();
        if (! $rs) {
            return redirect()->back()->with('error', 'Reservation not found.');
        }
        if ((int) $rs['approval_status'] !== 1) {
            return redirect()->back()->with('error', 'Approve the reservation first.');
        }

        $now = date('Y-m-d H:i:sP');
        $ok  = $this->tx(function ($db) use ($rs, $now) {
            $db->table('visitor_visitor')->insert([
                'visitor_code'        => 'V' . date('ymdHis'),
                'first_name'          => $rs['vis_first_name'],
                'last_name'           => $rs['vis_last_name'],
                'cert_no'             => $rs['cert_no'],
                'cert_type_id'        => $rs['cert_type_id'],
                'company'             => $rs['company'],
                'email'               => $rs['email'],
                'gender'              => $rs['gender'],
                'visit_quantity'      => (int) $rs['visit_quantity'],
                'visit_reason_id'     => $rs['visit_reason_id'],
                'visit_department_id' => $rs['visit_department_id'],
                'visited_id'          => (int) $rs['employee_id'],
                'start_time'          => $now,
                'end_time'            => date('Y-m-d H:i:sP', strtotime('+8 hours')),
                'status'              => 0,
                'create_time'         => $now,
            ]);
            $vid = (int) $db->insertID();
            $db->table('visitor_visitorlog')->insert(['visitor_id' => $vid, 'visitor_status' => 0, 'status' => 0, 'create_time' => $now]);
        });
        if (! $ok) {
            return redirect()->back()->with('error', 'Check-in from reservation failed — check the host still exists.');
        }
        $this->audit('visitor.reservation.convert', (string) $id);

        return redirect()->to(route_to('visitors'))->with('success', 'Reservation checked in.');
    }

    public function reservationDelete(int $id)
    {
        $ok = $this->tx(function ($db) use ($id) {
            $db->table('visitor_reservation')->where('workflowinstance_ptr_id', $id)->delete();
            $db->table('workflow_workflowinstance')->where('id', $id)->delete();
        });
        $this->audit('visitor.reservation.delete', (string) $id);

        return redirect()->to(route_to('visitors'))->with('success', $ok ? 'Reservation deleted.' : 'Delete failed.');
    }

    public function delete(int $id)
    {
        $ok = $this->tx(function ($db) use ($id) {
            $db->table('visitor_visitortransaction')->where('visitor_id', $id)->delete();
            $db->table('visitor_visitorlog')->where('visitor_id', $id)->delete();
            $db->table('visitor_visitor')->where('id', $id)->delete();
        });
        if (! $ok) {
            return redirect()->back()->with('error', 'Delete failed — visitor has linked records.');
        }
        $this->audit('visitor.delete', (string) $id);

        return redirect()->to(route_to('visitors'))->with('success', 'Visitor record deleted.');
    }

    // ------------------------------------------------- settings (reasons + config)

    public function settings(): string
    {
        $db = db_connect();

        return view('admin/visitors/settings', [
            'title'    => 'Visitor Settings',
            'subtitle' => 'Visit reasons and registration policy',
            'active'   => 'visitors',
            'reasons'  => $db->query(
                "SELECT r.*, (SELECT count(*) FROM visitor_visitor v WHERE v.visit_reason_id = r.id) AS uses
                   FROM visitor_reason r ORDER BY r.id"
            )->getResultArray(),
            'config'   => $db->query('SELECT * FROM visitor_visitorconfig ORDER BY id LIMIT 1')->getRowArray() ?: [],
        ]);
    }

    public function saveReason()
    {
        if (! $this->validate(['visit_reason' => 'required|max_length[100]'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }
        $p   = $this->request;
        $rid = $this->saveRow('visitor_reason', ['visit_reason' => trim($p->getPost('visit_reason'))], (int) $p->getPost('id'), [
            'status' => 0, 'create_time' => date('Y-m-d H:i:sP'),
        ]);
        $this->audit('visitor.reason.save', (string) $rid);

        return redirect()->to(route_to('visitor.settings'))->with('success', 'Reason saved.');
    }

    public function deleteReason(int $id)
    {
        $blk = $this->deleteRow('visitor_reason', $id, [
            'Visitors are recorded against this reason' => "SELECT 1 FROM visitor_visitor WHERE visit_reason_id = {$id} LIMIT 1",
        ]);
        if ($blk !== '') {
            return redirect()->back()->with('error', $blk . '.');
        }
        $this->audit('visitor.reason.delete', (string) $id);

        return redirect()->to(route_to('visitor.settings'))->with('success', 'Reason deleted.');
    }

    public function saveConfig()
    {
        $p    = $this->request;
        $data = [
            'code_prefix'      => substr((string) $p->getPost('code_prefix'), 0, 5) ?: 'V',
            'code_width'       => max(4, min(12, (int) $p->getPost('code_width'))),
            'qr_code_policy'   => in_array((int) $p->getPost('qr_code_policy'), [1, 2], true) ? (int) $p->getPost('qr_code_policy') : 1,
            'auto_delete_data' => (int) (bool) $p->getPost('auto_delete_data'),
            'data_retention'   => max(30, (int) $p->getPost('data_retention')),
        ];
        $db  = db_connect();
        $row = $db->table('visitor_visitorconfig')->orderBy('id')->get(1)->getRowArray();
        $ok  = $this->dbGuard(static fn () => $row
            ? $db->table('visitor_visitorconfig')->where('id', $row['id'])->update($data)
            : $db->table('visitor_visitorconfig')->insert($data + ['status' => 0, 'code_start' => 1, 'create_time' => date('Y-m-d H:i:sP')]));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Settings could not be saved.');
        }
        $this->audit('visitor.config.update', 'visitor policy');

        return redirect()->to(route_to('visitor.settings'))->with('success', 'Visitor policy saved.');
    }
}

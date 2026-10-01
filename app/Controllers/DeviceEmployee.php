<?php

namespace App\Controllers;

/**
 * Device ▸ Data ▸ Employee — ZKBioTime's "Device Employee" list: the staff
 * roster as the terminals hold it, with the biometric templates each person
 * has enrolled (fingerprint / face / palm / visible-light palm & face) plus
 * the enrol device, device privilege and verify mode.
 *
 * Toolbar mirrors ZKBio: Area Transfer · Data Transfer · Data Enrollment.
 * Every action queues a PUSH command in iclock_terminalcommand.
 */
class DeviceEmployee extends BaseController
{
    /** iclock.BioData.bio_type → the column it feeds */
    private const BIO_COL = [1 => 'fp', 2 => 'face', 6 => 'palm', 9 => 'vl_face', 10 => 'vl_palm'];

    private const DEV_PRIV = [0 => 'Employee', 2 => 'Registrar', 6 => 'System administrator', 10 => 'User defined', 14 => 'Super administrator'];

    private const VERIFY = [
        -1 => 'Group mode', 0 => 'Any', 1 => 'Fingerprint', 2 => 'User ID', 3 => 'Password', 4 => 'Card',
        15 => 'Face', 25 => 'Palm',
    ];

    public function index(): string
    {
        $db = db_connect();

        $b = $db->table('personnel_employee e')
            ->select("e.id, e.emp_code, e.first_name, e.last_name, e.card_no, e.dev_privilege, e.verify_mode,
                      d.dept_name,
                      to_char(e.update_time,'YYYY-MM-DD HH24:MI') AS updated_at,
                      (SELECT b.major_ver || ':' || b.minor_ver FROM iclock_biodata b WHERE b.employee_id = e.id AND b.bio_type = 1 LIMIT 1) AS fp_ver,
                      (SELECT count(*) FROM iclock_biodata b WHERE b.employee_id = e.id AND b.bio_type = 1) AS fp_n,
                      (SELECT count(*) FROM iclock_biodata b WHERE b.employee_id = e.id AND b.bio_type = 2) AS face_n,
                      (SELECT count(*) FROM iclock_biodata b WHERE b.employee_id = e.id AND b.bio_type = 6) AS palm_n,
                      (SELECT count(*) FROM iclock_biodata b WHERE b.employee_id = e.id AND b.bio_type = 10) AS vl_palm_n,
                      (SELECT count(*) FROM iclock_biodata b WHERE b.employee_id = e.id AND b.bio_type = 9) AS vl_face_n,
                      (SELECT count(*) FROM iclock_biophoto p WHERE p.employee_id = e.id) AS vl_photo_n,
                      (SELECT te.terminal_sn FROM iclock_terminalemployee te WHERE te.emp_code = e.emp_code LIMIT 1) AS enroll_sn")
            ->join('personnel_department d', 'd.id = e.department_id', 'left')
            ->where('e.id NOT IN (SELECT employee_id FROM personnel_resign)', null, false)
            ->orderBy('e.emp_code_digit');

        $fbApplied = $this->filterBuilder($b, [
            'emp_code'     => ['e.emp_code', 'text'],
            'first_name'   => ['e.first_name', 'text'],
            'last_name'    => ['e.last_name', 'text'],
            'dept_name'    => ['d.dept_name', 'text'],
            'card_no'      => ['e.card_no', 'text'],
            'dev_privilege'=> ['e.dev_privilege', 'select'],
            'verify_mode'  => ['e.verify_mode', 'select'],
        ]);

        [$rows, $pager] = $this->paged($b, 30);

        return view('admin/devemployee/index', [
            'title'     => 'Employee',
            'subtitle'  => 'Biometric enrolment held by the devices',
            'active'    => 'dev.data.employee',
            'rows'      => $rows,
            'pager'     => $pager,
            'fbApplied' => $fbApplied,
            'areas'     => $db->query('SELECT id, area_name FROM personnel_area ORDER BY area_name')->getResultArray(),
            'devPriv'   => self::DEV_PRIV,
            'verify'    => self::VERIFY,
        ]);
    }

    /** Edit Employee — the small inline dialog (emp code / name / card / privilege / verify). */
    public function edit(int $id)
    {
        $p = $this->request;
        if (! $this->validate(['first_name' => 'permit_empty|max_length[100]'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'deEmpForm');
        }
        $db  = db_connect();
        $old = $db->table('personnel_employee')->select('card_no, dev_privilege, verify_mode')->where('id', $id)->get()->getRowArray();
        if (! $old) {
            return redirect()->back()->with('error', 'Employee not found.');
        }
        $data = [
            'first_name'    => trim((string) $p->getPost('first_name')) ?: null,
            'last_name'     => trim((string) $p->getPost('last_name')) ?: null,
            'card_no'       => trim((string) $p->getPost('card_no')) ?: null,
            'dev_privilege' => (int) $p->getPost('dev_privilege'),
            'verify_mode'   => (int) $p->getPost('verify_mode'),
            'update_time'   => date('Y-m-d H:i:sP'),
        ];
        $this->dbGuard(static fn () => $db->table('personnel_employee')->where('id', $id)->update($data));
        // any device-relevant change → push the record
        if ((string) $old['card_no'] !== (string) $data['card_no']
            || (int) $old['dev_privilege'] !== $data['dev_privilege']
            || (int) $old['verify_mode'] !== $data['verify_mode']) {
            $this->queueForEmployees($db, [$id], 'UPDATE');
        }
        $this->audit('device.employee.edit', (string) $id);

        return redirect()->to(route_to('devemp'))->with('success', 'Employee updated.');
    }

    /** Area Transfer — move ticked employees to another area + re-push. */
    public function areaTransfer()
    {
        $p    = $this->request;
        $area = (int) $p->getPost('area_id');
        $ids  = $this->ids();
        if (! $area || ! $ids) {
            return redirect()->back()->with('error', 'Pick employees and a target area.');
        }
        $db  = db_connect();
        $now = date('Y-m-d H:i:sP');
        $this->tx(function ($db) use ($ids, $area, $now) {
            $db->table('personnel_employee_area')->whereIn('employee_id', $ids)->delete();
            foreach ($ids as $eid) {
                $db->table('personnel_employee_area')->insert(['employee_id' => $eid, 'area_id' => $area]);
                $db->table('personnel_assignareaemployee')->insert(['employee_id' => $eid, 'area_id' => $area, 'assigned_time' => $now]);
            }
            $this->queueForEmployees($db, $ids, 'UPDATE');
        }, 'The area transfer could not be completed.');
        $this->audit('device.employee.area_transfer', count($ids) . ' employees');

        return redirect()->to(route_to('devemp'))->with('success', 'Transferred ' . count($ids) . ' employee(s).');
    }

    /**
     * Data Transfer / Data Enrollment / Delete Bio Data — one grouped handler.
     *   resync   DATA UPDATE USERINFO   (push the record to the device)
     *   reupload DATA QUERY USERINFO    (pull the record from the device)
     *   enroll_fp / enroll_face / enroll_palm   remote-enrol prompt on the device
     *   clear_bio  wipe server templates + DATA DELETE USERINFO
     */
    public function transfer()
    {
        $p   = $this->request;
        $op  = (string) $p->getPost('op');
        $ids = $this->ids();
        if (! $ids) {
            return redirect()->back()->with('error', 'Select at least one employee.');
        }
        $map = [
            'resync'     => ['UPDATE', 'Re-synced to the device'],
            'reupload'   => ['QUERY', 'Re-uploaded from the device'],
            'enroll_fp'  => ['ENROLL_FP', 'Fingerprint enrolment requested'],
            'enroll_face'=> ['ENROLL_BIOPHOTO', 'Face enrolment requested'],
            'enroll_palm'=> ['ENROLL_PALM', 'Palm enrolment requested'],
            'clear_bio'  => ['DELETE', 'Biometric data cleared'],
        ];
        if (! isset($map[$op])) {
            return redirect()->back()->with('error', 'Unknown action.');
        }
        [$verb, $msg] = $map[$op];

        $db = db_connect();
        $this->tx(function ($db) use ($op, $ids, $verb) {
            if ($op === 'clear_bio') {
                $db->table('iclock_biodata')->whereIn('employee_id', $ids)->delete();
                $db->table('iclock_biophoto')->whereIn('employee_id', $ids)->delete();
            }
            $this->queueForEmployees($db, $ids, $verb);
        }, 'The action could not be completed.');
        $this->audit('device.employee.' . $op, count($ids) . ' employees');

        return redirect()->to(route_to('devemp'))->with('success', $msg . ' for ' . count($ids) . ' employee(s).');
    }

    // --------------------------------------------------------------- helpers

    /** @return list<int> ticked employee ids */
    private function ids(): array
    {
        return array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
    }

    /**
     * Queue "<VERB> USERINFO/FINGERTMP/… PIN=<code>" on every terminal in each
     * employee's area(s). $verb ∈ UPDATE | QUERY | DELETE | ENROLL_FP | ENROLL_PALM | ENROLL_BIOPHOTO
     */
    private function queueForEmployees($db, array $ids, string $verb): void
    {
        $rows = $db->query(
            "SELECT DISTINCT e.emp_code, t.id term_id
               FROM personnel_employee e
               JOIN personnel_employee_area ea ON ea.employee_id = e.id
               JOIN iclock_terminal t          ON t.area_id = ea.area_id
              WHERE e.id IN (" . implode(',', array_map('intval', $ids)) . ')'
        )->getResultArray();
        $now = date('Y-m-d H:i:sP');
        foreach ($rows as $r) {
            $content = match ($verb) {
                'ENROLL_FP'       => 'ENROLL_FP PIN=' . $r['emp_code'] . "\tFID=0\tRETRY=3\tOVERWRITE=1",
                'ENROLL_PALM'     => 'ENROLL_PALM PIN=' . $r['emp_code'] . "\tRETRY=3\tOVERWRITE=1",
                'ENROLL_BIOPHOTO' => 'ENROLL_BIOPHOTO PIN=' . $r['emp_code'] . "\tType=9\tRETRY=3",
                'DELETE'          => 'DATA DELETE USERINFO PIN=' . $r['emp_code'],
                'QUERY'           => 'DATA QUERY USERINFO PIN=' . $r['emp_code'],
                default           => 'DATA UPDATE USERINFO PIN=' . $r['emp_code'],
            };
            $db->table('iclock_terminalcommand')->insert([
                'content' => $content, 'commit_time' => $now, 'terminal_id' => (int) $r['term_id'],
            ]);
        }
    }
}

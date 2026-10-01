<?php

namespace App\Controllers;

/**
 * Mobile app support: GPS geofences (mobile_gpslocation +
 * mobile_gpsforemployee / mobile_gpsfordepartment), announcements
 * (mobile_announcement), the notification feed (mobile_appnotification)
 * and web-punch approvals (att_webpunch → iclock_transaction).
 */
class Mobile extends BaseController
{
    private const TABS = [
        'locations'     => 'Geofences',
        'assignments'   => 'Assignments',
        'announcements' => 'Announcements',
        'notifications' => 'Notifications',
        'webpunch'      => 'Web Punches',
    ];

    public function index(string $tab = 'locations'): string
    {
        if (! isset(self::TABS[$tab])) {
            $tab = 'locations';
        }
        $db = db_connect();
        $d  = [
            'title'    => 'Mobile',
            'subtitle' => self::TABS[$tab],
            'active'   => 'mobile',
            'tab'      => $tab,
            'tabs'     => self::TABS,
            'employees'   => $db->query("SELECT id, emp_code, NULLIF(TRIM(CONCAT(first_name,' ',last_name)),'') name FROM personnel_employee WHERE is_active = true ORDER BY emp_code_digit")->getResultArray(),
            'departments' => $db->query('SELECT id, dept_name FROM personnel_department ORDER BY dept_name')->getResultArray(),
            'locations'   => $db->query('SELECT id, alias, location, longitude, latitude FROM mobile_gpslocation WHERE status = 0 ORDER BY alias')->getResultArray(),
        ];

        switch ($tab) {
            case 'assignments':
                $d['empAssign'] = $db->query(
                    "SELECT g.id, g.distance, g.start_date, g.end_date, e.emp_code,
                            NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') name,
                            string_agg(l.alias, ', ') AS locs
                       FROM mobile_gpsforemployee g
                       JOIN personnel_employee e ON e.id = g.employee_id
                  LEFT JOIN mobile_gpsforemployee_location gl ON gl.gpsforemployee_id = g.id
                  LEFT JOIN mobile_gpslocation l ON l.id = gl.gpslocation_id
                   GROUP BY g.id, e.emp_code, e.emp_code_digit, e.first_name, e.last_name
                   ORDER BY e.emp_code_digit"
                )->getResultArray();
                $d['deptAssign'] = $db->query(
                    "SELECT g.id, g.distance, g.start_date, g.end_date, dep.dept_name,
                            string_agg(l.alias, ', ') AS locs
                       FROM mobile_gpsfordepartment g
                       JOIN personnel_department dep ON dep.id = g.department_id
                  LEFT JOIN mobile_gpsfordepartment_location gl ON gl.gpsfordepartment_id = g.id
                  LEFT JOIN mobile_gpslocation l ON l.id = gl.gpslocation_id
                   GROUP BY g.id, dep.dept_name ORDER BY dep.dept_name"
                )->getResultArray();
                break;

            case 'announcements':
                [$d['rows'], $d['pager']] = $this->paged(
                    $db->table('mobile_announcement a')
                        ->select("a.id, a.subject, a.content, a.category, a.create_time,
                                  NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS receiver, e.emp_code")
                        ->join('personnel_employee e', 'e.id = a.receiver_id', 'left')
                        ->orderBy('a.id', 'DESC'),
                    20
                );
                break;

            case 'notifications':
                [$d['rows'], $d['pager']] = $this->paged(
                    $db->table('mobile_appnotification n')
                        ->select("n.id, n.category, n.content, n.notification_time, n.read_status,
                                  NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS receiver, e.emp_code")
                        ->join('personnel_employee e', 'e.id = n.receiver_id', 'left')
                        ->orderBy('n.id', 'DESC'),
                    30
                );
                break;

            case 'webpunch':
                [$d['rows'], $d['pager']] = $this->paged(
                    $db->table('att_webpunch x')
                        ->select("x.workflowinstance_ptr_id id,
                                  to_char(x.punch_time,'YYYY-MM-DD HH24:MI') punch_time, x.punch_state,
                                  x.apply_reason, w.approval_status,
                                  e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') name")
                        ->join('workflow_workflowinstance w', 'w.id = x.workflowinstance_ptr_id')
                        ->join('personnel_employee e', 'e.id = w.employee_id', 'left')
                        ->orderBy('x.workflowinstance_ptr_id', 'DESC'),
                    20
                );
                break;
        }

        return view('admin/mobile/index', $d);
    }

    // ---- geofences ----

    public function saveLocation()
    {
        $rules = ['alias' => 'required|max_length[100]', 'latitude' => 'required|decimal', 'longitude' => 'required|decimal'];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'locNew');
        }
        $p  = $this->request;
        $id = (int) $p->getPost('id');
        $data = [
            'alias'     => $p->getPost('alias'),
            'location'  => $p->getPost('location') ?: $p->getPost('alias'),
            'latitude'  => (float) $p->getPost('latitude'),
            'longitude' => (float) $p->getPost('longitude'),
            'status'    => 0,
        ];
        $ok = $this->dbGuard(fn () => $id
            ? db_connect()->table('mobile_gpslocation')->where('id', $id)->update($data)
            : db_connect()->table('mobile_gpslocation')->insert($data + ['create_time' => date('Y-m-d H:i:sP')]));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Location could not be saved.');
        }
        $this->audit($id ? 'mobile.location.update' : 'mobile.location.create', $data['alias']);

        return redirect()->to(route_to('mobile.tab', 'locations'))->with('success', 'Geofence saved.');
    }

    public function deleteLocation(int $id)
    {
        $ok = $this->tx(function ($db) use ($id) {
            $db->table('mobile_gpsforemployee_location')->where('gpslocation_id', $id)->delete();
            $db->table('mobile_gpsfordepartment_location')->where('gpslocation_id', $id)->delete();
            $db->table('mobile_gpslocation')->where('id', $id)->delete();
        });
        $this->audit('mobile.location.delete', (string) $id);

        return redirect()->to(route_to('mobile.tab', 'locations'))->with('success', $ok ? 'Geofence deleted.' : 'Delete failed.');
    }

    public function assign()
    {
        $p     = $this->request;
        $kind  = $p->getPost('kind') === 'department' ? 'department' : 'employee';
        $tgt   = (int) $p->getPost('target_id');
        $locs  = array_values(array_unique(array_map('intval', (array) $p->getPost('location_ids'))));
        $dist  = max(10, (int) $p->getPost('distance') ?: 100);
        if (! $tgt || $locs === []) {
            return redirect()->back()->with('error', 'Pick a target and at least one geofence.')->with('reopen', 'asgNew');
        }

        $ok = $this->tx(function ($db) use ($kind, $tgt, $locs, $dist, $p) {
            $parent = $kind === 'department' ? 'mobile_gpsfordepartment' : 'mobile_gpsforemployee';
            $link   = $parent . '_location';
            $fk     = $kind === 'department' ? 'department_id' : 'employee_id';
            $linkFk = $kind === 'department' ? 'gpsfordepartment_id' : 'gpsforemployee_id';

            $db->table($parent)->insert([
                'distance'   => $dist,
                'start_date' => $p->getPost('start_date') ?: date('Y-m-d'),
                'end_date'   => $p->getPost('end_date') ?: '2999-12-31',
                'status'     => 0,
                $fk          => $tgt,
                'create_time'=> date('Y-m-d H:i:sP'),
            ]);
            $pid = (int) $db->insertID();
            foreach ($locs as $lid) {
                $db->table($link)->insert([$linkFk => $pid, 'gpslocation_id' => $lid]);
            }
        });
        if (! $ok) {
            return redirect()->back()->with('error', 'Assignment could not be saved.');
        }
        $this->audit('mobile.geofence.assign', $kind . '=' . $tgt);

        return redirect()->to(route_to('mobile.tab', 'assignments'))->with('success', 'Geofence assigned.');
    }

    public function deleteAssignment(string $kind, int $id)
    {
        $parent = $kind === 'department' ? 'mobile_gpsfordepartment' : 'mobile_gpsforemployee';
        $link   = $parent . '_location';
        $linkFk = $kind === 'department' ? 'gpsfordepartment_id' : 'gpsforemployee_id';
        $this->tx(function ($db) use ($parent, $link, $linkFk, $id) {
            $db->table($link)->where($linkFk, $id)->delete();
            $db->table($parent)->where('id', $id)->delete();
        });
        $this->audit('mobile.geofence.unassign', $kind . '=' . $id);

        return redirect()->to(route_to('mobile.tab', 'assignments'))->with('success', 'Assignment removed.');
    }

    // ---- announcements ----

    public function announce()
    {
        if (! $this->validate(['subject' => 'required|max_length[100]', 'content' => 'required'])) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'annNew');
        }
        $p        = $this->request;
        $receiver = (int) $p->getPost('receiver_id') ?: null;
        $adminId  = (int) (db_connect()->query('SELECT id FROM auth_user ORDER BY id LIMIT 1')->getRow()->id ?? 1);

        $ok = $this->dbGuard(fn () => db_connect()->table('mobile_announcement')->insert([
            'subject'     => $p->getPost('subject'),
            'content'     => $p->getPost('content'),
            'category'    => (int) $p->getPost('category') ?: 1,
            'sender'      => session()->get('userName'),
            'create_time' => date('Y-m-d H:i:sP'),
            'admin_id'    => $adminId,
            'receiver_id' => $receiver,
        ]));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Announcement could not be posted.');
        }
        $this->audit('mobile.announce', $p->getPost('subject'), $receiver ? 'to emp ' . $receiver : 'broadcast');

        return redirect()->to(route_to('mobile.tab', 'announcements'))->with('success', 'Announcement posted.');
    }

    public function deleteAnnouncement(int $id)
    {
        db_connect()->table('mobile_announcement')->where('id', $id)->delete();
        $this->audit('mobile.announce.delete', (string) $id);

        return redirect()->to(route_to('mobile.tab', 'announcements'))->with('success', 'Announcement removed.');
    }

    // ---- web punches ----

    public function decideWebpunch(int $id)
    {
        $map = ['approve' => 1, 'reject' => 2];
        $act = (string) $this->request->getPost('action');
        if (! isset($map[$act])) {
            return redirect()->back()->with('error', 'Unknown action.');
        }
        $db  = db_connect();
        $cur = $db->query('SELECT approval_status FROM workflow_workflowinstance w WHERE id = ? AND EXISTS (SELECT 1 FROM att_webpunch x WHERE x.workflowinstance_ptr_id = w.id)', [$id])->getRowArray();
        if (! $cur) {
            return redirect()->back()->with('error', 'Web punch not found.');
        }
        if ((int) $cur['approval_status'] !== 0) {
            return redirect()->back()->with('error', 'This web punch has already been decided.');
        }

        $ok = $this->tx(function ($db) use ($id, $act, $map) {
            $db->table('workflow_workflowinstance')->where('id', $id)->update([
                'approval_status' => $map[$act],
                'approval_time'   => date('Y-m-d H:i:sP'),
                'approver'        => session()->get('userName'),
            ]);
            if ($act !== 'approve') {
                return;
            }
            $wp = $db->query(
                "SELECT wp.punch_time, wp.punch_state, w.employee_id,
                        e.emp_code, c.company_code
                   FROM att_webpunch wp
                   JOIN workflow_workflowinstance w ON w.id = wp.workflowinstance_ptr_id
                   JOIN personnel_employee e ON e.id = w.employee_id
              LEFT JOIN personnel_company c ON c.id = e.company_id
                  WHERE wp.workflowinstance_ptr_id = ?", [$id]
            )->getRowArray();
            if ($wp) {
                $db->table('iclock_transaction')->insert([
                    'company_code'  => $wp['company_code'],
                    'emp_code'      => $wp['emp_code'],
                    'emp_id'        => (int) $wp['employee_id'],
                    'punch_time'    => $wp['punch_time'],
                    'punch_state'   => (string) (int) $wp['punch_state'],
                    'verify_type'   => 200,
                    'source'        => 5, // manual/web correction
                    'is_attendance' => 1,
                    'upload_time'   => date('Y-m-d H:i:sP'),
                    'reserved'      => 'webpunch:' . session()->get('userName'),
                ]);
            }
        });
        if (! $ok) {
            return redirect()->back()->with('error', 'Could not process the web punch (a punch at that time may already exist).');
        }
        $this->audit('mobile.webpunch.' . $act, (string) $id);

        return redirect()->to(route_to('mobile.tab', 'webpunch'))->with('success', 'Web punch ' . $act . 'd.');
    }
}

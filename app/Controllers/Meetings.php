<?php

namespace App\Controllers;

use App\Libraries\MeetingCalculator;

/**
 * Meeting bookings — meeting_meetingentity (+ _attender) created through a
 * lightweight workflow_workflowinstance row. Attendance per attendee is
 * derived from meeting_meetingtransaction by MeetingCalculator.
 */
class Meetings extends BaseController
{
    /** ZK NOT-NULL columns that this lightweight path just needs sane values for. */
    private const DEFAULTS = [
        'in_required' => true, 'out_required' => true, 'enable_virtual' => false,
        'time_zone' => 4, 'host_video' => true, 'participant_video' => true,
        'enable_waiting_room' => false, 'jbh_anytime' => false, 'mute_upon_entry' => false,
        'auto_recording' => 'none',
    ];

    public function index(): string
    {
        $db   = db_connect();
        $b    = $db->table('meeting_meetingentity m')
            ->select("m.workflowinstance_ptr_id id, m.code, m.alias, m.content, m.meeting_date,
                      to_char(m.start_time,'HH24:MI') st, to_char(m.end_time,'HH24:MI') et,
                      m.duration, m.calculation_time,
                      r.alias AS room, w.approval_status,
                      (SELECT count(*) FROM meeting_meetingentity_attender a WHERE a.meetingentity_id = m.workflowinstance_ptr_id) attendees")
            ->join('workflow_workflowinstance w', 'w.id = m.workflowinstance_ptr_id')
            ->join('meeting_meetingroom r', 'r.id = m.room_id', 'left')
            ->orderBy('m.meeting_date', 'DESC')
            ->orderBy('m.start_time', 'DESC');
        [$rows, $pager] = $this->paged($b, 20);

        return view('admin/meetings/bookings', [
            'title'     => 'Meetings',
            'subtitle'  => 'Room bookings and attendee attendance',
            'active'    => 'meetings',
            'nav'       => 'bookings',
            'rows'      => $rows,
            'pager'     => $pager,
            'rooms'     => $db->query('SELECT id, alias, capacity FROM meeting_meetingroom WHERE enable_room = true ORDER BY alias')->getResultArray(),
            'employees' => $db->query("SELECT id, emp_code, NULLIF(TRIM(CONCAT(first_name,' ',last_name)),'') name FROM personnel_employee WHERE is_active = true ORDER BY emp_code_digit")->getResultArray(),
        ]);
    }

    public function view(int $id): string
    {
        $db = db_connect();
        $m  = $db->query(
            "SELECT m.*, r.alias AS room FROM meeting_meetingentity m
        LEFT JOIN meeting_meetingroom r ON r.id = m.room_id
            WHERE m.workflowinstance_ptr_id = ?", [$id]
        )->getRowArray();
        if (! $m) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('admin/meetings/booking_view', [
            'title'      => $m['alias'],
            'subtitle'   => $m['meeting_date'],
            'active'     => 'meetings',
            'm'          => $m,
            'attendance' => (new MeetingCalculator($db))->attendance($id),
            'attenders'  => $db->query(
                "SELECT e.id, e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') name
                   FROM meeting_meetingentity_attender a JOIN personnel_employee e ON e.id = a.employee_id
                  WHERE a.meetingentity_id = ? ORDER BY e.emp_code_digit", [$id]
            )->getResultArray(),
        ]);
    }

    /** Record a manual in/out punch for one attendee, then recalculate. */
    public function manualLog(int $id)
    {
        $p     = $this->request;
        $empId = (int) $p->getPost('employee_id');
        $when  = $p->getPost('punch_time');
        $state = $p->getPost('punch_state') === '1' ? '1' : '0';
        if ($empId <= 0 || ! $when) {
            return redirect()->back()->with('error', 'Pick an attendee and a time.');
        }
        $ok = $this->tx(static function ($db) use ($id, $empId, $when, $state, $p): void {
            $emp = $db->table('personnel_employee')->select('emp_code')->where('id', $empId)->get()->getRowArray();
            $ts  = date('Y-m-d H:i:sP', strtotime($when));
            $db->table('meeting_meetingtransaction')->insert([
                'emp_code'       => $emp['emp_code'] ?? (string) $empId,
                'punch_datetime' => $ts,
                'punch_date'     => date('Y-m-d', strtotime($when)),
                'punch_time'     => date('H:i:s', strtotime($when)),
                'punch_state'    => $state,
                'source'         => 5,
                'upload_time'    => date('Y-m-d H:i:sP'),
                'emp_id'         => $empId,
                'meeting_id'     => $id,
            ]);
            // paper trail through the workflow so it shows as an approved correction
            $db->table('workflow_workflowinstance')->insert(['approval_status' => 1, 'approval_time' => date('Y-m-d H:i:sP'), 'approver' => session()->get('userName'), 'employee_id' => $empId]);
            $wid = (int) $db->insertID();
            $db->table('meeting_meetingmanuallog')->insert([
                'workflowinstance_ptr_id' => $wid,
                'punch_time'  => $ts,
                'punch_state' => $state,
                'apply_reason' => substr((string) $p->getPost('reason'), 0, 200) ?: 'Manual meeting punch',
                'apply_time'  => date('Y-m-d H:i:sP'),
                'meeting_id'  => $id,
            ]);
        });
        if (! $ok) {
            return redirect()->back()->with('error', 'Manual punch could not be recorded.');
        }
        $this->dbGuard(fn () => (new MeetingCalculator())->calculate($id));
        $this->audit('meeting.manuallog', (string) $id, 'emp ' . $empId);

        return redirect()->back()->with('success', 'Manual punch recorded and attendance recalculated.');
    }

    public function save()
    {
        $rules = [
            'alias'        => 'required|max_length[50]',
            'meeting_date' => 'required|valid_date',
            'start'        => 'required',
            'end'          => 'required',
            'room_id'      => 'permit_empty|is_natural',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->with('errors', $this->validator->getErrors())
                ->with('reopen', (int) $this->request->getPost('id') ? 'mtgEdit' . (int) $this->request->getPost('id') : 'mtgNew');
        }

        $p     = $this->request;
        $id    = (int) $p->getPost('id');
        $date  = $p->getPost('meeting_date');
        $start = $date . ' ' . $p->getPost('start') . ':00';
        $end   = $date . ' ' . $p->getPost('end') . ':00';
        if (strtotime($end) <= strtotime($start)) {
            return redirect()->back()->with('error', 'End time must be after start time.');
        }
        $dur     = (int) round((strtotime($end) - strtotime($start)) / 60);
        $attIds  = array_values(array_unique(array_map('intval', (array) $p->getPost('attendees'))));

        $ok = $this->tx(function ($db) use ($id, $p, $date, $start, $end, $dur, $attIds) {
            $data = self::DEFAULTS + [
                'alias'        => $p->getPost('alias'),
                'content'      => $p->getPost('content') ?: null,
                'meeting_date' => $date,
                'start_time'   => $start,
                'end_time'     => $end,
                'duration'     => $dur,
                'in_start'     => date('Y-m-d H:i:sP', strtotime($start . ' -30 minutes')),
                'in_end'       => $end,
                'out_start'    => $start,
                'out_end'      => date('Y-m-d H:i:sP', strtotime($end . ' +30 minutes')),
                'apply_reason' => $p->getPost('content') ?: $p->getPost('alias'),
                'apply_time'   => date('Y-m-d H:i:sP'),
                'view_date'    => $date,
                'room_id'      => (int) $p->getPost('room_id') ?: null,
            ];

            if ($id) {
                $db->table('meeting_meetingentity')->where('workflowinstance_ptr_id', $id)->update($data);
                $mid = $id;
            } else {
                $db->table('workflow_workflowinstance')->insert([
                    'approval_status' => 1,
                    'approval_time'   => date('Y-m-d H:i:sP'),
                    'approver'        => session()->get('userName'),
                    'employee_id'     => $attIds[0] ?? (int) $db->query('SELECT id FROM personnel_employee ORDER BY id LIMIT 1')->getRow()->id,
                ]);
                $mid = (int) $db->insertID();
                $data['workflowinstance_ptr_id'] = $mid;
                $data['code'] = 'MT' . strtoupper(substr(md5(uniqid('', true)), 0, 8));
                $db->table('meeting_meetingentity')->insert($data);
            }

            $db->table('meeting_meetingentity_attender')->where('meetingentity_id', $mid)->delete();
            foreach ($attIds as $eid) {
                $db->table('meeting_meetingentity_attender')->insert(['meetingentity_id' => $mid, 'employee_id' => $eid]);
            }
        });

        if (! $ok) {
            return redirect()->back()->with('error', 'Booking could not be saved — check the room and attendees.');
        }
        $this->audit($id ? 'meeting.booking.update' : 'meeting.booking.create', (string) $p->getPost('alias'));

        return redirect()->to(route_to('meetings.bookings'))->with('success', $id ? 'Booking updated.' : 'Booking created.');
    }

    public function calculate(int $id)
    {
        $n = $this->dbGuard(fn () => (new MeetingCalculator())->calculate($id));
        $this->audit('meeting.booking.calc', (string) $id, ($n ?? 0) . ' attendees');

        return redirect()->back()->with('success', 'Attendance recalculated for ' . ($n ?? 0) . ' attendee(s).');
    }

    public function delete(int $id)
    {
        $ok = $this->tx(function ($db) use ($id) {
            $db->table('meeting_meetingpayloadbase')->where('meeting_id', $id)->delete();
            $db->table('meeting_meetingtransaction')->where('meeting_id', $id)->delete();
            $db->table('meeting_meetingentity_attender')->where('meetingentity_id', $id)->delete();
            $db->table('meeting_meetingentity')->where('workflowinstance_ptr_id', $id)->delete();
            $db->table('workflow_workflowinstance')->where('id', $id)->delete();
        });
        $this->audit('meeting.booking.delete', (string) $id);

        return redirect()->to(route_to('meetings.bookings'))->with('success', $ok ? 'Booking deleted.' : 'Delete failed.');
    }
}

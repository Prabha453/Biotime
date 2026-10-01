<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;

/**
 * Per-attendee attendance for a meeting booking, derived from
 * meeting_meetingtransaction punches at the room's terminal.
 *
 *   first punch after in_start  → check-in  (late if after start_time)
 *   last punch before out_end   → check-out (early if before end_time)
 *   no punches                  → absent
 *
 * Results are persisted to meeting_meetingpayloadbase.
 */
class MeetingCalculator
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @return array<int,array> one row per attendee */
    public function attendance(int $meetingId): array
    {
        $m = $this->db->table('meeting_meetingentity')->where('workflowinstance_ptr_id', $meetingId)->get()->getRowArray();
        if (! $m) {
            return [];
        }

        $attenders = $this->db->query(
            "SELECT a.employee_id, e.emp_code,
                    NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name
               FROM meeting_meetingentity_attender a
               JOIN personnel_employee e ON e.id = a.employee_id
              WHERE a.meetingentity_id = ?
           ORDER BY e.emp_code_digit",
            [$meetingId]
        )->getResultArray();

        $punches = [];
        foreach ($this->db->query(
            "SELECT emp_id, to_char(punch_datetime,'YYYY-MM-DD HH24:MI:SS') t
               FROM meeting_meetingtransaction
              WHERE meeting_id = ? ORDER BY punch_datetime",
            [$meetingId]
        )->getResultArray() as $p) {
            $punches[(int) $p['emp_id']][] = $p['t'];
        }

        $start = strtotime($m['start_time']);
        $end   = strtotime($m['end_time']);

        $out = [];
        foreach ($attenders as $a) {
            $mine = $punches[(int) $a['employee_id']] ?? [];
            $row  = ['emp_code' => $a['emp_code'], 'name' => $a['name'], 'employee_id' => (int) $a['employee_id']];
            if ($mine === []) {
                $row += ['clock_in' => null, 'clock_out' => null, 'late_in' => 0, 'early_out' => 0, 'status' => 'Absent'];
            } else {
                $in       = strtotime($mine[0]);
                $lastOut  = strtotime(end($mine));
                $lateIn   = max(0, (int) round(($in - $start) / 60));
                $earlyOut = max(0, (int) round(($end - $lastOut) / 60));
                $row += [
                    'clock_in'  => substr($mine[0], 11, 5),
                    'clock_out' => count($mine) > 1 ? substr(end($mine), 11, 5) : null,
                    'late_in'   => $lateIn,
                    'early_out' => $earlyOut,
                    'status'    => $lateIn > 0 || $earlyOut > 0 ? 'Partial' : 'Attended',
                ];
            }
            $out[] = $row;
        }

        return $out;
    }

    /** Recompute + persist meeting_meetingpayloadbase for a booking. */
    public function calculate(int $meetingId): int
    {
        $m = $this->db->table('meeting_meetingentity')->where('workflowinstance_ptr_id', $meetingId)->get()->getRowArray();
        if (! $m) {
            return 0;
        }
        $rows = $this->attendance($meetingId);

        $this->db->transStart();
        $this->db->table('meeting_meetingpayloadbase')->where('meeting_id', $meetingId)->delete();
        foreach ($rows as $r) {
            $this->db->table('meeting_meetingpayloadbase')->insert([
                'id'               => $this->uuid(),
                'start_time'       => $m['start_time'],
                'end_time'         => $m['end_time'],
                'duration'         => (int) $m['duration'],
                'meeting_date'     => $m['meeting_date'],
                'clock_in'         => $r['clock_in'] ? $m['meeting_date'] . ' ' . $r['clock_in'] . ':00' : null,
                'clock_out'        => $r['clock_out'] ? $m['meeting_date'] . ' ' . $r['clock_out'] . ':00' : null,
                'attended_duration'=> $r['status'] === 'Absent' ? 0 : max(0, (int) $m['duration'] - $r['late_in'] - $r['early_out']),
                'late_in'          => $r['late_in'],
                'early_out'        => $r['early_out'],
                'absent'           => $r['status'] === 'Absent' ? (int) $m['duration'] : 0,
                'emp_id'           => $r['employee_id'],
                'meeting_id'       => $meetingId,
            ]);
        }
        $this->db->table('meeting_meetingentity')->where('workflowinstance_ptr_id', $meetingId)
            ->update(['calculation_time' => date('Y-m-d H:i:sP')]);
        $this->db->transComplete();

        return count($rows);
    }

    private function uuid(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}

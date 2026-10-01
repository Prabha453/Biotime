<?php

namespace App\Controllers;

/**
 * Meeting-room directory (meeting_meetingroom) + which terminal covers
 * each room (meeting_meetingroomdevice). Booking workflow lives in the
 * ZKBioTime workflow module and is out of scope here.
 */
class MeetingRooms extends BaseController
{
    public function index(): string
    {
        $db = db_connect();
        $rows = $db->query(
            "SELECT r.id, r.code, r.alias, r.capacity, r.location, r.remark, r.enable_room, r.state,
                    d.device_id, term.alias AS device_name
               FROM meeting_meetingroom r
          LEFT JOIN meeting_meetingroomdevice d ON d.room_id = r.id
          LEFT JOIN iclock_terminal term ON term.id = d.device_id
           ORDER BY r.alias"
        )->getResultArray();

        return view('admin/meetings/index', [
            'title'    => 'Meeting Rooms',
            'subtitle' => 'Bookable rooms and their check-in terminal',
            'active'   => 'meetings',
            'rows'     => $rows,
            'devices'  => $db->query('SELECT id, alias FROM iclock_terminal ORDER BY alias')->getResultArray(),
        ]);
    }

    public function save()
    {
        $rules = [
            'alias'    => 'required|max_length[60]',
            'capacity' => 'required|is_natural_no_zero',
            'location' => 'permit_empty|max_length[120]',
        ];
        if (! $this->validate($rules)) {
            $id = (int) $this->request->getPost('id');
            return redirect()->back()->with('errors', $this->validator->getErrors())
                ->with('reopen', $id ? 'roomEdit' . $id : 'roomNew');
        }

        $p  = $this->request;
        $db = db_connect();
        $id = (int) $p->getPost('id');
        $data = [
            'alias'       => $p->getPost('alias'),
            'capacity'    => (int) $p->getPost('capacity'),
            'location'    => (string) $p->getPost('location'),
            'remark'      => $p->getPost('remark') ?: null,
            'enable_room' => (bool) $p->getPost('enable_room'),
            'state'       => 0,
            'status'      => 0,
        ];
        $deviceId = (int) $p->getPost('device_id');

        $ok = $this->dbGuard(function () use ($db, $id, $data, $deviceId) {
            $db->transStart();
            if ($id) {
                $db->table('meeting_meetingroom')->where('id', $id)->update($data);
                $roomId = $id;
            } else {
                $data['code']        = 'RM' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
                $data['create_time'] = date('Y-m-d H:i:sP');
                $db->table('meeting_meetingroom')->insert($data);
                $roomId = (int) $db->insertID();
            }
            $db->table('meeting_meetingroomdevice')->where('room_id', $roomId)->delete();
            if ($deviceId) {
                $db->table('meeting_meetingroomdevice')->insert(['room_id' => $roomId, 'device_id' => $deviceId]);
            }
            $db->transComplete();

            return $db->transStatus();
        });

        if (! $ok) {
            return redirect()->back()->with('error', 'Room could not be saved — check the selected terminal still exists.');
        }
        $this->audit($id ? 'meeting.room.update' : 'meeting.room.create', $data['alias']);

        return redirect()->to(route_to('meetings'))->with('success', $id ? 'Room updated.' : 'Room added.');
    }

    public function delete(int $id)
    {
        $ok = $this->tx(function ($db) use ($id) {
            $db->table('meeting_meetingroomdevice')->where('room_id', $id)->delete();
            $db->table('meeting_meetingroom')->where('id', $id)->delete();
        });
        if (! $ok) {
            return redirect()->back()->with('error', 'Delete failed — the room has bookings or devices linked.');
        }
        $this->audit('meeting.room.delete', (string) $id);

        return redirect()->to(route_to('meetings'))->with('success', 'Room deleted.');
    }
}

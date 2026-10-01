<?php

namespace App\Controllers;

/**
 * Device ▸ Message — ZKBioTime's screen messages, split the ZKBio way:
 *
 *   public   iclock_publicmessage    shown on a terminal's screen (per device)
 *   private  iclock_privatemessage   shown to one employee after they punch
 *
 * Both point at an iclock_shortmessage row (text + schedule). Sending a
 * message writes the shortmessage once, then a public/private row per target.
 */
class DeviceMessages extends BaseController
{
    private const TABS = ['public' => 'Public Message', 'private' => 'Private Message'];

    public function index(string $tab = 'public'): string
    {
        if (! isset(self::TABS[$tab])) {
            $tab = 'public';
        }
        $db = db_connect();

        if ($tab === 'private') {
            $b = $db->table('iclock_privatemessage v')
                ->select("v.id, v.message_id, m.message, m.duration, e.emp_code,
                          NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS target,
                          to_char(m.start_time,'YYYY-MM-DD HH24:MI') AS start_at,
                          to_char(v.last_send,'YYYY-MM-DD HH24:MI') AS sent_at")
                ->join('iclock_shortmessage m', 'm.id = v.message_id', 'left')
                ->join('personnel_employee e', 'e.id = v.employee_id', 'left')
                ->orderBy('m.start_time', 'DESC');
            $spec = ['emp_code' => ['e.emp_code', 'text'], 'target' => ["TRIM(CONCAT(e.first_name,' ',e.last_name))", 'text'], 'message' => ['m.message', 'text'], 'start_time' => ['m.start_time', 'date']];
        } else {
            $b = $db->table('iclock_publicmessage p')
                ->select("p.id, p.message_id, m.message, m.duration, t.alias AS target,
                          to_char(m.start_time,'YYYY-MM-DD HH24:MI') AS start_at,
                          to_char(p.last_send,'YYYY-MM-DD HH24:MI') AS sent_at")
                ->join('iclock_shortmessage m', 'm.id = p.message_id', 'left')
                ->join('iclock_terminal t', 't.id = p.terminal_id', 'left')
                ->orderBy('m.start_time', 'DESC');
            $spec = ['target' => ['t.alias', 'text'], 'message' => ['m.message', 'text'], 'start_time' => ['m.start_time', 'date']];
        }

        $fbApplied = $this->filterBuilder($b, $spec);
        [$rows, $pager] = $this->paged($b, 30);

        return view('admin/devmessages/index', [
            'title'     => self::TABS[$tab],
            'subtitle'  => 'Messages pushed to the terminal screens',
            'active'    => 'dev.msg.' . $tab,
            'tab'       => $tab,
            'tabs'      => self::TABS,
            'rows'      => $rows,
            'pager'     => $pager,
            'fbApplied' => $fbApplied,
            'devices'   => $db->query('SELECT id, alias FROM iclock_terminal ORDER BY alias')->getResultArray(),
            'employees' => $db->query("SELECT id, emp_code, NULLIF(TRIM(CONCAT(first_name,' ',last_name)),'') name FROM personnel_employee WHERE is_active = true ORDER BY emp_code_digit")->getResultArray(),
        ]);
    }

    public function send(string $tab)
    {
        if (! isset(self::TABS[$tab])) {
            return redirect()->back()->with('error', 'Unknown message type.');
        }
        $p = $this->request;
        if (! $this->validate([
            'message'  => 'required|max_length[320]',
            'duration' => 'required|is_natural_no_zero',
            'targets'  => 'required',
        ])) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'msgForm');
        }

        $targets = array_values(array_filter(array_map('intval', (array) $p->getPost('targets'))));
        $start   = $p->getPost('start_time') ?: date('Y-m-d H:i:s');
        $now     = date('Y-m-d H:i:sP');

        $ok = $this->tx(function ($db) use ($tab, $p, $targets, $start, $now) {
            $db->table('iclock_shortmessage')->insert([
                'message'    => trim((string) $p->getPost('message')),
                'start_time' => $start,
                'duration'   => (int) $p->getPost('duration'),
                'msg_type'   => $tab === 'private' ? '254' : '253',
            ]);
            $mid = (int) $db->insertID();
            foreach ($targets as $tid) {
                $db->table($tab === 'private' ? 'iclock_privatemessage' : 'iclock_publicmessage')->insert([
                    'status'     => 0,
                    'message_id' => $mid,
                    'create_time' => $now,
                    ($tab === 'private' ? 'employee_id' : 'terminal_id') => $tid,
                ]);
            }
        }, 'The message could not be sent.');
        if (! $ok) {
            return redirect()->back()->withInput()->with('error', 'The message could not be sent.');
        }
        $this->audit('device.message.send.' . $tab, count($targets) . ' targets');

        return redirect()->to(route_to('devmsg.tab', $tab))->with('success', 'Message queued for ' . count($targets) . ' target(s).');
    }

    public function delete(string $tab, int $id)
    {
        $table = $tab === 'private' ? 'iclock_privatemessage' : 'iclock_publicmessage';
        $db    = db_connect();
        $row   = $db->table($table)->select('message_id')->where('id', $id)->get()->getRowArray();
        $this->tx(function ($db) use ($table, $id, $row) {
            $db->table($table)->where('id', $id)->delete();
            if ($row && $row['message_id']) {
                $orphan = ! $db->table('iclock_publicmessage')->where('message_id', $row['message_id'])->countAllResults()
                    && ! $db->table('iclock_privatemessage')->where('message_id', $row['message_id'])->countAllResults();
                if ($orphan) {
                    $db->table('iclock_shortmessage')->where('id', $row['message_id'])->delete();
                }
            }
        }, 'Could not withdraw the message.');
        $this->audit('device.message.delete.' . $tab, (string) $id);

        return redirect()->to(route_to('devmsg.tab', $tab))->with('success', 'Message withdrawn.');
    }
}

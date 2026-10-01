<?php

namespace App\Controllers;

use App\Libraries\AttPolicy;

class Settings extends BaseController
{
    /** base_sysparam key => label, for the TimeTrack-only engine knobs */
    private const KV = [
        'day_change_hour'      => 'Day-change hour',
        'extra_late_grace'     => 'Extra late grace (min)',
        'extra_early_grace'    => 'Extra early grace (min)',
    ];

    public function index(): string
    {
        $db  = db_connect();
        $pol = AttPolicy::load($db);

        $timetable = $db->query(
            "SELECT alias, in_time, duration, allow_late, allow_leave_early,
                    in_ahead_margin, in_above_margin, out_ahead_margin, out_above_margin
               FROM att_timeinterval ORDER BY id LIMIT 1"
        )->getRowArray();

        return view('admin/settings', [
            'title'     => 'Settings',
            'subtitle'  => 'Attendance engine parameters',
            'active'    => 'settings',
            'pol'       => $pol,
            'kvLabels'  => self::KV,
            'timetable' => $timetable,
            'canEdit'   => $this->role() === 'admin',
        ]);
    }

    public function save()
    {
        $p  = $this->request;
        $db = db_connect();

        // ---- weekend days + OT switch + day-change → att_attpolicy (ZK-native) ----
        $weekend = array_values(array_filter(
            array_map('intval', (array) $p->getPost('weekend_days')),
            static fn ($d) => $d >= 0 && $d <= 6
        ));
        $dayChange = max(0, min(12, (int) $p->getPost('day_change_hour')));

        $ok = $this->tx(function ($db) use ($p, $weekend, $dayChange) {
            $polRow = $db->table('att_attpolicy')->orderBy('id')->get(1)->getRowArray();
            $polData = [
                'weekend1'   => $weekend[0] ?? 7,
                'weekend2'   => $weekend[1] ?? 7,
                'use_ot'     => (int) (bool) $p->getPost('overtime_enabled'),
                'day_change' => sprintf('%02d:00:00', $dayChange),
            ];
            if ($polRow) {
                $db->table('att_attpolicy')->where('id', $polRow['id'])->update($polData);
            }

            // ---- TimeTrack knobs → base_sysparam (para_type = 'timetrack') ----
            $kv = [
                'weekend_days'         => implode(',', $weekend),
                'overtime_enabled'     => (int) (bool) $p->getPost('overtime_enabled'),
                'incomplete_policy'    => in_array($p->getPost('incomplete_policy'), ['present', 'half', 'absent'], true)
                    ? $p->getPost('incomplete_policy') : 'half',
            ];
            foreach (array_keys(self::KV) as $k) {
                $kv[$k] = (int) $p->getPost($k);
            }
            foreach ($kv as $name => $value) {
                $exists = $db->table('base_sysparam')->where('para_type', 'timetrack')->where('para_name', $name)->countAllResults();
                $row    = ['para_type' => 'timetrack', 'para_name' => $name, 'para_value' => (string) $value];
                if ($exists) {
                    $db->table('base_sysparam')->where('para_type', 'timetrack')->where('para_name', $name)->update($row);
                } else {
                    $db->table('base_sysparam')->insert($row);
                }
            }
        });

        AttPolicy::flush();

        if (! $ok) {
            return redirect()->back()->with('error', 'Settings could not be saved.');
        }
        $this->audit('settings.update', 'attendance policy');

        return redirect()->to(route_to('settings'))->with('success', 'Attendance settings saved — the engine now uses these values.');
    }

    public function profile()
    {
        $model  = new \App\Models\AuthUserModel();
        $userId = (int) session()->get('userId');
        $row    = $model->find($userId);

        if ($this->request->is('post')) {
            if (! $this->validate([
                'name'  => 'required|min_length[2]|max_length[60]',
                'email' => "permit_empty|valid_email",
            ])) {
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }

            $name = trim($this->request->getPost('name'));
            $sp   = strpos($name, ' ');
            $payload = [
                'first_name'  => $sp === false ? $name : substr($name, 0, $sp),
                'last_name'   => $sp === false ? ''   : substr($name, $sp + 1),
                'email'       => $this->request->getPost('email') ?: '',
                'update_time' => date('Y-m-d H:i:sP'),
            ];
            $newPassword = (string) $this->request->getPost('password');
            if ($newPassword !== '') {
                if (strlen($newPassword) < 6) {
                    return redirect()->back()->withInput()->with('error', 'Password must be at least 6 characters.');
                }
                $payload['password'] = \App\Models\AuthUserModel::makeDjangoPassword($newPassword);
            }
            $model->update($userId, $payload);
            session()->set(['userName' => $name, 'userEmail' => $payload['email'] ?: session()->get('userEmail')]);
            $this->audit('profile.update', (string) ($row['username'] ?? $userId));

            return redirect()->to('/admin/profile')->with('success', 'Profile updated.');
        }

        // shape the view's expected keys
        $user = $row ? [
            'name'  => \App\Models\AuthUserModel::displayName($row),
            'email' => $row['email'] ?: '',
            'role'  => \App\Models\AuthUserModel::roleOf($row),
            'username' => $row['username'],
        ] : null;

        return view('admin/profile', [
            'title'  => 'My Profile',
            'active' => 'settings',
            'user'   => $user,
        ]);
    }
}

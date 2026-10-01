<?php

namespace App\Controllers;

/**
 * Entrance-control / temperature screening (the ZKBioTime "EP" module) —
 * body-temperature and mask-detection punches uploaded by EP-capable
 * terminals into ep_eptransaction, with a screening dashboard and the
 * alarm thresholds in ep_epsetup.
 */
class EP extends BaseController
{
    private const TABS = ['dashboard' => 'Dashboard', 'transactions' => 'Screening Log', 'setup' => 'Setup'];

    public function index(string $tab = 'dashboard'): string
    {
        if (! isset(self::TABS[$tab])) {
            $tab = 'dashboard';
        }
        $db  = db_connect();
        $cfg = $db->query('SELECT * FROM ep_epsetup ORDER BY id LIMIT 1')->getRowArray() ?: [
            'temp_warning' => 37.3, 'temp_unit' => 1, 'temp_alarm' => false, 'mask_alarm' => false,
        ];
        $d = ['title' => 'Temperature Screening', 'subtitle' => self::TABS[$tab], 'active' => 'ep', 'tab' => $tab, 'tabs' => self::TABS, 'cfg' => $cfg];

        if ($tab === 'dashboard') {
            $day  = $this->request->getGet('d') ?: date('Y-m-d');
            $warn = (float) $cfg['temp_warning'];
            $agg  = $db->query(
                "SELECT count(*) total,
                        count(*) FILTER (WHERE temperature >= ?) AS high,
                        count(*) FILTER (WHERE is_mask = 0)     AS nomask,
                        round(avg(NULLIF(temperature,0))::numeric, 1) AS avg_temp
                   FROM ep_eptransaction WHERE check_date = ?",
                [$warn, $day]
            )->getRowArray() ?: ['total' => 0, 'high' => 0, 'nomask' => 0, 'avg_temp' => null];
            $d['day']  = $day;
            $d['agg']  = $agg;
            $d['recent'] = $db->query(
                "SELECT t.check_datetime, t.temperature, t.is_mask, t.area, e.emp_code,
                        NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name
                   FROM ep_eptransaction t
              LEFT JOIN personnel_employee e ON e.id = t.emp_id
                  WHERE t.check_date = ? ORDER BY t.check_datetime DESC LIMIT 50",
                [$day]
            )->getResultArray();
        } elseif ($tab === 'transactions') {
            $b = $db->table('ep_eptransaction t')
                ->select("t.id, t.check_datetime, t.temperature, t.is_mask, t.area, t.source, t.terminal_sn,
                          e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS name")
                ->join('personnel_employee e', 'e.id = t.emp_id', 'left')
                ->orderBy('t.check_datetime', 'DESC');
            $q = trim((string) $this->request->getGet('q'));
            if ($q !== '') {
                $b->groupStart()->like('e.emp_code', $q)->orLike('e.first_name', $q)->orLike('e.last_name', $q)->groupEnd();
            }
            [$d['rows'], $d['pager']] = $this->paged($b, 40, 'ep');
            $d['q'] = $q;
        }

        return view('admin/ep/index', $d);
    }

    public function saveSetup()
    {
        $p    = $this->request;
        $data = [
            'temp_alarm'   => (bool) $p->getPost('temp_alarm'),
            'temp_warning' => max(30, min(45, (float) $p->getPost('temp_warning'))),
            'temp_unit'    => (int) $p->getPost('temp_unit'),   // 1 °C · 2 °F
            'mask_alarm'   => (bool) $p->getPost('mask_alarm'),
            'change_time'  => date('Y-m-d H:i:sP'),
            'change_user'  => session()->get('userName'),
        ];
        $db  = db_connect();
        $row = $db->table('ep_epsetup')->orderBy('id')->get(1)->getRowArray();
        $ok  = $this->dbGuard(static fn () => $row
            ? $db->table('ep_epsetup')->where('id', $row['id'])->update($data)
            : $db->table('ep_epsetup')->insert($data + ['status' => 0, 'is_default' => true, 'create_time' => date('Y-m-d H:i:sP')]));
        if ($ok === null) {
            return redirect()->back()->with('error', 'EP setup could not be saved.');
        }
        $this->audit('ep.setup.update', 'ep thresholds');

        return redirect()->to(route_to('ep.tab', 'setup'))->with('success', 'Screening thresholds saved.');
    }
}

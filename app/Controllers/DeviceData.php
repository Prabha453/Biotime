<?php

namespace App\Controllers;

/**
 * Device ▸ Data — the biometric records the terminals have uploaded:
 *
 *   biotmp    iclock_biodata   fingerprint / face / palm / finger-vein templates
 *   biophoto  iclock_biophoto  visible-light registration photos (+ approval state)
 *
 * Both are read-only apart from Delete (ZKBio only allows delete/view here).
 * The "Transaction" sidebar item is the existing PunchLogs controller.
 */
class DeviceData extends BaseController
{
    private const TABS = ['biotmp' => 'Biometric Template', 'biophoto' => 'Bio-Photo'];

    /** iclock.BioData.bio_type */
    public const BIO_TYPES = [
        1 => 'Fingerprint', 2 => 'Face', 3 => 'Voiceprint', 4 => 'Iris', 5 => 'Retina',
        6 => 'Palm print', 7 => 'Finger vein', 8 => 'Palm vein', 9 => 'Visible face', 10 => 'Visible palm',
    ];

    /** iclock.BioPhoto.approval_state */
    public const APPROVAL_STATES = [0 => 'Pending', 1 => 'Approved', 2 => 'Rejected', 3 => 'Auto-approved'];

    public function index(string $tab = 'biotmp'): string
    {
        if (! isset(self::TABS[$tab])) {
            $tab = 'biotmp';
        }
        $db = db_connect();

        if ($tab === 'biophoto') {
            $b = $db->table('iclock_biophoto p')
                ->select("p.id, p.enroll_sn, p.approval_state, p.remark, e.emp_code,
                          NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS emp_name,
                          to_char(p.register_time,'YYYY-MM-DD HH24:MI') AS registered_at,
                          to_char(p.approval_time,'YYYY-MM-DD HH24:MI') AS approved_at")
                ->join('personnel_employee e', 'e.id = p.employee_id', 'left')
                ->orderBy('p.register_time', 'DESC');
            $spec = [
                'emp_code'      => ['e.emp_code', 'text'],
                'emp_name'      => ["TRIM(CONCAT(e.first_name,' ',e.last_name))", 'text'],
                'enroll_sn'     => ['p.enroll_sn', 'text'],
                'approval_state'=> ['p.approval_state', 'select'],
                'register_time' => ['p.register_time', 'date'],
            ];
        } else {
            $b = $db->table('iclock_biodata d')
                ->select("d.id, d.bio_type, d.bio_no, d.valid, d.duress, d.major_ver, d.minor_ver, d.sn AS enroll_sn,
                          e.emp_code, NULLIF(TRIM(CONCAT(e.first_name,' ',e.last_name)),'') AS emp_name,
                          to_char(d.update_time,'YYYY-MM-DD HH24:MI') AS updated_at")
                ->join('personnel_employee e', 'e.id = d.employee_id', 'left')
                ->orderBy('d.id', 'DESC');
            $spec = [
                'emp_code'  => ['e.emp_code', 'text'],
                'emp_name'  => ["TRIM(CONCAT(e.first_name,' ',e.last_name))", 'text'],
                'bio_type'  => ['d.bio_type', 'select'],
                'enroll_sn' => ['d.sn', 'text'],
            ];
        }

        $fbApplied = $this->filterBuilder($b, $spec);
        [$rows, $pager] = $this->paged($b, 40);

        return view('admin/devdata/index', [
            'title'          => self::TABS[$tab],
            'subtitle'       => 'Biometric records uploaded by the terminals',
            'active'         => 'devdata.' . $tab,
            'tab'            => $tab,
            'tabs'           => self::TABS,
            'rows'           => $rows,
            'pager'          => $pager,
            'fbApplied'      => $fbApplied,
            'bioTypes'       => self::BIO_TYPES,
            'approvalStates' => self::APPROVAL_STATES,
        ]);
    }

    public function bulkDelete(string $tab)
    {
        if (! isset(self::TABS[$tab])) {
            return redirect()->back()->with('error', 'Unknown list.');
        }
        $table = $tab === 'biophoto' ? 'iclock_biophoto' : 'iclock_biodata';
        $ids   = array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
        if (! $ids) {
            return redirect()->back()->with('error', 'Nothing selected.');
        }
        db_connect()->table($table)->whereIn('id', $ids)->delete();
        $this->audit('device.data.delete.' . $tab, count($ids) . ' rows');

        return redirect()->to(route_to('devdata.tab', $tab))->with('success', 'Deleted ' . count($ids) . ' record(s).');
    }
}

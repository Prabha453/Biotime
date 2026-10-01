<?php

namespace App\Controllers;

/**
 * Device ▸ Configurations — the global device policy singleton
 * (iclock_devicemoduleconfig). Mirrors every field of ZKBioTime's
 * DeviceModuleConfig: registration / upload rules, communication, data
 * retention and the six bio-photo approval policies.
 */
class DeviceConfig extends BaseController
{
    /** iclock.DeviceModuleConfig.*_policy — approval routing for a bio-photo source */
    public const APPROVAL_POLICY = [0 => 'Needs approval', 3 => 'Auto-approve'];

    public const TRANSFER_MODES = [1 => 'Real-time', 0 => 'Scheduled'];
    public const SYNC_MODES     = [1 => 'Real-time', 0 => 'Scheduled', 2 => 'Manual'];

    /** every editable column → [caster, default] */
    private const FIELDS = [
        'enable_registration'     => ['bool', false],
        'enable_resigned_filter'  => ['bool', false],
        'enable_auto_add'         => ['bool', true],
        'enable_name_upload'      => ['bool', true],
        'enable_name_download'    => ['bool', true],
        'enable_card_upload'      => ['bool', true],
        'encryption'              => ['bool', true],
        'global_setup'            => ['bool', false],
        'timezone'                => ['int', 8],
        'heartbeat'               => ['int', 10],
        'transfer_mode'           => ['int', 1],
        'transfer_interval'       => ['int', 1],
        'transfer_time'           => ['str', '00:00;14:05'],
        'sync_mode'               => ['int', 1],
        'sync_time'               => ['str', '00:00;12:00'],
        'transaction_retention'   => ['int', 9999],
        'command_retention'       => ['int', 90],
        'dev_log_retention'       => ['int', 90],
        'upload_log_retention'    => ['int', 90],
        'user_notification_log_retention' => ['int', 90],
        'report_log_retention'    => ['int', 9999],
        'edit_policy'             => ['int', 0],
        'import_policy'           => ['int', 0],
        'mobile_policy'           => ['int', 0],
        'device_policy'           => ['int', 3],
        'api_policy'              => ['int', 3],
        'visitor_policy'          => ['int', 0],
    ];

    public function index(): string
    {
        $cfg = db_connect()->query('SELECT * FROM iclock_devicemoduleconfig ORDER BY id LIMIT 1')->getRowArray() ?: [];
        foreach (self::FIELDS as $k => [, $def]) {
            $cfg[$k] ??= $def;
        }

        return view('admin/deviceconfig/index', [
            'title'      => 'Configurations',
            'subtitle'   => 'Global device communication & data policy',
            'active'     => 'deviceconfig',
            'cfg'        => $cfg,
            'tzList'     => $this->timezones(),
            'transferModes' => self::TRANSFER_MODES,
            'syncModes'     => self::SYNC_MODES,
            'policies'      => self::APPROVAL_POLICY,
        ]);
    }

    public function saveConfig()
    {
        $p    = $this->request;
        $data = ['change_time' => date('Y-m-d H:i:sP'), 'change_user' => session()->get('userName')];
        foreach (self::FIELDS as $key => [$type, $def]) {
            $v = $p->getPost($key);
            $data[$key] = match ($type) {
                'bool'  => (bool) $v,
                'int'   => $v === null || $v === '' ? $def : (int) $v,
                default => trim((string) ($v ?? $def)) ?: $def,
            };
        }
        // sane floors on the retention windows
        foreach (['transaction_retention', 'command_retention', 'dev_log_retention',
            'upload_log_retention', 'user_notification_log_retention', 'report_log_retention'] as $k) {
            $data[$k] = max(1, (int) $data[$k]);
        }
        $data['heartbeat'] = max(5, (int) $data['heartbeat']);

        $db  = db_connect();
        $row = $db->table('iclock_devicemoduleconfig')->orderBy('id')->get(1)->getRowArray();
        $ok  = $this->dbGuard(static fn () => $row
            ? $db->table('iclock_devicemoduleconfig')->where('id', $row['id'])->update($data)
            : $db->table('iclock_devicemoduleconfig')->insert($data + ['status' => 0, 'create_time' => date('Y-m-d H:i:sP')]));
        if ($ok === null) {
            return redirect()->back()->with('error', 'Configuration could not be saved.');
        }
        $this->audit('device.policy.update', 'device configuration');

        return redirect()->to(route_to('deviceconfig'))->with('success', 'Device configuration saved.');
    }

    private function timezones(): array
    {
        $tz = [];
        for ($h = -12; $h <= 13; $h++) {
            $tz[$h] = sprintf('GMT%s%d:00', $h < 0 ? '-' : '+', abs($h));
        }
        ksort($tz);

        return $tz;
    }
}

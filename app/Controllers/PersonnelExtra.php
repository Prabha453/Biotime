<?php

namespace App\Controllers;

/**
 * Personnel ▸ extras, mirroring ZKBioTime's Personnel module:
 *   Custom Attributes  → personnel_employeecustomattribute
 *   Configurations     → base_systemsetting name='psnl_config_setting'
 *                        (Code Setting + QR Code Setting)
 *
 * The resignation register lives in its own Resign module; custom
 * attributes surface on the employee form.
 */
class PersonnelExtra extends BaseController
{
    private const TABS = ['attributes' => 'Custom Attributes', 'config' => 'Configurations'];

    // ZKBioTime personnel.EmployeeCustomAttribute.attr_type — verbatim choices
    private const ATTR_TYPES = [1 => 'Text', 2 => 'Combobox'];

    /** base_systemsetting row that holds the Personnel module parameters. */
    private const SETTING = 'psnl_config_setting';

    private const CONFIG_DEFAULTS = [
        'num_emp_code'                    => false,   // employee ID must be numeric
        'order_by_num_emp_code'           => true,    // sort the employee list numerically
        'emp_default_password'            => '',      // default self-service password for new staff
        'enable_access_qrcode'            => false,
        'qrcode_validity_period_access'   => 30,      // minutes
        'qrcode_validity_period_biophoto' => 30,      // minutes
    ];

    public function index(string $tab = 'attributes'): string
    {
        if (! isset(self::TABS[$tab])) {
            $tab = 'attributes';
        }
        $db = db_connect();
        $d  = [
            'title'    => $tab === 'config' ? 'Personnel Configuration' : 'Custom Attributes',
            'subtitle' => self::TABS[$tab],
            'active'   => $tab === 'config' ? 'psnl.config' : 'psnl.attr',
            'tab'      => $tab,
            'tabs'     => self::TABS,
            'types'    => self::ATTR_TYPES,
        ];

        if ($tab === 'config') {
            $d['cfg'] = $this->config();
        } else {
            $b = $db->table('personnel_employeecustomattribute')->orderBy('attr_name');
            $d['fbApplied'] = $this->filterBuilder($b, [
                'attr_name'  => ['attr_name', 'text'],
                'attr_type'  => ['attr_type', 'select'],
                'is_unique'  => ['is_unique', 'bool'],
                'enable'     => ['enable', 'bool'],
            ]);
            [$d['rows'], $d['pager']] = $this->paged($b, 20);
        }

        return view('admin/personnelextra/index', $d);
    }

    /** Current Personnel parameters, merged over the defaults. */
    private function config(): array
    {
        $row = db_connect()->table('base_systemsetting')->select('value')->where('name', self::SETTING)->get()->getRowArray();
        $saved = $row ? json_decode((string) $row['value'], true) : [];

        return array_merge(self::CONFIG_DEFAULTS, is_array($saved) ? array_intersect_key($saved, self::CONFIG_DEFAULTS) : []);
    }

    // -------------------------------------------------------- custom attributes

    public function saveField()
    {
        if (! $this->validate([
            'attr_name' => 'required|max_length[50]',
            'attr_type' => 'permit_empty|in_list[1,2]',
        ])) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'cfForm');
        }
        $p    = $this->request;
        $id   = (int) $p->getPost('id');
        $type = (int) $p->getPost('attr_type') ?: 1;

        // Combobox (2): ZKBioTime stores attr_value as a JSON array of
        // {"option": "..."} objects (its own edit form's layui table writes
        // exactly this shape) — up to 5, non-empty, unique. Since this table
        // is shared with the real app, a plain comma-separated string here
        // would fail its JSON.parse() and break that attribute there.
        if ($type === 2) {
            $opts = array_values(array_unique(array_filter(array_map(
                'trim',
                explode(',', (string) $p->getPost('attr_value'))
            ), static fn ($o) => $o !== '')));
            $opts = array_slice($opts, 0, 5);
            $attrValue = $opts !== [] ? json_encode(array_map(static fn ($o) => ['option' => $o], $opts)) : null;
        } else {
            $attrValue = ($v = trim((string) $p->getPost('attr_value'))) !== '' ? mb_substr($v, 0, 999) : null;
        }

        $data = [
            'attr_name'  => trim($p->getPost('attr_name')),
            'attr_type'  => $type,
            'attr_value' => $attrValue,
            'is_unique'  => (bool) $p->getPost('is_unique'),
            'enable'     => $id ? (bool) $p->getPost('enable') : true,
        ];
        $rid = $this->saveRow('personnel_employeecustomattribute', $data, $id);
        if ($rid === null) {
            return redirect()->back()->with('error', 'Attribute could not be saved — the name must be unique.');
        }
        $this->audit($id ? 'personnel.attr.update' : 'personnel.attr.create', $data['attr_name']);

        return redirect()->to(route_to('personnelextra.tab', 'attributes'))->with('success', 'Custom attribute saved.');
    }

    public function bulkDeleteField()
    {
        $ids = array_values(array_filter(array_map('intval', (array) $this->request->getPost('ids'))));
        if (! $ids) {
            return redirect()->back()->with('error', 'Nothing selected.');
        }
        $db   = db_connect();
        $gone = $kept = 0;
        foreach ($ids as $id) {
            if ($db->table('personnel_employeeextrainfo')->like('value', '"' . $id . '"')->countAllResults()) {
                $kept++;
                continue;
            }
            $db->table('personnel_employeecustomattribute')->where('id', $id)->delete();
            $gone++;
        }
        $this->audit('personnel.attr.bulk_delete', "{$gone} deleted, {$kept} kept");

        return redirect()->to(route_to('personnelextra.tab', 'attributes'))->with(
            $gone ? 'success' : 'error',
            "Deleted {$gone}." . ($kept ? " Kept {$kept} that hold employee data." : '')
        );
    }

    public function deleteField(int $id)
    {
        $this->deleteRow('personnel_employeecustomattribute', $id, [
            'This attribute holds data on employee records' => "SELECT 1 FROM personnel_employeeextrainfo WHERE value LIKE '%\"" . $id . "\"%' LIMIT 1",
        ]);
        $this->audit('personnel.attr.delete', (string) $id);

        return redirect()->to(route_to('personnelextra.tab', 'attributes'))->with('success', 'Custom attribute deleted.');
    }

    // ------------------------------------------------------------- configurations

    public function saveConfig()
    {
        $p   = $this->request;
        $int = static fn (string $k, int $min, int $max, int $def): int => max($min, min($max, (int) ($p->getPost($k) ?: $def)));

        $cfg = [
            'num_emp_code'                    => (bool) $p->getPost('num_emp_code'),
            'order_by_num_emp_code'           => (bool) $p->getPost('order_by_num_emp_code'),
            'emp_default_password'            => mb_substr(trim((string) $p->getPost('emp_default_password')), 0, 30),
            'enable_access_qrcode'            => (bool) $p->getPost('enable_access_qrcode'),
            'qrcode_validity_period_access'   => $int('qrcode_validity_period_access', 1, 1440, 30),
            'qrcode_validity_period_biophoto' => $int('qrcode_validity_period_biophoto', 1, 1440, 30),
        ];

        // keep any keys ZKBioTime wrote that we don't manage (e.g. the QR secret key)
        $db   = db_connect();
        $row  = $db->table('base_systemsetting')->select('value')->where('name', self::SETTING)->get()->getRowArray();
        $keep = $row ? (array) json_decode((string) $row['value'], true) : [];
        $val  = json_encode(array_merge($keep, $cfg));

        $ok = $this->dbGuard(static function () use ($db, $row, $val) {
            return $row
                ? $db->table('base_systemsetting')->where('name', self::SETTING)->update(['value' => $val, 'change_time' => date('Y-m-d H:i:sP'), 'change_user' => session()->get('userName')])
                : $db->table('base_systemsetting')->insert(['name' => self::SETTING, 'value' => $val, 'status' => 0, 'create_time' => date('Y-m-d H:i:sP'), 'create_user' => session()->get('userName')]);
        });
        if ($ok === null) {
            return redirect()->back()->with('error', 'Configuration could not be saved.');
        }
        $this->audit('personnel.config.update', self::SETTING);

        return redirect()->to(route_to('personnelextra.tab', 'config'))->with('success', 'Personnel configuration saved.');
    }
}

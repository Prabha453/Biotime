<?php

namespace App\Controllers;

/**
 * Workflow — ZKBioTime's approval-flow designer.
 *
 *   workflow_workflowrole   — approval roles (hierarchical via parent_role)
 *   workflow_workflowengine — one approval definition per request type / scope
 *   workflow_workflownode   — the ordered approval steps of an engine, each
 *                             naming approver + notifier roles
 *
 * The Requests screen consumes an engine when one matches the applicant
 * (by employee / department / position / company); with no engine it falls
 * back to single-step admin approval.
 */
class Workflow extends BaseController
{
    private const TABS = ['flows' => 'Workflow Builder', 'roles' => 'Workflow Roles'];

    /** django_content_type rows a workflow can target (label => id resolved live). */
    public const TARGETS = [
        'att.leave'          => 'Leave',
        'att.overtime'       => 'Overtime',
        'att.manuallog'      => 'Manual Punch',
        'att.training'       => 'Training',
        'att.changeschedule' => 'Adjust Schedule',
        'visitor.reservation' => 'Visitor Reservation',
        'meeting.meetingentity' => 'Meeting',
    ];

    /** workflow_workflowengine.workflow_type */
    public const SCOPE = [0 => 'All employees', 1 => 'Department', 2 => 'Position', 3 => 'Specific employees'];

    public function index(string $tab = 'flows')
    {
        if (! isset(self::TABS[$tab])) {
            $tab = 'flows';
        }
        if ($this->wantsJson()) {
            return view('admin/workflow/_list', $this->tabData($tab));
        }

        return view('admin/workflow/index', $this->tabData($tab));
    }

    private function tabData(string $tab): array
    {
        $db = db_connect();
        $d  = [
            'title'    => 'Workflow',
            'subtitle' => self::TABS[$tab],
            'active'   => 'workflow',
            'tab'      => $tab,
            'tabs'     => self::TABS,
            'companies' => $db->query('SELECT id, company_name FROM personnel_company ORDER BY is_default DESC, company_name')->getResultArray(),
            'roles'     => $db->query('SELECT id, role_code, role_name FROM workflow_workflowrole ORDER BY role_name')->getResultArray(),
            'ctypes'    => $this->contentTypes(),
        ];

        if ($tab === 'roles') {
            $b = $db->table('workflow_workflowrole r')
                ->select("r.id, r.role_code, r.role_name, r.description, r.parent_role_id, r.company_id,
                          pr.role_name AS parent_name, c.company_name,
                          (SELECT count(*) FROM personnel_employee_flow_role f WHERE f.workflowrole_id = r.id) emp_count")
                ->join('workflow_workflowrole pr', 'pr.id = r.parent_role_id', 'left')
                ->join('personnel_company c', 'c.id = r.company_id', 'left')
                ->orderBy('r.role_name', 'ASC');
        } else {
            $b = $db->table('workflow_workflowengine e')
                ->select("e.id, e.workflow_code, e.workflow_name, e.description, e.workflow_type,
                          e.start_date, e.end_date, e.content_type_id, e.departments_id,
                          e.company_id, c.company_name,
                          ct.app_label || '.' || ct.model AS ctype_key,
                          (SELECT count(*) FROM workflow_workflownode n WHERE n.workflow_engine_id = e.id) node_count")
                ->join('personnel_company c', 'c.id = e.company_id', 'left')
                ->join('django_content_type ct', 'ct.id = e.content_type_id', 'left')
                ->orderBy('e.workflow_name', 'ASC');
        }
        [$d['rows'], $d['pager']] = $this->paged($b, 20);

        return $d;
    }

    /** [content_type_id => label] for the workflow-target dropdown. */
    private function contentTypes(): array
    {
        $db  = db_connect();
        $out = [];
        foreach (self::TARGETS as $key => $label) {
            [$app, $model] = explode('.', $key);
            $row = $db->table('django_content_type')->select('id')
                ->where('app_label', $app)->where('model', $model)->get()->getRowArray();
            if ($row) {
                $out[(int) $row['id']] = $label;
            }
        }

        return $out;
    }

    private function done(string $tab, string $type, string $msg)
    {
        return $this->crudDone(
            route_to('workflow.tab', $tab),
            $type,
            $msg,
            $type === 'success' ? view('admin/workflow/_list', $this->tabData($tab)) : null,
        );
    }

    private function invalid(string $modal)
    {
        return $this->wantsJson()
            ? $this->jsonFail('Please correct the highlighted fields.', $this->validator->getErrors())
            : redirect()->back()->withInput()->with('errors', $this->validator->getErrors())->with('reopen', $modal);
    }

    private function fail(string $msg)
    {
        return $this->wantsJson() ? $this->jsonFail($msg) : redirect()->back()->withInput()->with('error', $msg);
    }

    // ------------------------------------------------------------------- roles

    public function saveRole()
    {
        if (! $this->validate([
            'role_code' => 'required|max_length[30]',
            'role_name' => 'required|max_length[50]',
        ], [
            'role_code' => ['required' => 'Code is required.'],
            'role_name' => ['required' => 'Name is required.'],
        ])) {
            return $this->invalid('roleForm');
        }
        $p    = $this->request;
        $id   = (int) $p->getPost('id');
        $data = [
            'role_code'      => trim($p->getPost('role_code')),
            'role_name'      => trim($p->getPost('role_name')),
            'description'    => ($v = trim((string) $p->getPost('description'))) !== '' ? $v : null,
            'parent_role_id' => (int) $p->getPost('parent_role_id') ?: null,
            'company_id'     => (int) $p->getPost('company_id') ?: 1,
        ];
        if ($id && $data['parent_role_id'] === $id) {
            return $this->fail('A role cannot be its own parent.');
        }
        $ok = $this->tx(static function ($db) use ($id, $data) {
            $id
                ? $db->table('workflow_workflowrole')->where('id', $id)->update($data)
                : $db->table('workflow_workflowrole')->insert($data);
        });
        if (! $ok) {
            return $this->fail('Role could not be saved — the code may already be in use for this company.');
        }
        $this->audit($id ? 'workflow.role.update' : 'workflow.role.create', $data['role_code']);

        return $this->done('roles', 'success', 'Workflow role saved.');
    }

    public function deleteRole(int $id)
    {
        $db = db_connect();
        if ($db->table('personnel_employee_flow_role')->where('workflowrole_id', $id)->countAllResults()) {
            return $this->fail('Employees are assigned this role — reassign them first.');
        }
        if ($db->table('workflow_workflowrole')->where('parent_role_id', $id)->countAllResults()) {
            return $this->fail('This role has child roles — remove those first.');
        }
        if ($db->table('workflow_workflownode_approver')->where('workflowrole_id', $id)->countAllResults()
            || $db->table('workflow_workflownode_notifier')->where('workflowrole_id', $id)->countAllResults()) {
            return $this->fail('This role is used in a workflow step — remove it from the step first.');
        }
        $this->tx(static function ($db) use ($id) {
            $db->table('workflow_workflowrole')->where('id', $id)->delete();
        });
        $this->audit('workflow.role.delete', (string) $id);

        return $this->done('roles', 'success', 'Workflow role deleted.');
    }

    // ------------------------------------------------------------------- flows

    public function saveFlow()
    {
        if (! $this->validate([
            'workflow_code'   => 'required|max_length[50]',
            'workflow_name'   => 'required|max_length[50]',
            'content_type_id' => 'required|is_natural_no_zero',
            'workflow_type'   => 'required|in_list[0,1,2,3]',
            'start_date'      => 'required|valid_date',
            'end_date'        => 'required|valid_date',
        ], [
            'workflow_code' => ['required' => 'Code is required.'],
            'workflow_name' => ['required' => 'Name is required.'],
            'content_type_id' => ['required' => 'Choose what this workflow approves.'],
        ])) {
            return $this->invalid('flowForm');
        }
        $p    = $this->request;
        $id   = (int) $p->getPost('id');
        $type = (int) $p->getPost('workflow_type');
        $data = [
            'workflow_code'   => trim($p->getPost('workflow_code')),
            'workflow_name'   => trim($p->getPost('workflow_name')),
            'description'     => mb_substr(trim((string) $p->getPost('description')), 0, 50),
            'content_type_id' => (int) $p->getPost('content_type_id'),
            'workflow_type'   => $type,
            'is_leave'        => false,
            'start_date'      => $p->getPost('start_date'),
            'end_date'        => $p->getPost('end_date'),
            'company_id'      => (int) $p->getPost('company_id') ?: 1,
            'departments_id'  => $type === 1 ? ((int) $p->getPost('department_id') ?: null) : null,
            'applicant_position_id' => $type === 2 ? ((int) $p->getPost('applicant_position_id') ?: null) : null,
        ];
        $ok = $this->tx(static function ($db) use ($id, $data) {
            if ($id) {
                $db->table('workflow_workflowengine')->where('id', $id)->update($data);
            } else {
                $db->table('workflow_workflowengine')->insert($data);
                $id = (int) $db->insertID();
                // seed a first approval step so the flow is usable immediately
                $db->table('workflow_workflownode')->insert([
                    'node_name' => 'Approval', 'order_id' => 1,
                    'approver_by_overall' => false, 'notify_by_overall' => false,
                    'from_day' => 0, 'to_day' => 0, 'workflow_engine_id' => $id,
                ]);
            }
        });
        if (! $ok) {
            return $this->fail('Workflow could not be saved — the code must be unique.');
        }
        $this->audit($id ? 'workflow.flow.update' : 'workflow.flow.create', $data['workflow_code']);

        return $this->done('flows', 'success', 'Workflow saved.');
    }

    public function deleteFlow(int $id)
    {
        if (db_connect()->table('workflow_workflowinstance')->where('workflow_engine_id', $id)->countAllResults()) {
            return $this->fail('Requests have already been routed through this workflow — it cannot be deleted.');
        }
        $this->tx(static function ($db) use ($id) {
            $nodes = $db->table('workflow_workflownode')->select('id')->where('workflow_engine_id', $id)->get()->getResultArray();
            foreach ($nodes as $n) {
                $db->table('workflow_workflownode_approver')->where('workflownode_id', $n['id'])->delete();
                $db->table('workflow_workflownode_notifier')->where('workflownode_id', $n['id'])->delete();
            }
            $db->table('workflow_workflownode')->where('workflow_engine_id', $id)->delete();
            $db->table('workflow_workflowengine')->where('id', $id)->delete();
        });
        $this->audit('workflow.flow.delete', (string) $id);

        return $this->done('flows', 'success', 'Workflow deleted.');
    }

    // ------------------------------------------------------ flow detail + nodes

    public function flow(int $id)
    {
        $db   = db_connect();
        $flow = $db->query(
            "SELECT e.*, ct.app_label || '.' || ct.model AS ctype_key, c.company_name
               FROM workflow_workflowengine e
          LEFT JOIN django_content_type ct ON ct.id = e.content_type_id
          LEFT JOIN personnel_company   c  ON c.id = e.company_id
              WHERE e.id = ?",
            [$id]
        )->getRowArray();
        if (! $flow) {
            return redirect()->to(route_to('workflow'))->with('error', 'Workflow not found.');
        }

        $nodes = $db->query(
            "SELECT n.*,
                    (SELECT string_agg(r.role_name, ', ' ORDER BY r.role_name)
                       FROM workflow_workflownode_approver a
                       JOIN workflow_workflowrole r ON r.id = a.workflowrole_id
                      WHERE a.workflownode_id = n.id) approver_names,
                    (SELECT string_agg(r.role_name, ', ' ORDER BY r.role_name)
                       FROM workflow_workflownode_notifier x
                       JOIN workflow_workflowrole r ON r.id = x.workflowrole_id
                      WHERE x.workflownode_id = n.id) notifier_names,
                    (SELECT array_agg(a.workflowrole_id) FROM workflow_workflownode_approver a WHERE a.workflownode_id = n.id) approver_ids,
                    (SELECT array_agg(x.workflowrole_id) FROM workflow_workflownode_notifier x WHERE x.workflownode_id = n.id) notifier_ids
               FROM workflow_workflownode n
              WHERE n.workflow_engine_id = ?
           ORDER BY n.order_id, n.id",
            [$id]
        )->getResultArray();

        return view('admin/workflow/flow', [
            'title'    => 'Workflow · ' . $flow['workflow_name'],
            'subtitle' => 'Approval steps',
            'active'   => 'workflow',
            'flow'     => $flow,
            'nodes'    => $nodes,
            'roles'    => $db->query('SELECT id, role_name FROM workflow_workflowrole ORDER BY role_name')->getResultArray(),
            'targetLabel' => self::TARGETS[$flow['ctype_key']] ?? $flow['ctype_key'],
            'scopeLabel'  => self::SCOPE[(int) $flow['workflow_type']] ?? '—',
        ]);
    }

    public function saveNode(int $flowId)
    {
        if (! db_connect()->table('workflow_workflowengine')->where('id', $flowId)->countAllResults()) {
            return $this->fail('Workflow not found.');
        }
        if (! $this->validate([
            'node_name' => 'required|max_length[30]',
            'order_id'  => 'required|is_natural_no_zero',
        ])) {
            return redirect()->back()->with('errors', $this->validator->getErrors())->with('reopen', 'nodeForm');
        }
        $p  = $this->request;
        $id = (int) $p->getPost('id');
        if ($id && ! db_connect()->table('workflow_workflownode')
            ->where('id', $id)->where('workflow_engine_id', $flowId)->countAllResults()) {
            $id = 0;   // stale / mismatched id → create a new step instead
        }
        $approver = array_filter(array_map('intval', (array) $p->getPost('approver_ids')));
        $notifier = array_filter(array_map('intval', (array) $p->getPost('notifier_ids')));
        if (! $approver) {
            return redirect()->back()->with('error', 'Pick at least one approver role.');
        }
        $data = [
            'node_name'           => trim($p->getPost('node_name')),
            'order_id'            => (int) $p->getPost('order_id'),
            'approver_by_overall' => (bool) $p->getPost('approver_by_overall'),
            'notify_by_overall'   => (bool) $p->getPost('notify_by_overall'),
            'from_day'            => (int) $p->getPost('from_day'),
            'to_day'             => (int) $p->getPost('to_day'),
            'workflow_engine_id'  => $flowId,
        ];
        $this->tx(static function ($db) use ($id, $data, $approver, $notifier) {
            if ($id) {
                $db->table('workflow_workflownode')->where('id', $id)->update($data);
            } else {
                $db->table('workflow_workflownode')->insert($data);
                $id = (int) $db->insertID();
            }
            $db->table('workflow_workflownode_approver')->where('workflownode_id', $id)->delete();
            $db->table('workflow_workflownode_notifier')->where('workflownode_id', $id)->delete();
            foreach ($approver as $r) {
                $db->table('workflow_workflownode_approver')->insert(['workflownode_id' => $id, 'workflowrole_id' => $r]);
            }
            foreach ($notifier as $r) {
                $db->table('workflow_workflownode_notifier')->insert(['workflownode_id' => $id, 'workflowrole_id' => $r]);
            }
        });
        $this->audit('workflow.node.save', 'flow=' . $flowId);

        return redirect()->to(route_to('workflow.flow', $flowId))->with('success', 'Approval step saved.');
    }

    public function deleteNode(int $flowId, int $id)
    {
        $this->tx(static function ($db) use ($id) {
            $db->table('workflow_workflownode_approver')->where('workflownode_id', $id)->delete();
            $db->table('workflow_workflownode_notifier')->where('workflownode_id', $id)->delete();
            $db->table('workflow_workflownode')->where('id', $id)->delete();
        });
        $this->audit('workflow.node.delete', (string) $id);

        return redirect()->to(route_to('workflow.flow', $flowId))->with('success', 'Approval step removed.');
    }
}

<?php

namespace App\Controllers;

use App\Models\AuthUserModel;

/**
 * Administrator accounts — these are ZKBioTime `auth_user` rows, the same
 * accounts used to sign in to the ZKBioTime console.
 *
 *   role "admin"   → is_superuser = true
 *   role "manager" → is_staff = true (not superuser)
 *   role "viewer"  → neither
 *   disabled       → is_active = false
 */
class Admins extends BaseController
{
    public function index(): string
    {
        $b = db_connect()->table('auth_user')
            ->select("id, username, email,
                      NULLIF(TRIM(CONCAT(first_name,' ',last_name)),'') AS name,
                      is_superuser, is_staff, is_active,
                      to_char(last_login,'YYYY-MM-DD HH24:MI') AS last_login, login_count")
            ->where('COALESCE(del_flag, 0)', 0)
            ->orderBy('id', 'ASC');
        [$rows, $pager] = $this->paged($b, 20);

        foreach ($rows as &$r) {
            $r['role']   = AuthUserModel::roleOf($r);
            $r['status'] = AuthUserModel::isActive($r) ? 'active' : 'disabled';
        }
        unset($r);

        return view('admin/admins/index', [
            'title'    => 'Administrators',
            'subtitle' => 'ZKBioTime accounts that can sign in',
            'active'   => 'admins',
            'rows'     => $rows,
            'pager'    => $pager,
        ]);
    }

    public function save()
    {
        $model = new AuthUserModel();
        $id    = (int) $this->request->getPost('id');

        $rules = [
            'username' => "required|alpha_numeric_punct|max_length[30]|is_unique[auth_user.username,id,{$id}]",
            'name'     => 'required|min_length[2]|max_length[60]',
            'role'     => 'required|in_list[admin,manager,viewer]',
        ];
        if (! $id) {
            $rules['password'] = 'required|min_length[6]';
        }
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()
                ->with('errors', $this->validator->getErrors())
                ->with('reopen', $id ? 'admEdit' . $id : 'admNew');
        }

        $p    = $this->request;
        $name = trim($p->getPost('name'));
        $sp   = strpos($name, ' ');
        $role = $p->getPost('role');

        $data = [
            'username'     => trim($p->getPost('username')),
            'first_name'   => $sp === false ? $name : substr($name, 0, $sp),
            'last_name'    => $sp === false ? ''   : substr($name, $sp + 1),
            'email'        => $p->getPost('email') ?: '',
            'is_superuser' => $role === 'admin',
            'is_staff'     => $role === 'admin' || $role === 'manager',
            'is_active'    => ($p->getPost('status') ?: 'active') === 'active',
            'update_time'  => date('Y-m-d H:i:sP'),
        ];
        if ($pw = (string) $p->getPost('password')) {
            $data['password'] = AuthUserModel::makeDjangoPassword($pw);
        }

        $ok = $this->dbGuard(fn () => $id
            ? $model->update($id, $data)
            : db_connect()->table('auth_user')->insert($data + [
                'is_public'           => false,
                'can_manage_all_dept' => false,
                'two_factor_enabled'  => false,
                'del_flag'            => 0,
                'date_joined'         => date('Y-m-d H:i:sP'),
                'login_count'         => 0,
            ]));
        if ($ok === null) {
            return redirect()->back()->withInput()->with('error', 'Account could not be saved — the username may already exist.');
        }
        $this->audit($id ? 'admin.update' : 'admin.create', (string) $data['username'], 'role=' . $role);

        return redirect()->to(route_to('admins'))->with('success', $id ? 'Account updated.' : 'Account created.');
    }

    public function delete(int $id)
    {
        $db = db_connect();
        if ($id === (int) session()->get('userId')) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }
        $admins = (int) $db->table('auth_user')->where('is_superuser', true)->where('is_active', true)->countAllResults();
        $row    = $db->table('auth_user')->where('id', $id)->get()->getRowArray();
        if ($row && in_array($row['is_superuser'], [true, 't'], true) && $admins <= 1) {
            return redirect()->back()->with('error', 'At least one active superuser must remain.');
        }
        // auth_user is referenced widely (audit log, notifications…) — disable, don't hard-delete.
        $db->table('auth_user')->where('id', $id)->update(['is_active' => false, 'del_flag' => 1]);
        $this->audit('admin.delete', (string) $id);

        return redirect()->to(route_to('admins'))->with('success', 'Account removed.');
    }

    public function toggle(int $id)
    {
        $db  = db_connect();
        $u   = $db->table('auth_user')->where('id', $id)->get()->getRowArray();
        if ($u) {
            $active = in_array($u['is_active'], [true, 't'], true);
            $db->table('auth_user')->where('id', $id)->update(['is_active' => ! $active]);
            $this->audit('admin.toggle', $u['username'], $active ? 'disabled' : 'activated');
        }

        return redirect()->back()->with('success', 'Status changed.');
    }
}

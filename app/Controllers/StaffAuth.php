<?php

namespace App\Controllers;

use App\Models\AuthUserModel;

/**
 * Employee self-service sign-in. Authenticates against
 * personnel_employee.self_password (Django pbkdf2_sha256 — the same hash
 * format ZKBioTime's own self-service app uses) keyed by emp_code.
 *
 * The portal session is a separate namespace ('staffLoggedIn' / 'staffId')
 * so it never overlaps with the admin panel's ('isLoggedIn' / 'userId').
 */
class StaffAuth extends BaseController
{
    public function login()
    {
        if (session()->get('staffLoggedIn')) {
            return redirect()->to(route_to('portal'));
        }

        return view('portal/login');
    }

    public function attempt()
    {
        if (! $this->validate(['emp_code' => 'required|max_length[50]', 'password' => 'required'])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $code = trim((string) $this->request->getPost('emp_code'));
        $pass = (string) $this->request->getPost('password');
        $bad  = static fn () => redirect()->back()->withInput()->with('error', 'Invalid employee code or password.');

        $emp = db_connect()->query(
            "SELECT id, emp_code, self_password, is_active,
                    NULLIF(TRIM(CONCAT(first_name,' ',last_name)),'') AS name
               FROM personnel_employee WHERE emp_code = ? LIMIT 1",
            [$code]
        )->getRowArray();

        if (! $emp || empty($emp['self_password'])) {
            return $bad();
        }
        if (! in_array($emp['is_active'], [true, 't', '1', 1], true)) {
            return redirect()->back()->withInput()->with('error', 'This employee account is inactive.');
        }
        if (! AuthUserModel::verifyDjangoPassword($pass, (string) $emp['self_password'])) {
            return $bad();
        }

        session()->regenerate();
        session()->set([
            'staffLoggedIn' => true,
            'staffId'       => (int) $emp['id'],
            'staffCode'     => $emp['emp_code'],
            'staffName'     => $emp['name'] ?: $emp['emp_code'],
        ]);

        return redirect()->to(route_to('portal'))->with('success', 'Welcome, ' . esc($emp['name'] ?: $emp['emp_code']) . '.');
    }

    public function logout()
    {
        session()->remove(['staffLoggedIn', 'staffId', 'staffCode', 'staffName']);

        return redirect()->to(route_to('portal.login'))->with('success', 'Signed out.');
    }
}

<?php

namespace App\Controllers;

use App\Models\AuthUserModel;

/**
 * Sign-in against ZKBioTime's own auth_user table — same credentials as the
 * ZKBioTime web console (username + password, Django pbkdf2_sha256).
 */
class Auth extends BaseController
{
    public function index()
    {
        if (session()->get('isLoggedIn')) {
            return redirect()->to('/admin/dashboard');
        }

        return view('auth/login');
    }

    public function attemptLogin()
    {
        if (! $this->validate([
            'username' => 'required|max_length[30]',
            'password' => 'required',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $username = (string) $this->request->getPost('username');
        $password = (string) $this->request->getPost('password');

        $users = new AuthUserModel();
        $user  = $users->findByUsername($username);

        // Uniform message so we don't leak which accounts exist.
        $bad = static fn () => redirect()->back()->withInput()->with('error', 'Invalid username or password.');

        if ($user === null) {
            return $bad();
        }

        if ($users->isLocked($user)) {
            $mins = max(1, (int) ceil((strtotime($user['locked_until']) - time()) / 60));

            return redirect()->back()->withInput()
                ->with('error', "Too many failed attempts. Try again in {$mins} minute(s).");
        }

        if (! AuthUserModel::verifyDjangoPassword($password, (string) $user['password'])) {
            $users->registerFailure((int) $user['id']);
            $this->audit('login.fail', $username);

            return $bad();
        }

        if (! AuthUserModel::isActive($user)) {
            return redirect()->back()->withInput()->with('error', 'This account is disabled.');
        }

        session()->regenerate();
        $sessionKey = session_id() ?: bin2hex(random_bytes(16));
        $role       = AuthUserModel::roleOf($user);
        $name       = AuthUserModel::displayName($user);

        session()->set([
            'isLoggedIn' => true,
            'userId'     => (int) $user['id'],       // a real auth_user id — audit rows attribute correctly
            'userName'   => $name,
            'userEmail'  => $user['email'] ?: ($user['username'] . '@zkbiotime'),
            'userRole'   => $role,
        ]);

        $users->touchLogin((int) $user['id'], $this->request->getIPAddress(), $sessionKey);
        $this->audit('login', $username);

        return redirect()->to('/admin/dashboard')->with('success', 'Welcome back, ' . esc($name) . '!');
    }

    public function logout()
    {
        $this->audit('logout', (string) session()->get('userName'));
        session()->destroy();

        return redirect()->to('/login')->with('success', 'You have been signed out.');
    }
}

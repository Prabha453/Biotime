<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Role gate for the admin panel.
 *
 *   admin   – everything
 *   manager – everything except the "System" group (admins / settings / system-log writes)
 *   viewer  – read-only: every non-GET request is blocked
 *
 * Usage in Routes.php:
 *   ['filter' => 'role']            → viewer may GET, manager+admin may write
 *   ['filter' => 'role:admin']      → only admin (any method)
 *   ['filter' => 'role:manager']    → manager or admin (any method)
 */
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $role = (string) (session()->get('userRole') ?: 'viewer');

        // explicit role requirement on the route
        if (! empty($arguments)) {
            $allowed = $arguments;               // e.g. ['manager'] means manager|admin
            if (in_array('manager', $allowed, true)) {
                $allowed[] = 'admin';
            }
            if (! in_array($role, $allowed, true)) {
                return $this->deny($request);
            }

            return;
        }

        // default: viewers can read but not write
        if ($role === 'viewer' && strtoupper($request->getMethod()) !== 'GET') {
            return $this->deny($request);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }

    private function deny(RequestInterface $request)
    {
        if ($request->isAJAX()) {
            return service('response')->setStatusCode(403)->setJSON(['error' => 'Forbidden']);
        }

        return redirect()->to(previous_url() ?: '/admin/dashboard')
            ->with('error', 'You do not have permission to do that.');
    }
}

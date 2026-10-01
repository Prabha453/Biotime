<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Blocks access to the admin panel for guests.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        if (! $session->get('isLoggedIn')) {
            $session->setFlashdata('error', 'Please sign in to continue.');

            return redirect()->to('/login');
        }

        // base_securitypolicy.session_timeout (minutes) — an idle admin
        // session is force-logged-out rather than just relying on the
        // framework's own fixed cookie lifetime.
        $timeoutMin = (int) (db_connect()->table('base_securitypolicy')->select('session_timeout')
            ->orderBy('id')->get(1)->getRow()->session_timeout ?? 0);
        $last = $session->get('lastActivity');
        if ($timeoutMin > 0 && $last && (time() - $last) > $timeoutMin * 60) {
            $session->destroy();
            $session->setFlashdata('error', 'Your session timed out after ' . $timeoutMin . ' minutes of inactivity — please sign in again.');

            return redirect()->to('/login');
        }
        $session->set('lastActivity', time());
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }
}

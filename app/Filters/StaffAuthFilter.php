<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Guards the employee self-service portal. Its session is a separate
 * namespace ('staffLoggedIn' / 'staffId') from the admin panel, so an
 * employee sign-in never grants admin access and vice-versa.
 */
class StaffAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        if (! $session->get('staffLoggedIn')) {
            $session->setFlashdata('error', 'Please sign in to your staff account.');

            return redirect()->to('/portal/login');
        }

        // base_securitypolicy.session_timeout (minutes) — shared system-wide
        // idle timeout, tracked separately from the admin panel's own.
        $timeoutMin = (int) (db_connect()->table('base_securitypolicy')->select('session_timeout')
            ->orderBy('id')->get(1)->getRow()->session_timeout ?? 0);
        $last = $session->get('staffLastActivity');
        if ($timeoutMin > 0 && $last && (time() - $last) > $timeoutMin * 60) {
            $session->destroy();
            $session->setFlashdata('error', 'Your session timed out after ' . $timeoutMin . ' minutes of inactivity — please sign in again.');

            return redirect()->to('/portal/login');
        }
        $session->set('staffLastActivity', time());
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // no-op
    }
}

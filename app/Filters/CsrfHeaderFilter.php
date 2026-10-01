<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Exposes the current CSRF hash on every response as `X-CSRF-TOKEN` so the
 * AJAX helper (public/assets/js/tt.js) can keep its cached token fresh even
 * though CI4 rotates the hash after each validated request
 * (Config\Security::$regenerate = true).
 */
class CsrfHeaderFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // no-op
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (function_exists('csrf_hash')) {
            $response->setHeader('X-CSRF-TOKEN', csrf_hash());
        }
    }
}

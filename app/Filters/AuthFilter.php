<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Auth;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $authHeader = $request->getHeaderLine('Authorization');
        $token = null;

        if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
        }

        if (!$token) {
            $token = $request->getCookie('token');
        }

        if (!$token) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['error' => 'Unauthorized']);
        }

        try {
            $config = config(Auth::class);
            $decoded = JWT::decode($token, new Key($config->jwtSecret, $config->jwtAlgorithm));
            $request->userId = $decoded->sub;
            $request->userEmail = $decoded->email ?? null;
        } catch (\Exception $e) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['error' => 'Invalid or expired token']);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}

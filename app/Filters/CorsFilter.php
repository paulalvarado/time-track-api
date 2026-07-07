<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Cors;

class CorsFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $config = config(Cors::class);
        $origin = $request->getHeaderLine('Origin');

        if ($origin) {
            $allowed = false;
            foreach ($config->allowedOrigins as $allowedOrigin) {
                if ($origin === $allowedOrigin) {
                    $allowed = true;
                    break;
                }
            }

            if ($allowed) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Allow-Headers: ' . implode(', ', $config->allowedHeaders));
                header('Access-Control-Allow-Methods: ' . implode(', ', $config->allowedMethods));
                header('Access-Control-Max-Age: ' . $config->maxAge);
            }
        }

        if ($request->getMethod() === 'options') {
            $response = service('response');
            $response->setStatusCode(204);
            $response->setHeader('Access-Control-Allow-Origin', $origin ?: '*');
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
            $response->setHeader('Access-Control-Allow-Headers', implode(', ', $config->allowedHeaders));
            $response->setHeader('Access-Control-Allow-Methods', implode(', ', $config->allowedMethods));
            $response->setHeader('Access-Control-Max-Age', (string) $config->maxAge);
            return $response;
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $config = config(Cors::class);
        $origin = $request->getHeaderLine('Origin');

        if ($origin) {
            $allowed = false;
            foreach ($config->allowedOrigins as $allowedOrigin) {
                if ($origin === $allowedOrigin) {
                    $allowed = true;
                    break;
                }
            }
            if ($allowed) {
                $response->setHeader('Access-Control-Allow-Origin', $origin);
                $response->setHeader('Access-Control-Allow-Credentials', 'true');
            }
        }
    }
}

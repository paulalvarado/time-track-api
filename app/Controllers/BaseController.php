<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

class BaseController extends Controller
{
    protected function respondSuccess($data = null, string $message = 'OK', int $code = 200): ResponseInterface
    {
        $response = $data !== null ? $data : ['ok' => true];
        return $this->response->setStatusCode($code)->setJSON($response);
    }

    protected function respondError(string $message, int $code = 400): ResponseInterface
    {
        return $this->response->setStatusCode($code)->setJSON(['error' => $message]);
    }

    protected function respondUnauthorized(): ResponseInterface
    {
        return $this->response->setStatusCode(401)->setJSON(['error' => 'Unauthorized']);
    }

    protected function respondNotFound(string $message = 'Not found'): ResponseInterface
    {
        return $this->response->setStatusCode(404)->setJSON(['error' => $message]);
    }

    protected function getUserId(): ?string
    {
        return $this->request->userId ?? null;
    }

    protected function getJsonInput(): array
    {
        $json = $this->request->getJSON(true);
        return $json ?? [];
    }
}

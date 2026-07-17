<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\OdooConfigModel;
use App\Models\RoleModel;
use App\Models\UserRoleModel;
use Config\Auth;
use Firebase\JWT\JWT;

class AuthController extends BaseController
{
    public function register()
    {
        $data = $this->getJsonInput();
        $email = $data['email'] ?? '';
        $name = $data['name'] ?? '';
        $password = $data['password'] ?? '';

        if (!$email || !$name || !$password) {
            return $this->respondError('Email, name, and password are required');
        }
        if (strlen($password) < 6) {
            return $this->respondError('Password must be at least 6 characters');
        }

        $userModel = new UserModel();
        $existing = $userModel->findByEmail($email);
        if ($existing) {
            return $this->respondError('Email already registered', 409);
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $user = $userModel->createUser([
            'email' => $email,
            'name' => $name,
            'password' => $passwordHash,
        ]);

        // Asignar rol por defecto "user"
        $roleModel = new RoleModel();
        $defaultRole = $roleModel->findByName('user');
        if ($defaultRole) {
            $userRoleModel = new UserRoleModel();
            $userRoleModel->assignRole($user->id, $defaultRole->id);
        }

        $token = $this->generateToken($user->id, $user->email);
        $this->setTokenCookie($token);

        $userRoleModel = new \App\Models\UserRoleModel();
        $isAdmin = $userRoleModel->isAdmin($user->id);
        $permissions = $userRoleModel->getPermissionsForUser($user->id);

        return $this->response
            ->setStatusCode(201)
            ->setJSON([
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'isAdmin' => $isAdmin,
                    'permissions' => $permissions,
                ],
            ]);
    }

    public function login()
    {
        $data = $this->getJsonInput();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            return $this->respondError('Email and password are required');
        }

        $userModel = new UserModel();
        $user = $userModel->findByEmail($email);
        if (!$user || !$user->password) {
            return $this->respondError('Invalid email or password', 401);
        }

        if (!password_verify($password, $user->password)) {
            return $this->respondError('Invalid email or password', 401);
        }

        $token = $this->generateToken($user->id, $user->email);
        $this->setTokenCookie($token);

        $userRoleModel = new \App\Models\UserRoleModel();
        $isAdmin = $userRoleModel->isAdmin($user->id);
        $permissions = $userRoleModel->getPermissionsForUser($user->id);

        return $this->response
            ->setStatusCode(200)
            ->setJSON([
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'isAdmin' => $isAdmin,
                    'permissions' => $permissions,
                ],
            ]);
    }

    public function logout()
    {
        $this->response->deleteCookie('token');
        return $this->respondSuccess(['ok' => true]);
    }

    public function me()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $userModel = new UserModel();
        $user = $userModel->findById($userId);
        if (!$user) {
            return $this->respondNotFound('User not found');
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);

        // Usar permisos ya cargados por AuthFilter — evita queries redundantes
        $isAdmin = $this->request->isAdmin ?? false;
        $permissions = $this->request->userPermissions ?? [];

        return $this->respondSuccess([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'hasOdooConfig' => $config !== null,
                'isAdmin' => $isAdmin,
                'permissions' => $permissions,
            ],
        ]);
    }

    public function updateProfile()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $data = $this->getJsonInput();
        $name = $data['name'] ?? null;
        $currentPassword = $data['currentPassword'] ?? null;
        $newPassword = $data['newPassword'] ?? null;

        $userModel = new UserModel();
        $user = $userModel->findById($userId);
        if (!$user) {
            return $this->respondNotFound('User not found');
        }

        if ($currentPassword || $newPassword) {
            if (!$currentPassword || !$newPassword) {
                return $this->respondError('Both currentPassword and newPassword are required to change password');
            }
            if (strlen($newPassword) < 6) {
                return $this->respondError('New password must be at least 6 characters');
            }
            if (!password_verify($currentPassword, $user->password)) {
                return $this->respondError('Current password is incorrect', 401);
            }
            $userModel->updatePassword($userId, password_hash($newPassword, PASSWORD_BCRYPT));
        }

        if ($name && $name !== $user->name) {
            $userModel->updateName($userId, $name);
        }

        $updated = $userModel->findById($userId);
        return $this->respondSuccess([
            'user' => ['id' => $updated->id, 'email' => $updated->email, 'name' => $updated->name],
        ]);
    }

    private function generateToken(string $userId, string $email): string
    {
        $authConfig = config(Auth::class);
        $now = time();
        $payload = [
            'sub' => $userId,
            'email' => $email,
            'iat' => $now,
            'exp' => $now + $authConfig->sessionExpiresSeconds,
        ];
        return JWT::encode($payload, $authConfig->jwtSecret, $authConfig->jwtAlgorithm);
    }

    private function setTokenCookie(string $token): void
    {
        $authConfig = config(Auth::class);
        $this->response->setCookie('token', $token, $authConfig->sessionExpiresSeconds, '', '/', '', null, true);
    }
}

<?php

namespace App\Controllers;

use App\Models\UserMetadataModel;

class UserMetadataController extends BaseController
{
    /**
     * GET /api/user/metadata
     * Retorna toda la metadata del usuario autenticado.
     */
    public function index()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $model = new UserMetadataModel();
        $metadata = $model->getAllForUser($userId);

        return $this->respondSuccess(['metadata' => $metadata]);
    }

    /**
     * GET /api/user/metadata/(:any)
     * Retorna el valor de una clave específica.
     */
    public function get(string $key)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $model = new UserMetadataModel();
        $value = $model->getByKey($userId, $key);

        return $this->respondSuccess(['value' => $value]);
    }

    /**
     * PUT /api/user/metadata/(:any)
     * Crea o actualiza una clave de metadata.
     * Body: { "value": ... }
     */
    public function set(string $key)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $data = $this->getJsonInput();
        $value = $data['value'] ?? null;

        $model = new UserMetadataModel();
        $model->saveMetadata($userId, $key, $value);

        return $this->respondSuccess(['ok' => true]);
    }

    /**
     * DELETE /api/user/metadata/(:any)
     * Elimina una clave de metadata.
     */
    public function delete(string $key)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $model = new UserMetadataModel();
        $deleted = $model->deleteByKey($userId, $key);

        if (!$deleted) {
            return $this->respondNotFound('Metadata key not found');
        }

        return $this->respondSuccess(['ok' => true]);
    }
}

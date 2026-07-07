<?php

namespace App\Controllers;

use App\Models\OdooConfigModel;
use App\Models\CatalogModel;
use App\Models\CatalogItemModel;

class CatalogController extends BaseController
{
    public function list()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondSuccess(['catalogs' => []]);
        }

        $catalogModel = new CatalogModel();
        $catalogs = $catalogModel->where('odooConfigId', $config->id)->findAll();

        $itemModel = new CatalogItemModel();
        $result = [];

        foreach ($catalogs as $catalog) {
            $items = $itemModel->findByCatalogId($catalog->id);
            $result[] = [
                'id' => $catalog->id,
                'name' => $catalog->name,
                'lastSyncAt' => $catalog->lastSyncAt,
                'items' => array_map(function ($item) {
                    return [
                        'id' => $item->id,
                        'key' => $item->key,
                        'value' => $item->value,
                        'extra' => $item->extra ? json_decode($item->extra, true) : null,
                    ];
                }, $items),
            ];
        }

        return $this->respondSuccess(['catalogs' => $result]);
    }

    public function getByName($name)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->respondUnauthorized();
        }

        $configModel = new OdooConfigModel();
        $config = $configModel->findByUserId($userId);
        if (!$config) {
            return $this->respondSuccess(['catalog' => ['name' => $name, 'items' => []]]);
        }

        $catalogModel = new CatalogModel();
        $catalog = $catalogModel->findByName($name, $config->id);
        if (!$catalog) {
            return $this->respondNotFound("Catalog '{$name}' not available. Run sync-catalogs first.");
        }

        $itemModel = new CatalogItemModel();
        $items = $itemModel->findByCatalogId($catalog->id);

        return $this->respondSuccess([
            'catalog' => [
                'name' => $catalog->name,
                'items' => array_map(function ($item) {
                    return [
                        'id' => $item->id,
                        'key' => $item->key,
                        'value' => $item->value,
                        'extra' => $item->extra ? json_decode($item->extra, true) : null,
                    ];
                }, $items),
            ],
        ]);
    }
}

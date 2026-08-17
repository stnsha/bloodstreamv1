<?php

namespace App\Http\Controllers\API\Catalog;

use App\Models\MasterPanelItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterPanelItemController extends BaseCatalogController
{
    protected function model(): string
    {
        return MasterPanelItem::class;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedIndex($request);
    }

    public function show($id): JsonResponse
    {
        return $this->findById($id);
    }
}

<?php

namespace App\Http\Controllers\API\Catalog;

use App\Models\PanelItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanelItemController extends BaseCatalogController
{
    protected function model(): string
    {
        return PanelItem::class;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedIndex($request);
    }

    public function show($id): JsonResponse
    {
        return $this->findById($id);
    }

    public function byMasterPanelItem(Request $request, $masterPanelItemId): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('master_panel_item_id', $masterPanelItemId));
    }

    public function byLab(Request $request, $labId): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('lab_id', $labId));
    }
}

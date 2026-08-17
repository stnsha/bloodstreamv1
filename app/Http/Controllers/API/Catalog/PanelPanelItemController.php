<?php

namespace App\Http\Controllers\API\Catalog;

use App\Models\PanelPanelItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanelPanelItemController extends BaseCatalogController
{
    protected function model(): string
    {
        return PanelPanelItem::class;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedIndex($request);
    }

    public function show($id): JsonResponse
    {
        return $this->findById($id);
    }

    public function byPanel(Request $request, $panelId): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('panel_id', $panelId));
    }

    public function byPanelItem(Request $request, $panelItemId): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('panel_item_id', $panelItemId));
    }
}

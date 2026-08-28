<?php

namespace App\Http\Controllers\API\Catalog;

use App\Models\PanelInterpretation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanelInterpretationController extends BaseCatalogController
{
    protected function model(): string
    {
        return PanelInterpretation::class;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedIndex($request);
    }

    public function show($id): JsonResponse
    {
        return $this->findById($id);
    }

    public function byPanelPanelItem(Request $request, $panelPanelItemId): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('panel_panel_item_id', $panelPanelItemId));
    }
}

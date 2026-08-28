<?php

namespace App\Http\Controllers\API\Catalog;

use App\Models\Panel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanelController extends BaseCatalogController
{
    protected function model(): string
    {
        return Panel::class;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedIndex($request);
    }

    public function show($id): JsonResponse
    {
        return $this->findById($id);
    }

    public function byMasterPanel(Request $request, $masterPanelId): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('master_panel_id', $masterPanelId));
    }

    public function byPanelCategory(Request $request, $panelCategoryId): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('panel_category_id', $panelCategoryId));
    }

    public function byCode(Request $request, $code): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('code', $code));
    }
}

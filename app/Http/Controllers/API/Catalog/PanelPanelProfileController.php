<?php

namespace App\Http\Controllers\API\Catalog;

use App\Models\PanelPanelProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanelPanelProfileController extends BaseCatalogController
{
    protected function model(): string
    {
        return PanelPanelProfile::class;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedIndex($request);
    }

    public function show($id): JsonResponse
    {
        return $this->findById($id);
    }

    public function byPanelProfile(Request $request, $panelProfileId): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('panel_profile_id', $panelProfileId));
    }

    public function byPanel(Request $request, $panelId): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('panel_id', $panelId));
    }
}

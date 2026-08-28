<?php

namespace App\Http\Controllers\API\Catalog;

use App\Models\PanelProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanelProfileController extends BaseCatalogController
{
    protected function model(): string
    {
        return PanelProfile::class;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedIndex($request);
    }

    public function show($id): JsonResponse
    {
        return $this->findById($id);
    }

    public function byLab(Request $request, $labId): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('lab_id', $labId));
    }
}

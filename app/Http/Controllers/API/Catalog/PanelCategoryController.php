<?php

namespace App\Http\Controllers\API\Catalog;

use App\Models\PanelCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanelCategoryController extends BaseCatalogController
{
    protected function model(): string
    {
        return PanelCategory::class;
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

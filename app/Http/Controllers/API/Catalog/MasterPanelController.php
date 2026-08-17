<?php

namespace App\Http\Controllers\API\Catalog;

use App\Models\MasterPanel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterPanelController extends BaseCatalogController
{
    protected function model(): string
    {
        return MasterPanel::class;
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

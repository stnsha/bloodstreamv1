<?php

namespace App\Http\Controllers\API\Catalog;

use App\Models\MasterPanelComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterPanelCommentController extends BaseCatalogController
{
    protected function model(): string
    {
        return MasterPanelComment::class;
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

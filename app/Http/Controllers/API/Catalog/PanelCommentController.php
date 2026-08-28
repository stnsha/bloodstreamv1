<?php

namespace App\Http\Controllers\API\Catalog;

use App\Models\PanelComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanelCommentController extends BaseCatalogController
{
    protected function model(): string
    {
        return PanelComment::class;
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

    public function byMasterPanelComment(Request $request, $masterPanelCommentId): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('master_panel_comment_id', $masterPanelCommentId));
    }
}

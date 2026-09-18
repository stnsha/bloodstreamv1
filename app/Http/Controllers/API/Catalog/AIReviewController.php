<?php

namespace App\Http\Controllers\API\Catalog;

use App\Models\AIReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIReviewController extends BaseCatalogController
{
    protected function model(): string
    {
        return AIReview::class;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedIndex($request);
    }

    public function show($id): JsonResponse
    {
        return $this->findById($id);
    }

    public function byTestResult(Request $request, $testResultId): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('test_result_id', $testResultId));
    }
}

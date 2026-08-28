<?php

namespace App\Http\Controllers\API\Catalog;

use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientController extends BaseCatalogController
{
    protected function model(): string
    {
        return Patient::class;
    }

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedIndex($request);
    }

    public function show($id): JsonResponse
    {
        return $this->findById($id);
    }

    public function byGender(Request $request, $gender): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('gender', $gender));
    }

    public function byAge(Request $request, $age): JsonResponse
    {
        return $this->paginatedIndex($request, fn ($q) => $q->where('age', $age));
    }
}

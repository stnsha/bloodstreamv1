<?php

namespace App\Http\Controllers\API\Lab;

use App\Http\Controllers\Controller;
use App\Http\Requests\LabRequest;
use App\Models\Lab;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LabController extends Controller
{
    public function index()
    {
        Log::info('LabController@index: fetching all labs');

        $labs = Lab::orderBy('name')->get();

        Log::info('LabController@index: completed', ['count' => $labs->count()]);

        return response()->json(['success' => true, 'data' => $labs], 200);
    }

    public function store(LabRequest $request)
    {
        Log::info('LabController@store: creating lab', ['data' => $request->validated()]);

        try {
            DB::beginTransaction();

            $lab = Lab::create($request->validated());

            DB::commit();

            Log::info('LabController@store: lab created', ['id' => $lab->id, 'name' => $lab->name]);

            return response()->json(['success' => true, 'data' => $lab], 201);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('LabController@store: failed', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        Log::info('LabController@show: fetching lab', ['id' => $id]);

        $lab = Lab::find($id);

        if (!$lab) {
            Log::warning('LabController@show: lab not found', ['id' => $id]);

            return response()->json(['success' => false, 'message' => 'Lab not found.'], 404);
        }

        Log::info('LabController@show: completed', ['id' => $id]);

        return response()->json(['success' => true, 'data' => $lab], 200);
    }

    public function update(LabRequest $request, $id)
    {
        Log::info('LabController@update: updating lab', ['id' => $id, 'data' => $request->validated()]);

        try {
            DB::beginTransaction();

            $lab = Lab::find($id);

            if (!$lab) {
                DB::rollBack();

                Log::warning('LabController@update: lab not found', ['id' => $id]);

                return response()->json(['success' => false, 'message' => 'Lab not found.'], 404);
            }

            $lab->update($request->validated());

            DB::commit();

            Log::info('LabController@update: lab updated', ['id' => $lab->id]);

            return response()->json(['success' => true, 'data' => $lab], 200);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('LabController@update: failed', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        Log::info('LabController@destroy: deleting lab', ['id' => $id]);

        try {
            DB::beginTransaction();

            $lab = Lab::find($id);

            if (!$lab) {
                DB::rollBack();

                Log::warning('LabController@destroy: lab not found', ['id' => $id]);

                return response()->json(['success' => false, 'message' => 'Lab not found.'], 404);
            }

            $lab->delete();

            DB::commit();

            Log::info('LabController@destroy: lab deleted', ['id' => $id]);

            return response()->json(['success' => true, 'message' => 'Lab deleted.'], 200);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('LabController@destroy: failed', ['id' => $id, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}

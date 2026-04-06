<?php

namespace App\Http\Controllers\API\ConsultCall;

use App\Http\Controllers\Controller;
use App\Http\Controllers\API\PDFController;
use App\Models\ConsultCall;
use App\Models\ConsultCallDetails;
use App\Models\ConsultCallFollowUp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ConsultCallController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Log::info('ConsultCall index: listing consult calls', [
            'filters' => $request->only([
                'patient_id', 'outlet_id', 'consent_call_status', 'scheduled_status',
                'date_from', 'date_to', 'search', 'enrollment_type',
                'process_status', 'followup_reminder', 'scheduled_from', 'scheduled_to',
            ]),
        ]);

        $query = ConsultCall::with(['patient', 'details.clinicalCondition', 'details.testResult', 'followUps']);

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->input('patient_id'));
        }

        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', $request->input('outlet_id'));
        }

        if ($request->filled('consent_call_status')) {
            $query->where('consent_call_status', $request->input('consent_call_status'));
        }

        if ($request->filled('scheduled_status')) {
            $query->where('scheduled_status', $request->input('scheduled_status'));
        }

        if ($request->filled('date_from')) {
            $query->where('enrollment_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('enrollment_date', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('patient', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('icno', 'LIKE', "%{$search}%")
                    ->orWhere('tel', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('enrollment_type')) {
            $query->where('enrollment_type', $request->input('enrollment_type'));
        }

        if ($request->filled('process_status')) {
            $processStatus = $request->input('process_status');
            $query->whereHas('details', function ($q) use ($processStatus) {
                $q->where('process_status', $processStatus);
            });
        }

        if ($request->filled('followup_reminder')) {
            $followupReminder = $request->input('followup_reminder');
            $query->whereHas('followUps', function ($q) use ($followupReminder) {
                $q->where('followup_reminder', $followupReminder);
            });
        }

        if ($request->filled('scheduled_from')) {
            $query->where('scheduled_call_date', '>=', $request->input('scheduled_from'));
        }

        if ($request->filled('scheduled_to')) {
            $query->where('scheduled_call_date', '<=', $request->input('scheduled_to'));
        }

        if ($request->filled('consulted_by')) {
            $consultedBy = $request->input('consulted_by');
            $query->whereHas('details', function ($q) use ($consultedBy) {
                $q->where('consulted_by', $consultedBy);
            });
        }

        $perPage = $request->input('per_page', 15);
        $data = $query->orderByRaw("
            (consent_call_status = 2 OR COALESCE((
                SELECT d.process_status = 3
                FROM consult_call_details d
                WHERE d.consult_call_id = consult_calls.id
                  AND d.deleted_at IS NULL
                ORDER BY d.id DESC
                LIMIT 1
            ), 0)) ASC,
            scheduled_call_date IS NULL ASC,
            (scheduled_call_date < CURDATE()) ASC,
            scheduled_call_date ASC,
            COALESCE((
                SELECT d.action = 1
                FROM consult_call_details d
                WHERE d.consult_call_id = consult_calls.id
                  AND d.deleted_at IS NULL
                ORDER BY d.id DESC
                LIMIT 1
            ), 0) DESC
        ")->paginate($perPage);

        Log::info('ConsultCall index: completed', ['total' => $data->total()]);

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Consult calls retrieved successfully.',
        ]);
    }

    public function summary(): JsonResponse
    {
        Log::info('ConsultCall summary: retrieving dashboard summary');

        try {
            $baseStats = ConsultCall::selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN enrollment_type = 1 THEN 1 ELSE 0 END) as enrollment_primary,
                SUM(CASE WHEN enrollment_type = 2 THEN 1 ELSE 0 END) as enrollment_follow_up,
                SUM(CASE WHEN consent_call_status = 0 THEN 1 ELSE 0 END) as consent_pending,
                SUM(CASE WHEN consent_call_status = 1 THEN 1 ELSE 0 END) as consent_obtained,
                SUM(CASE WHEN consent_call_status = 2 THEN 1 ELSE 0 END) as consent_refused
            ")->first();

            $processStatusCounts = DB::table('consult_call_details as ccd')
                ->joinSub(
                    DB::table('consult_call_details')
                        ->selectRaw('MAX(id) as max_id')
                        ->whereNull('deleted_at')
                        ->groupBy('consult_call_id'),
                    'latest',
                    'ccd.id',
                    '=',
                    'latest.max_id'
                )
                ->selectRaw("
                    SUM(CASE WHEN ccd.process_status = 1 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN ccd.process_status = 2 THEN 1 ELSE 0 END) as escalated,
                    SUM(CASE WHEN ccd.process_status = 3 THEN 1 ELSE 0 END) as closed
                ")
                ->first();

            $followupReminderCounts = DB::table('consult_call_follow_ups as ccf')
                ->joinSub(
                    DB::table('consult_call_follow_ups')
                        ->selectRaw('MAX(id) as max_id')
                        ->whereNull('deleted_at')
                        ->groupBy('consult_call_id'),
                    'latest',
                    'ccf.id',
                    '=',
                    'latest.max_id'
                )
                ->selectRaw("
                    SUM(CASE WHEN ccf.followup_reminder = 0 THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN ccf.followup_reminder = 1 THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN ccf.followup_reminder = 2 THEN 1 ELSE 0 END) as rescheduled,
                    SUM(CASE WHEN ccf.followup_reminder = 3 THEN 1 ELSE 0 END) as cancelled
                ")
                ->first();

            $data = [
                'total' => (int) $baseStats->total,
                'enrollment_type' => [
                    'primary' => (int) $baseStats->enrollment_primary,
                    'follow_up' => (int) $baseStats->enrollment_follow_up,
                ],
                'consent_call_status' => [
                    'pending' => (int) $baseStats->consent_pending,
                    'obtained' => (int) $baseStats->consent_obtained,
                    'refused' => (int) $baseStats->consent_refused,
                ],
                'process_status' => [
                    'active' => (int) ($processStatusCounts->active ?? 0),
                    'escalated' => (int) ($processStatusCounts->escalated ?? 0),
                    'closed' => (int) ($processStatusCounts->closed ?? 0),
                ],
                'followup_reminder' => [
                    'pending' => (int) ($followupReminderCounts->pending ?? 0),
                    'completed' => (int) ($followupReminderCounts->completed ?? 0),
                    'rescheduled' => (int) ($followupReminderCounts->rescheduled ?? 0),
                    'cancelled' => (int) ($followupReminderCounts->cancelled ?? 0),
                ],
            ];

            Log::info('ConsultCall summary: completed', ['total' => $data['total']]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Summary retrieved successfully.',
            ]);
        } catch (Throwable $e) {
            Log::error('ConsultCall summary: failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve summary.',
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        Log::info('ConsultCall show: retrieving consult call', ['id' => $id]);

        $consultCall = ConsultCall::with(['patient', 'details.clinicalCondition', 'details.testResult', 'followUps'])
            ->find($id);

        if (!$consultCall) {
            Log::warning('ConsultCall show: not found', ['id' => $id]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Consult call not found.',
            ], 404);
        }

        Log::info('ConsultCall show: completed', ['id' => $id]);

        return response()->json([
            'success' => true,
            'data' => $consultCall,
            'message' => 'Consult call retrieved successfully.',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Log::info('ConsultCall store: creating new consult call', [
            'patient_id' => $request->input('patient_id'),
        ]);

        $validator = Validator::make($request->all(), [
            'patient_id' => 'required|integer|exists:patients,id',
            'customer_id' => 'nullable|integer',
            'outlet_id' => 'nullable|integer',
            'is_eligible' => 'nullable|boolean',
            'enrollment_date' => 'required|date',
            'enrollment_type' => 'nullable|integer|in:1,2',
            'consent_call_status' => 'nullable|integer|in:0,1,2',
            'consent_call_date' => 'nullable|date',
            'scheduled_status' => 'nullable|integer|in:0,1,2,3',
            'scheduled_call_date' => 'nullable|date',
            'updated_scheduled_date' => 'nullable|date',
            'handled_by' => 'nullable|integer',
            'mode_of_consultation' => 'nullable|integer|in:0,1,2,3',
            'closure_date' => 'nullable|date',
            'final_remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => $validator->errors(),
                'message' => 'Validation failed.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $consultCall = ConsultCall::create($validator->validated());

            DB::commit();

            Log::info('ConsultCall store: created successfully', ['id' => $consultCall->id]);

            return response()->json([
                'success' => true,
                'data' => $consultCall->load(['patient', 'details.clinicalCondition', 'details.testResult', 'followUps']),
                'message' => 'Consult call created successfully.',
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('ConsultCall store: failed', [
                'error' => $e->getMessage(),
                'patient_id' => $request->input('patient_id'),
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to create consult call.',
            ], 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        Log::info('ConsultCall update: updating consult call', ['id' => $id]);

        $consultCall = ConsultCall::find($id);

        if (!$consultCall) {
            Log::warning('ConsultCall update: not found', ['id' => $id]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Consult call not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'patient_id' => 'sometimes|integer|exists:patients,id',
            'customer_id' => 'nullable|integer',
            'outlet_id' => 'nullable|integer',
            'is_eligible' => 'nullable|boolean',
            'enrollment_date' => 'sometimes|date',
            'enrollment_type' => 'nullable|integer|in:1,2',
            'consent_call_status' => 'nullable|integer|in:0,1,2',
            'consent_call_date' => 'nullable|date',
            'scheduled_status' => 'nullable|integer|in:0,1,2,3',
            'scheduled_call_date' => 'nullable|date',
            'updated_scheduled_date' => 'nullable|date',
            'handled_by' => 'nullable|integer',
            'mode_of_consultation' => 'nullable|integer|in:0,1,2,3',
            'closure_date' => 'nullable|date',
            'final_remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => $validator->errors(),
                'message' => 'Validation failed.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $consultCall->update($validator->validated());

            DB::commit();

            Log::info('ConsultCall update: updated successfully', ['id' => $id]);

            return response()->json([
                'success' => true,
                'data' => $consultCall->fresh(['patient', 'details.clinicalCondition', 'details.testResult', 'followUps']),
                'message' => 'Consult call updated successfully.',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('ConsultCall update: failed', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to update consult call.',
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        Log::info('ConsultCall destroy: deleting consult call', ['id' => $id]);

        $consultCall = ConsultCall::find($id);

        if (!$consultCall) {
            Log::warning('ConsultCall destroy: not found', ['id' => $id]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Consult call not found.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $consultCall->delete();

            DB::commit();

            Log::info('ConsultCall destroy: deleted successfully', ['id' => $id]);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Consult call deleted successfully.',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('ConsultCall destroy: failed', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to delete consult call.',
            ], 500);
        }
    }

    // ──────────────────────────────────────────────
    // Details sub-resource
    // ──────────────────────────────────────────────

    public function storeDetails(Request $request, int $id): JsonResponse
    {
        Log::info('ConsultCall storeDetails: creating detail', ['consult_call_id' => $id]);

        $consultCall = ConsultCall::find($id);

        if (!$consultCall) {
            Log::warning('ConsultCall storeDetails: consult call not found', ['id' => $id]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Consult call not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'clinical_condition_id' => 'nullable|integer|exists:clinical_conditions,id',
            'test_result_id' => 'nullable|integer|exists:test_results,id',
            'documentation' => 'required|string',
            'diagnosis' => 'nullable|string',
            'treatment_plan' => 'nullable|string',
            'rx_issued' => 'nullable|boolean',
            'action' => 'nullable|integer|in:1,2,3',
            'consult_status' => 'nullable|integer|in:0,1,2,3',
            'process_status' => 'nullable|integer|in:1,2,3',
            'consulted_by' => 'nullable|integer',
            'consult_date' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => $validator->errors(),
                'message' => 'Validation failed.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $detailData = $validator->validated();

            // Auto-set process_status: End Process action forces Closed; default to Active
            if (isset($detailData['action']) && $detailData['action'] === ConsultCallDetails::ACTION_END_PROCESS) {
                $detailData['process_status'] = ConsultCallDetails::PROCESS_STATUS_CLOSED;
            } elseif (!isset($detailData['process_status'])) {
                $detailData['process_status'] = ConsultCallDetails::PROCESS_STATUS_ACTIVE;
            }

            $detail = $consultCall->details()->create($detailData);

            DB::commit();

            Log::info('ConsultCall storeDetails: created successfully', [
                'consult_call_id' => $id,
                'detail_id' => $detail->id,
                'process_status' => $detail->process_status,
            ]);

            return response()->json([
                'success' => true,
                'data' => $detail->load(['clinicalCondition', 'testResult']),
                'message' => 'Consult call detail created successfully.',
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('ConsultCall storeDetails: failed', [
                'error' => $e->getMessage(),
                'consult_call_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to create consult call detail.',
            ], 500);
        }
    }

    public function updateDetails(Request $request, int $id, int $detailId): JsonResponse
    {
        Log::info('ConsultCall updateDetails: updating detail', [
            'consult_call_id' => $id,
            'detail_id' => $detailId,
        ]);

        $detail = ConsultCallDetails::where('consult_call_id', $id)->find($detailId);

        if (!$detail) {
            Log::warning('ConsultCall updateDetails: detail not found', [
                'consult_call_id' => $id,
                'detail_id' => $detailId,
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Consult call detail not found.',
            ], 404);
        }

        // Once consulted_by is set, only that doctor may update this detail record
        if ($detail->consulted_by && $detail->consulted_by !== (int) $request->attributes->get('staff_id')) {
            Log::warning('ConsultCall updateDetails: forbidden — staff_id does not match consulted_by', [
                'consult_call_id' => $id,
                'detail_id' => $detailId,
                'consulted_by' => $detail->consulted_by,
                'staff_id' => $request->attributes->get('staff_id'),
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Only the assigned doctor can update this consultation detail.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'clinical_condition_id' => 'nullable|integer|exists:clinical_conditions,id',
            'test_result_id' => 'nullable|integer|exists:test_results,id',
            'documentation' => 'sometimes|nullable|string',
            'diagnosis' => 'nullable|string',
            'treatment_plan' => 'nullable|string',
            'rx_issued' => 'nullable|boolean',
            'action' => 'nullable|integer|in:1,2,3',
            'consult_status' => 'nullable|integer|in:0,1,2,3',
            'process_status' => 'nullable|integer|in:1,2,3',
            'consulted_by' => 'nullable|integer',
            'consult_date' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => $validator->errors(),
                'message' => 'Validation failed.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $detailData = $validator->validated();

            // Auto-set process_status: End Process action forces Closed; default to Active
            if (isset($detailData['action']) && $detailData['action'] === ConsultCallDetails::ACTION_END_PROCESS) {
                $detailData['process_status'] = ConsultCallDetails::PROCESS_STATUS_CLOSED;
            } elseif (!isset($detailData['process_status'])) {
                $detailData['process_status'] = ConsultCallDetails::PROCESS_STATUS_ACTIVE;
            }

            $detail->update($detailData);

            DB::commit();

            Log::info('ConsultCall updateDetails: updated successfully', [
                'consult_call_id' => $id,
                'detail_id' => $detailId,
                'process_status' => $detail->process_status,
            ]);

            return response()->json([
                'success' => true,
                'data' => $detail->fresh(['clinicalCondition', 'testResult']),
                'message' => 'Consult call detail updated successfully.',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('ConsultCall updateDetails: failed', [
                'error' => $e->getMessage(),
                'consult_call_id' => $id,
                'detail_id' => $detailId,
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to update consult call detail.',
            ], 500);
        }
    }

    public function destroyDetails(int $id, int $detailId): JsonResponse
    {
        Log::info('ConsultCall destroyDetails: deleting detail', [
            'consult_call_id' => $id,
            'detail_id' => $detailId,
        ]);

        $detail = ConsultCallDetails::where('consult_call_id', $id)->find($detailId);

        if (!$detail) {
            Log::warning('ConsultCall destroyDetails: detail not found', [
                'consult_call_id' => $id,
                'detail_id' => $detailId,
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Consult call detail not found.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $detail->delete();

            DB::commit();

            Log::info('ConsultCall destroyDetails: deleted successfully', [
                'consult_call_id' => $id,
                'detail_id' => $detailId,
            ]);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Consult call detail deleted successfully.',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('ConsultCall destroyDetails: failed', [
                'error' => $e->getMessage(),
                'consult_call_id' => $id,
                'detail_id' => $detailId,
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to delete consult call detail.',
            ], 500);
        }
    }

    // ──────────────────────────────────────────────
    // Follow-up sub-resource
    // ──────────────────────────────────────────────

    public function storeFollowUp(Request $request, int $id): JsonResponse
    {
        Log::info('ConsultCall storeFollowUp: creating follow-up', ['consult_call_id' => $id]);

        $consultCall = ConsultCall::find($id);

        if (!$consultCall) {
            Log::warning('ConsultCall storeFollowUp: consult call not found', ['id' => $id]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Consult call not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'followup_type' => 'nullable|integer|in:0,1,2',
            'next_followup' => 'nullable|integer|in:0,1,2,3',
            'followup_date' => 'nullable|date',
            'is_blood_test_required' => 'nullable|boolean',
            'mode_of_conversion' => 'nullable|integer',
            'referral_to' => 'nullable|integer',
            'my_referral_id' => 'nullable|integer',
            'followup_reminder' => 'nullable|integer|in:0,1,2,3',
            'rescheduled_date' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => $validator->errors(),
                'message' => 'Validation failed.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $followUpData = $validator->validated();

            // Auto-set followup_reminder to pending (0) on creation if not explicitly provided
            if (!isset($followUpData['followup_reminder'])) {
                $followUpData['followup_reminder'] = 0;
            }

            $followUp = $consultCall->followUps()->create($followUpData);

            // Promote enrollment_type to Follow-up (2) the first time a follow-up is created,
            // provided the parent ConsultCall is still in Primary status (1).
            if ($consultCall->enrollment_type === ConsultCall::ENROLLMENT_TYPE_PRIMARY) {
                $consultCall->update(['enrollment_type' => ConsultCall::ENROLLMENT_TYPE_FOLLOW_UP]);
                Log::info('ConsultCall storeFollowUp: promoted enrollment_type to Follow-up', [
                    'consult_call_id' => $id,
                ]);
            }

            DB::commit();

            Log::info('ConsultCall storeFollowUp: created successfully', [
                'consult_call_id' => $id,
                'follow_up_id' => $followUp->id,
                'followup_reminder' => $followUp->followup_reminder,
            ]);

            return response()->json([
                'success' => true,
                'data' => $followUp,
                'message' => 'Consult call follow-up created successfully.',
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('ConsultCall storeFollowUp: failed', [
                'error' => $e->getMessage(),
                'consult_call_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to create consult call follow-up.',
            ], 500);
        }
    }

    public function updateFollowUp(Request $request, int $id, int $followUpId): JsonResponse
    {
        Log::info('ConsultCall updateFollowUp: updating follow-up', [
            'consult_call_id' => $id,
            'follow_up_id' => $followUpId,
        ]);

        $followUp = ConsultCallFollowUp::where('consult_call_id', $id)->find($followUpId);

        if (!$followUp) {
            Log::warning('ConsultCall updateFollowUp: follow-up not found', [
                'consult_call_id' => $id,
                'follow_up_id' => $followUpId,
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Consult call follow-up not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'followup_type' => 'nullable|integer|in:0,1,2',
            'next_followup' => 'nullable|integer|in:0,1,2,3',
            'followup_date' => 'nullable|date',
            'is_blood_test_required' => 'nullable|boolean',
            'mode_of_conversion' => 'nullable|integer',
            'referral_to' => 'nullable|integer',
            'my_referral_id' => 'nullable|integer',
            'followup_reminder' => 'nullable|integer|in:0,1,2,3',
            'rescheduled_date' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data' => $validator->errors(),
                'message' => 'Validation failed.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $followUp->update($validator->validated());

            DB::commit();

            Log::info('ConsultCall updateFollowUp: updated successfully', [
                'consult_call_id' => $id,
                'follow_up_id' => $followUpId,
            ]);

            return response()->json([
                'success' => true,
                'data' => $followUp->fresh(),
                'message' => 'Consult call follow-up updated successfully.',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('ConsultCall updateFollowUp: failed', [
                'error' => $e->getMessage(),
                'consult_call_id' => $id,
                'follow_up_id' => $followUpId,
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to update consult call follow-up.',
            ], 500);
        }
    }

    public function destroyFollowUp(int $id, int $followUpId): JsonResponse
    {
        Log::info('ConsultCall destroyFollowUp: deleting follow-up', [
            'consult_call_id' => $id,
            'follow_up_id' => $followUpId,
        ]);

        $followUp = ConsultCallFollowUp::where('consult_call_id', $id)->find($followUpId);

        if (!$followUp) {
            Log::warning('ConsultCall destroyFollowUp: follow-up not found', [
                'consult_call_id' => $id,
                'follow_up_id' => $followUpId,
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Consult call follow-up not found.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $followUp->delete();

            DB::commit();

            Log::info('ConsultCall destroyFollowUp: deleted successfully', [
                'consult_call_id' => $id,
                'follow_up_id' => $followUpId,
            ]);

            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'Consult call follow-up deleted successfully.',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('ConsultCall destroyFollowUp: failed', [
                'error' => $e->getMessage(),
                'consult_call_id' => $id,
                'follow_up_id' => $followUpId,
            ]);

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Failed to delete consult call follow-up.',
            ], 500);
        }
    }

    // ──────────────────────────────────────────────
    // PDF export
    // ──────────────────────────────────────────────

    public function exportPdf(Request $request, int $id): JsonResponse
    {
        Log::info('ConsultCall exportPdf: generating PDF', ['consult_call_id' => $id]);

        // If a specific test_result_id is provided, use it directly.
        if ($request->filled('test_result_id')) {
            $testResultId = (int) $request->input('test_result_id');

            Log::info('ConsultCall exportPdf: using specific test_result_id', [
                'consult_call_id' => $id,
                'test_result_id'  => $testResultId,
            ]);

            return app(PDFController::class)->exportByTestResultId($testResultId);
        }

        // Fallback: use the latest detail that has a linked test result.
        $consultCall = ConsultCall::with(['details' => function ($q) {
            $q->whereNotNull('test_result_id')->latest();
        }])->find($id);

        if (!$consultCall) {
            Log::warning('ConsultCall exportPdf: consult call not found', ['consult_call_id' => $id]);

            return response()->json([
                'success' => false,
                'message' => 'Consult call not found.',
            ], 404);
        }

        $detail = $consultCall->details->first();

        if (!$detail || !$detail->test_result_id) {
            Log::warning('ConsultCall exportPdf: no test result linked', ['consult_call_id' => $id]);

            return response()->json([
                'success' => false,
                'message' => 'No test result linked to this consult call.',
            ], 404);
        }

        Log::info('ConsultCall exportPdf: dispatching to PDFController', [
            'consult_call_id' => $id,
            'test_result_id'  => $detail->test_result_id,
        ]);

        return app(PDFController::class)->exportByTestResultId($detail->test_result_id);
    }
}

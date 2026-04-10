<?php

namespace App\Http\Controllers\API\ConsultCall;

use App\Http\Controllers\Controller;
use App\Models\ConsultCallFollowUp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ConsultCallFollowUpController extends Controller
{
    /**
     * Link a referral to a consult call by searching for the most recent follow-up
     * belonging to the given consult_call_id, then storing my_referral_id and referral_to.
     *
     * PATCH /api/v1/consult-call/{id}/link-referral-by-call
     */
    public function linkReferralByCall(Request $request, int $id): JsonResponse
    {
        Log::info('ConsultCallFollowUp linkReferralByCall: start', [
            'consult_call_id' => $id,
        ]);

        $validator = Validator::make($request->all(), [
            'my_referral_id' => 'required|integer|min:1',
            'referral_to'    => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data'    => $validator->errors(),
                'message' => 'Validation failed.',
            ], 422);
        }

        $followUp = ConsultCallFollowUp::where('consult_call_id', $id)
            ->orderByDesc('id')
            ->first();

        if (!$followUp) {
            Log::warning('ConsultCallFollowUp linkReferralByCall: no follow-up found', [
                'consult_call_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'No follow-up record found for consult call ID ' . $id . '.',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $followUp->update($validator->validated());

            DB::commit();

            Log::info('ConsultCallFollowUp linkReferralByCall: linked successfully', [
                'consult_call_id' => $id,
                'follow_up_id'    => $followUp->id,
                'my_referral_id'  => $followUp->my_referral_id,
                'referral_to'     => $followUp->referral_to,
            ]);

            return response()->json([
                'success' => true,
                'data'    => $followUp->fresh(),
                'message' => 'Referral linked to follow-up successfully.',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('ConsultCallFollowUp linkReferralByCall: failed', [
                'error'           => $e->getMessage(),
                'consult_call_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Failed to link referral to follow-up.',
            ], 500);
        }
    }

    /**
     * Link a MyReferral record to a consult call follow-up.
     * Updates referral_to (outlet ID) and my_referral_id on the follow-up.
     *
     * PATCH /api/v1/consult-call/{id}/follow-up/{followUpId}/link-referral
     */
    public function linkReferral(Request $request, int $id, int $followUpId): JsonResponse
    {
        Log::info('ConsultCallFollowUp linkReferral: linking referral', [
            'consult_call_id' => $id,
            'follow_up_id'    => $followUpId,
        ]);

        $followUp = ConsultCallFollowUp::where('consult_call_id', $id)->find($followUpId);

        if (!$followUp) {
            Log::warning('ConsultCallFollowUp linkReferral: follow-up not found', [
                'consult_call_id' => $id,
                'follow_up_id'    => $followUpId,
            ]);

            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Consult call follow-up not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'my_referral_id' => 'required|integer',
            'referral_to'    => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'data'    => $validator->errors(),
                'message' => 'Validation failed.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $followUp->update($validator->validated());

            DB::commit();

            Log::info('ConsultCallFollowUp linkReferral: linked successfully', [
                'consult_call_id' => $id,
                'follow_up_id'    => $followUpId,
                'my_referral_id'  => $followUp->my_referral_id,
                'referral_to'     => $followUp->referral_to,
            ]);

            return response()->json([
                'success' => true,
                'data'    => $followUp->fresh(),
                'message' => 'Referral linked to follow-up successfully.',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('ConsultCallFollowUp linkReferral: failed', [
                'error'           => $e->getMessage(),
                'consult_call_id' => $id,
                'follow_up_id'    => $followUpId,
            ]);

            return response()->json([
                'success' => false,
                'data'    => null,
                'message' => 'Failed to link referral to follow-up.',
            ], 500);
        }
    }
}

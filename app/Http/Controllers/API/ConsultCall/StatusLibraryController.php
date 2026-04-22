<?php

namespace App\Http\Controllers\API\ConsultCall;

use App\Http\Controllers\Controller;
use App\Models\ConsultCall;
use App\Models\ConsultCallDetails;
use App\Models\ConsultCallFollowUp;
use Illuminate\Http\JsonResponse;

class StatusLibraryController extends Controller
{
    public function enrollmentTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['id' => ConsultCall::ENROLLMENT_TYPE_PRIMARY, 'label' => 'Primary'],
                ['id' => ConsultCall::ENROLLMENT_TYPE_FOLLOW_UP, 'label' => 'Follow Up'],
            ],
            'message' => 'Enrollment types retrieved successfully',
        ]);
    }

    public function consentCallStatuses(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['id' => ConsultCall::CONSENT_STATUS_PENDING, 'label' => 'Pending'],
                ['id' => ConsultCall::CONSENT_STATUS_OBTAINED, 'label' => 'Obtained'],
                ['id' => ConsultCall::CONSENT_STATUS_REFUSED, 'label' => 'Refused'],
            ],
            'message' => 'Consent call statuses retrieved successfully',
        ]);
    }

    public function scheduledStatuses(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['id' => ConsultCall::SCHEDULED_STATUS_PENDING, 'label' => 'Pending'],
                ['id' => ConsultCall::SCHEDULED_STATUS_CONFIRMED, 'label' => 'Confirmed'],
                ['id' => ConsultCall::SCHEDULED_STATUS_RESCHEDULED, 'label' => 'Rescheduled'],
                ['id' => ConsultCall::SCHEDULED_STATUS_CANCELLED, 'label' => 'Cancelled'],
            ],
            'message' => 'Scheduled statuses retrieved successfully',
        ]);
    }

    public function modesOfConsultation(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['id' => ConsultCall::MODE_PENDING, 'label' => 'Pending'],
                ['id' => ConsultCall::MODE_PHONE, 'label' => 'Phone'],
                ['id' => ConsultCall::MODE_GOOGLE_MEET, 'label' => 'Google Meet'],
                ['id' => ConsultCall::MODE_WHATSAPP, 'label' => 'WhatsApp'],
            ],
            'message' => 'Modes of consultation retrieved successfully',
        ]);
    }

    public function actions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['id' => ConsultCallDetails::ACTION_REFER_INTERNAL, 'label' => 'Refer Internal'],
                ['id' => ConsultCallDetails::ACTION_REFER_EXTERNAL, 'label' => 'Refer External'],
                ['id' => ConsultCallDetails::ACTION_END_PROCESS, 'label' => 'End Process'],
            ],
            'message' => 'Actions retrieved successfully',
        ]);
    }

    public function consultStatuses(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['id' => ConsultCallDetails::CONSULT_STATUS_PENDING, 'label' => 'Pending'],
                ['id' => ConsultCallDetails::CONSULT_STATUS_COMPLETED, 'label' => 'Completed'],
                ['id' => ConsultCallDetails::CONSULT_STATUS_NO_SHOW, 'label' => 'No Show'],
                ['id' => ConsultCallDetails::CONSULT_STATUS_CANCELLED, 'label' => 'Cancelled'],
            ],
            'message' => 'Consult statuses retrieved successfully',
        ]);
    }

    public function processStatuses(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['id' => ConsultCallDetails::PROCESS_STATUS_ACTIVE, 'label' => 'Active'],
                ['id' => ConsultCallDetails::PROCESS_STATUS_CLOSED, 'label' => 'Closed'],
            ],
            'message' => 'Process statuses retrieved successfully',
        ]);
    }

    public function followUpTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['id' => ConsultCallFollowUp::FOLLOWUP_TYPE_NONE, 'label' => 'None'],
                ['id' => ConsultCallFollowUp::FOLLOWUP_TYPE_BLOOD_TEST_AND_REVIEW, 'label' => 'Blood Test and Review'],
                ['id' => ConsultCallFollowUp::FOLLOWUP_TYPE_REVIEW_ONLY, 'label' => 'Review Only'],
            ],
            'message' => 'Follow-up types retrieved successfully',
        ]);
    }

    public function nextFollowUps(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['id' => ConsultCallFollowUp::NEXT_FOLLOWUP_NONE, 'label' => 'None'],
                ['id' => ConsultCallFollowUp::NEXT_FOLLOWUP_1_MONTH, 'label' => '1 Month'],
                ['id' => ConsultCallFollowUp::NEXT_FOLLOWUP_3_MONTHS, 'label' => '3 Months'],
                ['id' => ConsultCallFollowUp::NEXT_FOLLOWUP_6_MONTHS, 'label' => '6 Months'],
            ],
            'message' => 'Next follow-ups retrieved successfully',
        ]);
    }

    public function followUpReminders(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['id' => ConsultCallFollowUp::FOLLOWUP_REMINDER_PENDING, 'label' => 'Pending'],
                ['id' => ConsultCallFollowUp::FOLLOWUP_REMINDER_COMPLETED, 'label' => 'Completed'],
                ['id' => ConsultCallFollowUp::FOLLOWUP_REMINDER_RESCHEDULED, 'label' => 'Rescheduled'],
                ['id' => ConsultCallFollowUp::FOLLOWUP_REMINDER_CANCELLED, 'label' => 'Cancelled'],
            ],
            'message' => 'Follow-up reminders retrieved successfully',
        ]);
    }
}

<?php

namespace App\Http\Controllers\API\Webhook;

use App\Http\Controllers\Controller;
use App\Http\Requests\AIResultRequest;
use App\Jobs\ProcessAIWebhookResult;
use App\Services\AIReviewService;
use App\Services\ReviewHtmlGenerator;
use Illuminate\Support\Facades\Log;

class AIResultController extends Controller
{
    protected $htmlGenerator;
    protected $AIReviewService;
    protected $logChannel;

    public function __construct(
        ReviewHtmlGenerator $htmlGenerator,
        AIReviewService $AIReviewService
    ) {
        $this->htmlGenerator = $htmlGenerator;
        $this->AIReviewService = $AIReviewService;
    }
    
    public function store(AIResultRequest $request)
    {
        $validated = $request->validated();

        Log::channel('webhook')->info('AI webhook received', [
            'test_result_id' => $validated['test_result_id'],
            'success' => $validated['success'],
            'status' => $validated['status']
        ]);

        // Dispatch job for processing
        ProcessAIWebhookResult::dispatch($validated);

        // Return 200 OK immediately (acknowledge receipt)
        return response()->json([
            'success' => true,
            'message' => 'Webhook received and queued for processing'
        ], 200);
    }
}
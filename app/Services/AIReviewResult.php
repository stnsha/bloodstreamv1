<?php

namespace App\Services;

/**
 * Data Transfer Object for AI Review Processing Results
 * Encapsulates the outcome of processing a test result through AI review
 */
class AIReviewResult
{
    public $testResultId;
    public $htmlReview;
    public $success;
    public $errorMessage;
    public $icno;
    public $refid;

    public function __construct(
        int $testResultId,
        ?string $htmlReview,
        bool $success,
        ?string $errorMessage = null,
        ?string $icno = null,
        ?string $refid = null
    ) {
        $this->testResultId = $testResultId;
        $this->htmlReview = $htmlReview;
        $this->success = $success;
        $this->errorMessage = $errorMessage;
        $this->icno = $icno;
        $this->refid = $refid;
    }

    /**
     * Convert result to array format for JSON responses
     */
    public function toArray(): array
    {
        $result = [
            'test_result_id' => $this->testResultId,
            'report_id' => $this->testResultId, // Alias for compatibility
            'success' => $this->success,
        ];

        if ($this->icno) {
            $result['icno'] = $this->icno;
        }

        if ($this->refid) {
            $result['refid'] = $this->refid;
        }

        if ($this->success) {
            $result['review'] = $this->htmlReview;
        } else {
            $result['error'] = $this->errorMessage;
            $result['reason'] = $this->errorMessage; // Alias for compatibility
        }

        return $result;
    }

    /**
     * Check if the review was successful
     */
    public function isSuccessful(): bool
    {
        return $this->success;
    }

    /**
     * Check if the review failed
     */
    public function isFailed(): bool
    {
        return !$this->success;
    }
}

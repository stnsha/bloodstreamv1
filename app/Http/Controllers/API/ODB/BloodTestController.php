<?php

namespace App\Http\Controllers\API\ODB;

use App\Http\Controllers\Controller;
use App\Models\TestResult;
use Exception;
use Illuminate\Http\Request;

class BloodTestController extends Controller
{
    public function getReportId(Request $request)
    {
        try {
            $validated = $request->validate([
                '*.icno' => 'required|string',
                '*.refid' => 'nullable|string',
            ], [
                '*.icno.required' => 'IC No. is required.',
            ]);

            $results = [];

            foreach ($request->all() as $item) {
                $icno = $item['icno'];
                $refid = $item['refid'] ?? null;

                // Search by IC number first
                $testResult = TestResult::whereHas('patient', function ($p) use ($icno) {
                    $p->where('icno', $icno);
                })->first();

                // Fallback to search by refid if provided
                if (!$testResult && $refid) {
                    $testResult = TestResult::where('ref_id', $refid)->first();
                }

                // Update ref_id if request has refid but DB has null
                if ($testResult && $refid && !$testResult->ref_id) {
                    $testResult->ref_id = $refid;
                    $testResult->save();
                }

                // Only add to results if test result found
                if ($testResult) {
                    $results[] = [
                        'icno' => $icno,
                        'report_id' => $testResult->id
                    ];
                }
            }

            return response()->json($results);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing the request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\DoctorReview;
use App\Models\Patient;
use App\Models\TestResult;
use App\Models\ResultLibrary;
use App\Services\MyHealthService;
use Illuminate\Support\Facades\Log;

use Carbon\Carbon;
use Exception;

class DoctorReviewController extends Controller
{
    protected $myHealthService;

    public function __construct(MyHealthService $myHealthService)
    {
        $this->myHealthService = $myHealthService;
    }

    public function processResult()
    {
        $testResults = TestResult::with([
            'patient',
            'testResultItems.panelPanelItem.panel.panelCategory',
            'testResultItems.referenceRange',
            'testResultItems.panelPanelItem.panelItem',
            'testResultItems.panelComments.masterPanelComment',
        ])
            ->where('is_reviewed', false)
            ->where('is_completed', true)
            ->whereHas('patient', function ($query) {
                $query->where('ic_type', 'NRIC');
            })
            ->take(5) //First 5
            ->get();

        foreach ($testResults as $tr) {
            $icno = $tr->patient->icno;
            $checkRecords = $this->myHealthService->getCheckRecordIdByIC($icno);

            $patientInfo = [
                'Age' => $tr->patient->age
            ];

            if ($checkRecords) {
                foreach ($checkRecords as $cr) {
                    $recordId = $cr->id;
                    $recordGender = $cr->gender;
                    $recordDate = Carbon::parse($cr->date_time)->format('Y-m-d');

                    if (is_null($tr->patient->gender)) {
                        $tr->patient->gender = $recordGender == 1 ? Patient::GENDER_MALE : Patient::GENDER_FEMALE;
                        $tr->patient->save();
                    }

                    $patientInfo['Gender'] = $tr->patient->gender;

                    $recordDetails = $this->myHealthService->getRecordDetailsByRecordId($recordId);
                    if (count($recordDetails) != 0) {
                        $transformedRecordDetails = [];

                        foreach ($recordDetails as $rd) {
                            if (isset($rd->parameter)) {
                                $parameterName = $rd->parameter;
                                unset($rd->parameter);
                                $transformedRecordDetails[$parameterName] = $rd;
                            }
                        }
                        $healthDetails[$recordDate] = $transformedRecordDetails;
                        $patientInfo = array_merge($patientInfo, $healthDetails);
                    }
                }
            }

            if (!$tr || !$tr->id) {
                Log::error('Invalid test result object');
                $failedResults[] = ['id' => 'unknown', 'reason' => 'Invalid test result object'];
                continue;
            }

            if (!$tr->patient) {
                Log::warning('Test result has no associated patient', ['test_result_id' => $tr->id]);
                $failedResults[] = ['id' => $tr->id, 'reason' => 'Missing patient information'];
                continue;
            }

            $reportDate = Carbon::parse($tr->reported_date)->format('Y-m-d');
            $categorizedItems = [];
            $validItemsCount = 0;

            try {
                if ($tr->testResultItems->isEmpty()) {
                    Log::warning('Test result has no test result items', ['test_result_id' => $tr->id]);
                    $failedResults[] = ['id' => $tr->id, 'reason' => 'No test result items found'];
                    continue;
                }

                foreach ($tr->testResultItems as $ri) {
                    try {
                        if (!$ri || !$ri->id) {
                            Log::warning('Invalid result item', ['test_result_id' => $tr->id]);
                            continue;
                        }

                        if (!$ri->panelPanelItem) {
                            Log::warning('Test result item missing panel relationship', [
                                'result_item_id' => $ri->id,
                                'test_result_id' => $tr->id
                            ]);
                            continue;
                        }

                        if (!$ri->panelPanelItem->panelItem) {
                            Log::warning('Test result item missing panel item relationship', [
                                'result_item_id' => $ri->id,
                                'test_result_id' => $tr->id
                            ]);
                            continue;
                        }

                        $categoryName = 'Unknown Category';
                        try {
                            $categoryName = $ri->panelPanelItem->panel->panelCategory->name ??
                                $ri->panelPanelItem->panel->name ??
                                'Unknown Category';
                        } catch (Exception $e) {
                            Log::warning('Error determining category name', [
                                'error' => $e->getMessage(),
                                'result_item_id' => $ri->id
                            ]);
                        }

                        if (!isset($categorizedItems[$categoryName])) {
                            $categorizedItems[$categoryName] = [];
                        }

                        $flagDescription = $ri->flag;
                        if (!empty($ri->flag)) {
                            try {
                                $resultLibrary = ResultLibrary::where('code', '0078')
                                    ->where('value', $ri->flag)
                                    ->first();
                                if ($resultLibrary && !empty($resultLibrary->description)) {
                                    // Remove content within parentheses and trim whitespace
                                    $flagDescription = trim(preg_replace('/\s*\([^)]*\)/', '', $resultLibrary->description));
                                } else {
                                    $flagDescription = $ri->flag;
                                }
                            } catch (Exception $e) {
                                Log::error('Error fetching flag description from ResultLibrary', [
                                    'error' => $e->getMessage(),
                                    'flag' => $ri->flag,
                                    'result_item_id' => $ri->id
                                ]);
                            }
                        }

                        $itemData = [
                            'panel_item_name' => $ri->panelPanelItem->panelItem->name ?? 'Unknown Item',
                            'result_value' => $ri->value ?? null,
                            'panel_item_unit' => $ri->panelPanelItem->panelItem->unit ?? null,
                            'result_status' => $flagDescription ?? null,
                            'reference_range' => null,
                            'comments' => []
                        ];

                        if ($ri->reference_range_id && $ri->referenceRange) {
                            try {
                                $itemData['reference_range'] = $ri->referenceRange->value;
                            } catch (Exception $e) {
                                Log::warning('Error accessing reference range', [
                                    'error' => $e->getMessage(),
                                    'result_item_id' => $ri->id
                                ]);
                            }
                        }

                        if ($ri->panelComments && !$ri->panelComments->isEmpty()) {
                            try {
                                foreach ($ri->panelComments as $pc) {
                                    if ($pc && $pc->masterPanelComment && !empty($pc->masterPanelComment->comment)) {
                                        $itemData['comments'][] = $pc->masterPanelComment->comment;
                                    }
                                }
                            } catch (Exception $e) {
                                Log::warning('Error processing panel comments', [
                                    'error' => $e->getMessage(),
                                    'result_item_id' => $ri->id
                                ]);
                            }
                        }

                        $categorizedItems[$categoryName][] = $itemData;
                        $validItemsCount++;
                    } catch (Exception $e) {
                        Log::error('Error processing test result item', [
                            'error' => $e->getMessage(),
                            'result_item_id' => $ri->id ?? 'unknown',
                            'test_result_id' => $tr->id,
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                }

                if ($validItemsCount === 0) {
                    Log::warning('No valid test result items processed', ['test_result_id' => $tr->id]);
                    $failedResults[] = ['id' => $tr->id, 'reason' => 'No valid test result items'];
                    continue;
                }

                $finalResults[$reportDate] = $categorizedItems;

                $testResultData = [
                    'Health History' => $patientInfo,
                    'Blood Test Results' => $finalResults
                ];

                return response()->json($testResultData); //Send to API

            } catch (Exception $e) {
                Log::error('Critical error processing individual test result', [
                    'error' => $e->getMessage(),
                    'test_result_id' => $tr->id ?? 'unknown',
                    'trace' => $e->getTraceAsString()
                ]);
                $failedResults[] = ['id' => $tr->id ?? 'unknown', 'reason' => 'Critical processing error'];
            }
        }
    }

    public function formatResponse($response)
    {
        $response = "";
    }
}
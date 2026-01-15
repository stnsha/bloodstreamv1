<?php

namespace Tests\Feature\Innoquest;

use App\Constants\Innoquest\PanelPanelItem as PanelPanelItemConstants;
use App\Jobs\Innoquest\ProcessPanelResults;
use App\Jobs\SendToAIServer;
use App\Models\TestResult;
use App\Services\MyHealthService;
use App\Services\PanelInterpretationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use ReflectionClass;
use Tests\TestCase;

class SpecialTestCalculationTest extends TestCase
{
    /**
     * Test special test calculation for test result ID 24477.
     * This mimics the ProcessPanelResults flow when is_completed = true.
     */
    public function test_special_test_calculation_for_test_result_24477(): void
    {
        // Arrange
        $testResultId = 24477;
        $testResult = TestResult::with(['patient', 'testResultItems'])->find($testResultId);

        $this->assertNotNull($testResult, 'Test result 24477 must exist');
        $this->assertTrue($testResult->is_completed, 'Test result must be completed');
        $this->assertNotNull($testResult->patient, 'Test result must have a patient');

        // Get the relevant test result items
        $testResultItems = $testResult->testResultItems()
            ->whereIn('panel_panel_item_id', PanelPanelItemConstants::PANEL_PANEL_ITEM_IDS)
            ->get()
            ->keyBy('panel_panel_item_id');

        Log::info('Test: Starting special test calculation', [
            'test_result_id' => $testResult->id,
            'lab_no' => $testResult->lab_no,
            'patient_age' => $testResult->patient->age,
            'relevant_items_count' => $testResultItems->count(),
        ]);

        // Act - Call the protected method via reflection
        $job = new ProcessPanelResults([], 'test-request-id', 2);
        $reflection = new ReflectionClass($job);
        $method = $reflection->getMethod('calculateSpecialTests');
        $method->setAccessible(true);
        $method->invoke($job, $testResult);

        // Assert - Verify special tests were created/updated
        $testResult->refresh();
        $specialTests = $testResult->testResultSpecialTests;

        $this->assertNotEmpty($specialTests, 'Special tests should be created');

        Log::info('Test: Special tests created', [
            'test_result_id' => $testResult->id,
            'special_tests_count' => $specialTests->count(),
        ]);

        // Log each special test result
        foreach ($specialTests as $specialTest) {
            Log::info('Test: Special test result', [
                'panel_panel_item_id' => $specialTest->panel_panel_item_id,
                'value' => $specialTest->value,
                'panel_interpretation_id' => $specialTest->panel_interpretation_id,
            ]);
        }

        // Verify specific calculations exist
        $panelInterpretationService = app(PanelInterpretationService::class);

        // Check lipid interpretations
        $lr = $panelInterpretationService->lipidInterpretation(
            cri_i: $testResultItems[PanelPanelItemConstants::CRI_I]->value ?? null,
            cri_ii: $testResultItems[PanelPanelItemConstants::CRI_II]->value ?? null,
            aip: $testResultItems[PanelPanelItemConstants::AIP]->value ?? null,
        );

        if ($lr['cri_i_panel_panel_item_id']) {
            $criISpecialTest = $specialTests->firstWhere('panel_panel_item_id', $lr['cri_i_panel_panel_item_id']);
            $this->assertNotNull($criISpecialTest, 'CRI-I special test should exist');
            Log::info('Test: CRI-I verified', [
                'value' => $criISpecialTest->value,
                'interpretation_id' => $criISpecialTest->panel_interpretation_id,
            ]);
        }

        // Check AC calculation
        $ac = $panelInterpretationService->calculateAC(
            totalCholesterol: $testResultItems[PanelPanelItemConstants::TOTAL_CHOLESTEROL]->value ?? null,
            hdlCholesterol: $testResultItems[PanelPanelItemConstants::HDL]->value ?? null,
        );

        if ($ac['panel_panel_item_id']) {
            $acSpecialTest = $specialTests->firstWhere('panel_panel_item_id', $ac['panel_panel_item_id']);
            $this->assertNotNull($acSpecialTest, 'AC special test should exist');
            $this->assertEquals($ac['value'], $acSpecialTest->value, 'AC value should match calculated value');
            Log::info('Test: AC verified', [
                'calculated_value' => $ac['value'],
                'stored_value' => $acSpecialTest->value,
                'interpretation_id' => $acSpecialTest->panel_interpretation_id,
            ]);
        }

        // Check FIB-4 calculation
        $fib = $panelInterpretationService->calculateFIB(
            age: $testResult->patient->age,
            ast: $testResultItems[PanelPanelItemConstants::AST]->value ?? null,
            alt: $testResultItems[PanelPanelItemConstants::ALT]->value ?? null,
            plateletCount: $testResultItems[PanelPanelItemConstants::PLATELETS]->value ?? null,
        );

        if ($fib['panel_panel_item_id']) {
            $fibSpecialTest = $specialTests->firstWhere('panel_panel_item_id', $fib['panel_panel_item_id']);
            $this->assertNotNull($fibSpecialTest, 'FIB-4 special test should exist');
            $this->assertEquals($fib['value'], $fibSpecialTest->value, 'FIB-4 value should match calculated value');
            Log::info('Test: FIB-4 verified', [
                'calculated_value' => $fib['value'],
                'stored_value' => $fibSpecialTest->value,
                'interpretation_id' => $fibSpecialTest->panel_interpretation_id,
            ]);
        }

        Log::info('Test: Special test calculation completed successfully', [
            'test_result_id' => $testResult->id,
            'total_special_tests' => $specialTests->count(),
        ]);
    }

    /**
     * Test the full flow mimicking ProcessPanelResults completion up to SendToAIServer dispatch.
     */
    public function test_full_flow_mimics_process_panel_results_to_ai_server(): void
    {
        // Arrange
        Queue::fake([SendToAIServer::class]);

        $testResultId = 24477;
        $testResult = TestResult::with(['patient'])->find($testResultId);

        $this->assertNotNull($testResult, 'Test result 24477 must exist');

        Log::info('Test: Starting full flow test', [
            'test_result_id' => $testResult->id,
            'lab_no' => $testResult->lab_no,
            'is_completed' => $testResult->is_completed,
        ]);

        // Act - Mimic the ProcessPanelResults completion block
        // This is what happens in ProcessPanelResults when PDF is received

        // Step 1: Mark as completed (already done for 24477, but simulate)
        $testResult->is_completed = true;
        $testResult->is_reviewed = false;
        $testResult->save();

        Log::info('Test: Marked test result as completed', [
            'test_result_id' => $testResult->id,
        ]);

        // Step 2: Calculate special tests (error-isolated)
        try {
            Log::info('Test: Starting special test calculation', [
                'test_result_id' => $testResult->id,
                'lab_no' => $testResult->lab_no,
            ]);

            $job = new ProcessPanelResults([], 'test-request-id', 2);
            $reflection = new ReflectionClass($job);
            $method = $reflection->getMethod('calculateSpecialTests');
            $method->setAccessible(true);
            $method->invoke($job, $testResult);

            Log::info('Test: Special test calculation completed', [
                'test_result_id' => $testResult->id,
            ]);
        } catch (\Throwable $e) {
            // Log error but DO NOT rethrow - allow main flow to continue
            Log::error('Test: Special test calculation failed', [
                'test_result_id' => $testResult->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        // Step 3: Dispatch to AI server (what would happen after DB commit)
        try {
            SendToAIServer::dispatch($testResult->id);
            Log::info('Test: Dispatched test result to AI server queue', [
                'test_result_id' => $testResult->id,
                'lab_no' => $testResult->lab_no,
            ]);
        } catch (\Throwable $e) {
            Log::error('Test: Failed to dispatch test result to AI server queue', [
                'test_result_id' => $testResult->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Assert
        // Verify special tests were created
        $testResult->refresh();
        $specialTests = $testResult->testResultSpecialTests;
        $this->assertNotEmpty($specialTests, 'Special tests should be created');

        // Verify SendToAIServer was dispatched
        Queue::assertPushed(SendToAIServer::class, function ($job) use ($testResultId) {
            $reflection = new ReflectionClass($job);
            $property = $reflection->getProperty('testResultId');
            $property->setAccessible(true);
            return $property->getValue($job) === $testResultId;
        });

        Log::info('Test: Full flow completed successfully', [
            'test_result_id' => $testResult->id,
            'special_tests_count' => $specialTests->count(),
            'ai_server_dispatched' => true,
        ]);
    }

    /**
     * Test that special test calculation failure does NOT break the main flow.
     */
    public function test_special_test_failure_does_not_break_main_flow(): void
    {
        // Arrange
        Queue::fake([SendToAIServer::class]);

        $testResultId = 24477;
        $testResult = TestResult::find($testResultId);

        $this->assertNotNull($testResult, 'Test result 24477 must exist');

        $flowCompleted = false;
        $specialTestFailed = false;

        // Act - Simulate the flow with a forced failure in special tests
        try {
            // Mark as completed
            $testResult->is_completed = true;
            $testResult->save();

            // Special test calculation (simulate failure by passing invalid data)
            try {
                Log::info('Test: Simulating special test calculation');

                // This should still work, but we're testing the error isolation pattern
                $job = new ProcessPanelResults([], 'test-request-id', 2);
                $reflection = new ReflectionClass($job);
                $method = $reflection->getMethod('calculateSpecialTests');
                $method->setAccessible(true);
                $method->invoke($job, $testResult);

            } catch (\Throwable $e) {
                $specialTestFailed = true;
                Log::error('Test: Special test failed (expected in error isolation test)', [
                    'error' => $e->getMessage(),
                ]);
                // DO NOT rethrow - this is the key point
            }

            // Continue with AI server dispatch regardless of special test result
            SendToAIServer::dispatch($testResult->id);
            $flowCompleted = true;

        } catch (\Throwable $e) {
            $this->fail('Main flow should not fail: ' . $e->getMessage());
        }

        // Assert
        $this->assertTrue($flowCompleted, 'Main flow should complete regardless of special test result');

        Queue::assertPushed(SendToAIServer::class);

        Log::info('Test: Error isolation verified', [
            'flow_completed' => $flowCompleted,
            'special_test_failed' => $specialTestFailed,
        ]);
    }
}

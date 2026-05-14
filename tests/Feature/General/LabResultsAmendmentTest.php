<?php

namespace Tests\Feature\General;

use App\Models\Lab;
use App\Models\LabCredential;
use App\Models\TestResult;
use App\Models\TestResultItem;
use App\Models\TestResultItemAmendment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class LabResultsAmendmentTest extends TestCase
{
    use RefreshDatabase;

    private Lab $lab;
    private LabCredential $credential;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@testlab.com',
            'password' => bcrypt('password'),
        ]);

        $this->lab = new Lab(['name' => 'Test Lab', 'code' => 'TST', 'status' => 1]);
        $this->lab->id = 6;
        $this->lab->save();

        $this->credential = LabCredential::create([
            'user_id' => $user->id,
            'lab_id' => 6,
            'username' => 'testlab',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->token = JWTAuth::fromUser($this->credential);
    }

    private function authedPost(string $url, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->postJson($url, $data);
    }

    private function authedGet(string $url): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->getJson($url);
    }

    private function payload(string $labNo, string $haemoglobinValue, bool $reportStatus): array
    {
        return [
            'reference_id' => null,
            'lab_no' => $labNo,
            'bill_code' => null,
            'report_status' => $reportStatus,
            'doctor' => [
                'code' => null,
                'name' => 'DR. BERNARD CHEU TECK LUK',
                'type' => null,
                'address' => 'LOT 10992, SECTION 64, KTLD, JALAN TUN JUGAH, 93350 KUCHING',
                'phone' => '082-507333',
            ],
            'patient' => [
                'icno' => '021112-13-0655',
                'ic_type' => 'NRIC',
                'name' => 'LUCAS WONG SIE HONG',
                'age' => '14',
                'gender' => 'M',
                'tel' => null,
            ],
            'collected_date' => '2017-03-31 11:44:00',
            'received_date' => '2017-03-31 11:44:00',
            'reported_date' => '2026-05-05 09:26:00',
            'validated_by' => null,
            'package_name' => null,
            'results' => [
                'HEMATOLOGY - FULL BLOOD COUNT' => [
                    'panel_code' => '',
                    'panel_sequence' => 1,
                    'panel_remarks' => null,
                    'result_status' => 1,
                    'tests' => [
                        [
                            'test_code' => 'HAE',
                            'test_name' => 'Haemoglobin 血红蛋白',
                            'result_value' => $haemoglobinValue,
                            'result_flag' => null,
                            'unit' => 'g/dl',
                            'ref_range' => '13.0 - 18.0',
                            'test_note' => null,
                            'report_sequence' => 1,
                            'decimal_point' => 1,
                        ],
                        [
                            'test_code' => 'RBC',
                            'test_name' => 'RBC 红血球计数',
                            'result_value' => '5.0',
                            'result_flag' => null,
                            'unit' => 'x10^12/L',
                            'ref_range' => '4.5 - 6.5',
                            'test_note' => null,
                            'report_sequence' => 2,
                            'decimal_point' => 1,
                        ],
                    ],
                ],
                'DIFFERENTIAL COUNTS' => [
                    'panel_code' => '',
                    'panel_sequence' => 2,
                    'panel_remarks' => null,
                    'result_status' => 1,
                    'tests' => [
                        [
                            'test_code' => null,
                            'test_name' => 'Neutrophils 嗜中性球',
                            'result_value' => '55',
                            'result_flag' => 'H',
                            'unit' => '%',
                            'ref_range' => '32 - 54',
                            'test_note' => null,
                            'report_sequence' => 1,
                            'decimal_point' => 0,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_first_submission_creates_fresh_items_with_has_amended_false(): void
    {
        $response = $this->authedPost('/api/v1/result/patient', $this->payload('LAB001', '14.3', false));


        $response->assertStatus(200);

        $testResult = TestResult::where('lab_no', 'LAB001')->first();
        $this->assertNotNull($testResult);

        $items = TestResultItem::where('test_result_id', $testResult->id)->get();
        $this->assertNotEmpty($items);

        foreach ($items as $item) {
            $this->assertFalse((bool) $item->has_amended);
        }

        $this->assertEquals(0, TestResultItemAmendment::count());
    }

    public function test_resubmit_partial_updates_in_place_no_amendment_record(): void
    {
        $this->authedPost('/api/v1/result/patient', $this->payload('LAB002', '14.3', false));

        $testResult = TestResult::where('lab_no', 'LAB002')->first();
        $itemCountBefore = TestResultItem::where('test_result_id', $testResult->id)->count();

        $this->authedPost('/api/v1/result/patient', $this->payload('LAB002', '15.0', false));

        $haemItem = TestResultItem::where('test_result_id', $testResult->id)
            ->whereHas('panelItem', fn ($q) => $q->where('code', 'HAE'))
            ->first();

        $this->assertEquals('15.0', $haemItem->value);
        $this->assertFalse((bool) $haemItem->has_amended);

        $this->assertEquals($itemCountBefore, TestResultItem::where('test_result_id', $testResult->id)->count());

        $this->assertEquals(0, TestResultItemAmendment::count());
    }

    public function test_resubmit_after_final_archives_old_value_and_marks_amended(): void
    {
        $this->authedPost('/api/v1/result/patient', $this->payload('LAB003', '14.3', true));

        $testResult = TestResult::where('lab_no', 'LAB003')->first();
        $this->assertTrue((bool) $testResult->is_completed);

        $haemItem = TestResultItem::where('test_result_id', $testResult->id)
            ->whereHas('panelItem', fn ($q) => $q->where('code', 'HAE'))
            ->first();

        $originalItemId = $haemItem->id;

        $this->authedPost('/api/v1/result/patient', $this->payload('LAB003', '16.0', true));

        $haemItem->refresh();
        $this->assertEquals('16.0', $haemItem->value);
        $this->assertTrue((bool) $haemItem->has_amended);
        $this->assertEquals($originalItemId, $haemItem->id);

        $amendment = TestResultItemAmendment::where('test_result_item_id', $originalItemId)->first();
        $this->assertNotNull($amendment);
        $this->assertEquals('14.3', $amendment->value);
    }

    public function test_multiple_amendments_accumulate_history(): void
    {
        $this->authedPost('/api/v1/result/patient', $this->payload('LAB004', '14.3', true));

        $testResult = TestResult::where('lab_no', 'LAB004')->first();
        $haemItem = TestResultItem::where('test_result_id', $testResult->id)
            ->whereHas('panelItem', fn ($q) => $q->where('code', 'HAE'))
            ->first();

        $this->authedPost('/api/v1/result/patient', $this->payload('LAB004', '15.0', true));
        $this->authedPost('/api/v1/result/patient', $this->payload('LAB004', '15.5', true));

        $haemItem->refresh();
        $this->assertEquals('15.5', $haemItem->value);
        $this->assertTrue((bool) $haemItem->has_amended);

        $amendments = TestResultItemAmendment::where('test_result_item_id', $haemItem->id)
            ->orderBy('created_at')
            ->pluck('value')
            ->toArray();

        $this->assertCount(2, $amendments);
        $this->assertEquals('14.3', $amendments[0]);
        $this->assertEquals('15.0', $amendments[1]);
    }

    public function test_show_returns_has_amended_status_and_history(): void
    {
        $this->authedPost('/api/v1/result/patient', $this->payload('LAB005', '14.3', true));

        $testResult = TestResult::where('lab_no', 'LAB005')->first();

        $this->authedPost('/api/v1/result/patient', $this->payload('LAB005', '16.0', true));

        $response = $this->authedGet('/api/v1/result/' . $testResult->id);

        $response->assertStatus(200);

        $panels = $response->json('data.results');
        $this->assertNotNull($panels);

        $haemTest = null;
        foreach ($panels as $panel) {
            foreach ($panel['tests'] as $test) {
                if ($test['test_code'] === 'HAE') {
                    $haemTest = $test;
                    break 2;
                }
            }
        }

        $this->assertNotNull($haemTest, 'HAE test item not found in response');
        $this->assertEquals('amended', $haemTest['status']);
        $this->assertTrue($haemTest['has_amended']);
        $this->assertCount(1, $haemTest['amendments']);
        $this->assertEquals('14.3', $haemTest['amendments'][0]['value']);
        $this->assertArrayHasKey('amended_at', $haemTest['amendments'][0]);
    }
}

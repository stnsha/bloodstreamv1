<?php

namespace Tests\Feature\Innoquest;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\TestResult;
use App\Services\IcNormalizerService;
use App\Services\OctopusApiService;
use App\Services\RefIdVerificationService;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

/**
 * RefIdVerificationService::verify() against a mocked ODB lookup.
 * Modelled on consult call 9119: lab result for TAN YONG JIAN delivered with
 * ref_id INN124856 (Lee Chee Chim's sale) instead of INN124586.
 */
class RefIdVerificationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TAN_IC = '930825146719';

    private const LEE_IC = '810506075013';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function service(?array $odbData, ?Exception $throw = null): RefIdVerificationService
    {
        $octopus = Mockery::mock(OctopusApiService::class);
        $expectation = $octopus->shouldReceive('verifyRefIdByIc');

        $throw ? $expectation->andThrow($throw) : $expectation->andReturn($odbData);

        return new RefIdVerificationService($octopus, new IcNormalizerService);
    }

    private function leeRefCustomer(): array
    {
        return [
            'customer_id' => 489303,
            'customer_name' => 'Lee Chee Chim',
            'ic' => self::LEE_IC,
            'blood_test_sales_id' => 124856,
            'date' => '2026-10-12 03:00:47',
            'outlet_id' => 21,
        ];
    }

    private function tanSale(int $saleId = 124586, string $campaignDate = '2026-10-17'): array
    {
        return [
            'customer_id' => 488859,
            'customer_name' => 'Tan Yong Jian',
            'ic' => self::TAN_IC,
            'blood_test_sales_id' => $saleId,
            'date' => '2026-09-06 05:04:30',
            'outlet_id' => 21,
            'campaign_id' => 10134,
            'campaign_date' => $campaignDate,
        ];
    }

    private function verifyTan(RefIdVerificationService $service, string $labNo = 'TEST-REFID-1'): array
    {
        return $service->verify('INN124856', 'INN', self::TAN_IC, 'TAN YONG JIAN', '2026-10-17 09:00:00', $labNo);
    }

    public function test_ref_id_owned_by_patient_is_verified(): void
    {
        $outcome = $this->service(['ref_customer' => $this->leeRefCustomer(), 'ic_sales' => []])
            ->verify('INN124856', 'INN', self::LEE_IC, 'LEE CHENG CHIM', '2026-10-17 09:30:00', 'TEST-REFID-2');

        $this->assertSame(['ref_id' => 'INN124856', 'status' => 'verified'], $outcome);
    }

    public function test_ic_typo_with_matching_name_is_verified(): void
    {
        $outcome = $this->service(['ref_customer' => $this->leeRefCustomer(), 'ic_sales' => []])
            ->verify('INN124856', 'INN', '810506075031', 'LEE CHEE CHIM', '2026-10-17 09:30:00', 'TEST-REFID-2');

        $this->assertSame('verified', $outcome['status']);
        $this->assertSame('INN124856', $outcome['ref_id']);
    }

    public function test_ref_id_of_another_customer_is_corrected_to_patients_own_sale(): void
    {
        $outcome = $this->verifyTan($this->service([
            'ref_customer' => $this->leeRefCustomer(),
            'ic_sales' => [$this->tanSale()],
        ]));

        $this->assertSame(['ref_id' => 'INN124586', 'status' => 'corrected'], $outcome);
    }

    public function test_mismatch_without_patient_sale_drops_ref_id(): void
    {
        $outcome = $this->verifyTan($this->service([
            'ref_customer' => $this->leeRefCustomer(),
            'ic_sales' => [],
        ]));

        $this->assertSame(['ref_id' => null, 'status' => 'unresolved'], $outcome);
    }

    public function test_mismatch_with_sale_from_another_campaign_date_drops_ref_id(): void
    {
        $outcome = $this->verifyTan($this->service([
            'ref_customer' => $this->leeRefCustomer(),
            'ic_sales' => [$this->tanSale(124586, '2026-10-16')],
        ]));

        $this->assertSame('unresolved', $outcome['status']);
        $this->assertNull($outcome['ref_id']);
    }

    public function test_mismatch_with_multiple_candidate_sales_drops_ref_id(): void
    {
        $outcome = $this->verifyTan($this->service([
            'ref_customer' => $this->leeRefCustomer(),
            'ic_sales' => [$this->tanSale(99999901), $this->tanSale(99999902)],
        ]));

        $this->assertSame('unresolved', $outcome['status']);
    }

    public function test_candidate_sale_already_used_by_another_lab_result_is_excluded(): void
    {
        $patient = Patient::create([
            'icno' => 'TESTREFID'.uniqid(),
            'ic_type' => Patient::IC_TYPE_OTHERS,
            'name' => 'REF ID TEST',
        ]);

        TestResult::create([
            'doctor_id' => Doctor::query()->value('id'),
            'patient_id' => $patient->id,
            'ref_id' => 'INN124586',
            'lab_no' => 'TEST-REFID-OTHER',
        ]);

        $outcome = $this->verifyTan($this->service([
            'ref_customer' => $this->leeRefCustomer(),
            'ic_sales' => [$this->tanSale()],
        ]));

        $this->assertSame('unresolved', $outcome['status']);
    }

    public function test_unknown_ref_id_without_replacement_is_kept(): void
    {
        $outcome = $this->verifyTan($this->service([
            'ref_customer' => null,
            'ic_sales' => [],
        ]));

        $this->assertSame(['ref_id' => 'INN124856', 'status' => 'not_found'], $outcome);
    }

    public function test_odb_failure_keeps_delivered_ref_id(): void
    {
        $outcome = $this->verifyTan($this->service(null, new Exception('Connection Failure')));

        $this->assertSame(['ref_id' => 'INN124856', 'status' => 'unavailable'], $outcome);
    }

    public function test_placeholder_ic_is_skipped(): void
    {
        $outcome = $this->service(null)
            ->verify('INN124856', 'INN', 'N/A_BATCH1', 'UNKNOWN', '2026-10-17 09:00:00', 'TEST-REFID-3');

        $this->assertSame(['ref_id' => 'INN124856', 'status' => 'skipped'], $outcome);
    }

    public function test_definitive_outcome_is_cached_per_lab_no(): void
    {
        $octopus = Mockery::mock(OctopusApiService::class);
        $octopus->shouldReceive('verifyRefIdByIc')->once()->andReturn([
            'ref_customer' => $this->leeRefCustomer(),
            'ic_sales' => [$this->tanSale()],
        ]);

        $service = new RefIdVerificationService($octopus, new IcNormalizerService);

        $this->assertSame('INN124586', $this->verifyTan($service)['ref_id']);
        $this->assertSame('INN124586', $this->verifyTan($service)['ref_id']);
    }
}

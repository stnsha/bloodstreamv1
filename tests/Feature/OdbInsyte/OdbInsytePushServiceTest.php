<?php

namespace Tests\Feature\OdbInsyte;

use App\Exceptions\OdbInsytePushException;
use App\Services\OdbInsytePushService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * HTTP behaviour of OdbInsytePushService::push() / getToken().
 * No database access: everything external is faked.
 */
class OdbInsytePushServiceTest extends TestCase
{
    private const LOGIN_URL = 'http://odb.test/odb/InSyte/api/inbound/login.php';

    private const PUSH_URL = 'http://odb.test/odb/InSyte/api/inbound/receive_and_trigger.php';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.odb_insyte.login_url' => self::LOGIN_URL,
            'services.odb_insyte.push_url' => self::PUSH_URL,
            'credentials.odb_insyte.username' => 'push_user',
            'credentials.odb_insyte.password' => 'push_pass',
        ]);

        Cache::forget('odb_insyte_push_token');
    }

    private function payload(): array
    {
        return [
            'icno' => '900101011234',
            'patient' => ['id' => 1, 'name' => 'TEST', 'dob' => '19900101', 'gender' => 'M', 'age' => '36'],
            'blood_test' => [[
                'collected_date' => '2026-09-01',
                'lab_no' => '26-0000001',
                'items' => [['name' => 'Glycated Haemoglobin', 'value' => '6.1', 'unit' => '%']],
            ]],
            'check_record' => (object) [],
            'remarks' => [],
        ];
    }

    private function successBody(array $overrides = []): array
    {
        return array_merge([
            'success' => true,
            'patient_id' => 39,
            'patient_created' => false,
            'saved' => 1,
            'unmapped_blood_test' => [],
            'unmapped_check_record' => [],
            'unit_mismatch' => [],
            'remarks_received' => 0,
            'remarks_skipped' => 0,
            'queued' => true,
            'queue_id' => 15,
            'queue_position' => 1,
            'already_waiting' => false,
        ], $overrides);
    }

    public function test_push_logs_in_caches_token_and_sends_bearer(): void
    {
        Http::fake([
            self::LOGIN_URL => Http::response(['success' => true, 'token' => 'tok-1', 'expires_in' => 86400]),
            self::PUSH_URL => Http::response($this->successBody()),
        ]);

        $service = app(OdbInsytePushService::class);

        $first = $service->push($this->payload());
        $service->push($this->payload());

        $this->assertTrue($first['queued']);
        $this->assertSame('tok-1', Cache::get('odb_insyte_push_token'));

        // One login, two pushes, both with the cached bearer token.
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $r) => $r->url() === self::LOGIN_URL
            && $r['username'] === 'push_user' && $r['password'] === 'push_pass');
        Http::assertSent(fn (Request $r) => $r->url() === self::PUSH_URL
            && $r->hasHeader('Authorization', 'Bearer tok-1')
            && $r['icno'] === '900101011234');
    }

    public function test_push_relogs_and_retries_once_on_401(): void
    {
        Cache::put('odb_insyte_push_token', 'stale', 3600);

        Http::fake([
            self::LOGIN_URL => Http::response(['success' => true, 'token' => 'fresh', 'expires_in' => 86400]),
            self::PUSH_URL => Http::sequence()
                ->push(['success' => false, 'error' => 'expired token'], 401)
                ->push($this->successBody()),
        ]);

        $response = app(OdbInsytePushService::class)->push($this->payload());

        $this->assertTrue($response['success']);
        $this->assertSame('fresh', Cache::get('odb_insyte_push_token'));
        Http::assertSent(fn (Request $r) => $r->url() === self::PUSH_URL && $r->hasHeader('Authorization', 'Bearer fresh'));
    }

    public function test_client_errors_are_not_retryable(): void
    {
        Http::fake([
            self::LOGIN_URL => Http::response(['success' => true, 'token' => 'tok', 'expires_in' => 86400]),
            self::PUSH_URL => Http::response(['success' => false, 'error' => 'dob is not YYYYMMDD'], 422),
        ]);

        try {
            app(OdbInsytePushService::class)->push($this->payload());
            $this->fail('Expected OdbInsytePushException');
        } catch (OdbInsytePushException $e) {
            $this->assertSame(422, $e->httpStatus);
            $this->assertFalse($e->retryable);
        }
    }

    public function test_server_errors_are_retryable(): void
    {
        Http::fake([
            self::LOGIN_URL => Http::response(['success' => true, 'token' => 'tok', 'expires_in' => 86400]),
            self::PUSH_URL => Http::response(['success' => false, 'error' => 'queue not set up'], 503),
        ]);

        try {
            app(OdbInsytePushService::class)->push($this->payload());
            $this->fail('Expected OdbInsytePushException');
        } catch (OdbInsytePushException $e) {
            $this->assertSame(503, $e->httpStatus);
            $this->assertTrue($e->retryable);
        }
    }

    public function test_missing_configuration_is_not_retryable(): void
    {
        config(['credentials.odb_insyte.password' => null]);

        try {
            app(OdbInsytePushService::class)->getToken();
            $this->fail('Expected OdbInsytePushException');
        } catch (OdbInsytePushException $e) {
            $this->assertFalse($e->retryable);
        }
    }
}

<?php

namespace App\Services;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OctopusApiService
{
    /**
     * ODB API base URL.
     */
    protected string $baseUrl;

    /**
     * ODB API credentials.
     */
    protected string $username;
    protected string $password;

    /**
     * HTTP request timeout in seconds.
     */
    protected int $timeout = 30;

    public function __construct()
    {
        $this->baseUrl = config('services.octopus.api_url') ?? '';
        $this->username = config('credentials.odb_api.username') ?? '';
        $this->password = config('credentials.odb_api.password') ?? '';
    }

    /**
     * Call ODB API using Laravel HTTP client.
     *
     * @param string $method HTTP method (POST, GET, PUT)
     * @param string $endpoint API endpoint path
     * @param array $data Request data
     * @return array Decoded JSON response
     * @throws Exception
     */
    protected function callAPI(string $method, string $endpoint, array $data): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        Log::info('OctopusApiService: API Request', [
            'method' => $method,
            'url' => $url,
            'data_keys' => array_keys($data),
        ]);

        try {
            $http = Http::timeout($this->timeout)
                ->acceptJson()
                ->asJson();

            switch (strtoupper($method)) {
                case 'POST':
                    $response = $http->post($url, $data);
                    break;
                case 'PUT':
                    $response = $http->put($url, $data);
                    break;
                case 'GET':
                default:
                    $response = $http->get($url, $data);
                    break;
            }

            $httpCode = $response->status();
            $body = $response->body();

            Log::info('OctopusApiService: API Response', [
                'method' => $method,
                'url' => $url,
                'http_code' => $httpCode,
                'response_length' => strlen($body),
            ]);

            if ($response->failed()) {
                Log::error('OctopusApiService: API returned error status', [
                    'method' => $method,
                    'url' => $url,
                    'http_code' => $httpCode,
                    'response_body' => substr($body, 0, 500),
                ]);

                throw new Exception("API request failed with status {$httpCode}: " . substr($body, 0, 200));
            }

            $result = $response->json();

            if ($result === null && !empty($body)) {
                Log::error('OctopusApiService: Invalid JSON response', [
                    'response' => substr($body, 0, 500),
                ]);

                throw new Exception('Invalid JSON response from ODB API');
            }

            return $result ?? [];

        } catch (ConnectionException $e) {
            Log::error('OctopusApiService: Connection Failure', [
                'method' => $method,
                'url' => $url,
                'error_message' => $e->getMessage(),
            ]);

            throw new Exception("Connection Failure: " . $e->getMessage(), 0, $e);

        } catch (RequestException $e) {
            Log::error('OctopusApiService: Request Exception', [
                'method' => $method,
                'url' => $url,
                'error_message' => $e->getMessage(),
                'http_code' => $e->response ? $e->response->status() : null,
            ]);

            throw new Exception("Request failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Perform fuzzy search for customer candidates.
     *
     * @param array $params Search parameters
     * @return array Response with exact_match or candidates
     * @throws Exception
     */
    public function fuzzySearch(array $params): array
    {
        Log::info('OctopusApiService: Starting fuzzy search', [
            'ic' => $params['ic'] ?? null,
            'refid' => $params['refid'] ?? null,
            'lab_code' => $params['lab_code'] ?? null,
        ]);

        $data = [
            'username' => $this->username,
            'password' => $this->password,
            'search_type' => 'fuzzy',
            'ic' => $params['ic'] ?? '',
            'ic_normalized' => $params['ic_normalized'] ?? '',
            'dob' => $params['dob'] ?? '',
            'gender' => $params['gender'] ?? '',
            'refid' => $params['refid'] ?? '',
            'lab_code' => $params['lab_code'] ?? '',
            'max_candidates' => $params['max_candidates'] ?? 10,
        ];

        try {
            $result = $this->callAPI('POST', '/customerFuzzySearch.php', $data);

            Log::info('OctopusApiService: Fuzzy search completed', [
                'ic' => $params['ic'] ?? null,
                'has_exact_match' => isset($result['exact_match']) && $result['exact_match'] !== null,
                'candidate_count' => count($result['candidates'] ?? []),
            ]);

            return [
                'status' => $result['status'] ?? 'error',
                'exact_match' => $result['exact_match'] ?? null,
                'candidates' => $result['candidates'] ?? [],
                'search_criteria_used' => $result['search_criteria_used'] ?? [],
                'total_found' => $result['total_found'] ?? 0,
            ];

        } catch (Exception $e) {
            Log::error('OctopusApiService: Fuzzy search exception', [
                'error' => $e->getMessage(),
                'ic' => $params['ic'] ?? null,
            ]);

            throw $e;
        }
    }

    /**
     * Look up customer by blood_test_sales reference ID.
     *
     * @param string $refId The reference ID (e.g., INN10256)
     * @param string|null $labCode The lab code prefix (e.g., INN)
     * @return array|null Customer data or null if not found
     * @throws Exception
     */
    public function customerByRefId(string $refId, ?string $labCode = null): ?array
    {
        Log::info('OctopusApiService: Looking up customer by RefID', [
            'refid' => $refId,
            'lab_code' => $labCode,
        ]);

        $data = [
            'username' => $this->username,
            'password' => $this->password,
            'refid' => $refId,
            'lab_code' => $labCode ?? '',
        ];

        try {
            $result = $this->callAPI('POST', '/customerByRefId.php', $data);

            if (($result['status'] ?? '') !== 'success' || !isset($result['customer'])) {
                Log::info('OctopusApiService: Customer not found by RefID', [
                    'refid' => $refId,
                ]);

                return null;
            }

            Log::info('OctopusApiService: Customer found by RefID', [
                'refid' => $refId,
                'customer_id' => $result['customer']['customer_id'] ?? null,
                'blood_test_sales_date' => $result['customer']['date'] ?? null,
            ]);

            return $result['customer'];

        } catch (Exception $e) {
            Log::error('OctopusApiService: RefID lookup exception', [
                'error' => $e->getMessage(),
                'refid' => $refId,
            ]);

            throw $e;
        }
    }

    /**
     * Look up customer by blood_test_sales reference ID.
     * Alias for customerByRefId for backward compatibility.
     *
     * @param string $refId The reference ID (e.g., INN10256)
     * @param string|null $labCode The lab code prefix (e.g., INN)
     * @return array|null Customer data or null if not found
     * @throws Exception
     */
    public function getCustomerByRefId(string $refId, ?string $labCode = null): ?array
    {
        return $this->customerByRefId($refId, $labCode);
    }

    /**
     * Look up outlet details by outlet ID.
     * Returns outlet data including regional name from outlet_regional table.
     *
     * @param int $outletId The outlet ID
     * @return array|null Outlet data or null if not found
     * @throws Exception
     */
    public function outletById(int $outletId): ?array
    {
        Log::info('OctopusApiService: Looking up outlet by ID', [
            'outlet_id' => $outletId,
        ]);

        $data = [
            'username'  => $this->username,
            'password'  => $this->password,
            'outlet_id' => $outletId,
        ];

        try {
            $result = $this->callAPI('POST', '/outletById.php', $data);

            if (empty($result) || ! isset($result[0])) {
                Log::info('OctopusApiService: Outlet not found', [
                    'outlet_id' => $outletId,
                ]);

                return null;
            }

            Log::info('OctopusApiService: Outlet found', [
                'outlet_id' => $outletId,
                'comp_name' => $result[0]['comp_name'] ?? null,
                'regional'  => $result[0]['regional'] ?? null,
            ]);

            return $result[0];

        } catch (Exception $e) {
            Log::error('OctopusApiService: Outlet lookup exception', [
                'error'     => $e->getMessage(),
                'outlet_id' => $outletId,
            ]);

            throw $e;
        }
    }

    /**
     * Look up eligible consult call customer by reference ID across all outlets.
     * Returns null if the ref ID does not match an eligible customer.
     * 
     * Updated outlet:
     * Melaka (Regional ID 6, outlet code starting with 'M') 
     * Johor (Regional ID 11)
     * Kelantan (Regional ID 5)
     *
     * @param string $refId The reference ID (e.g., INN10256)
     * @param string|null $labCode The lab code prefix used for normalization (e.g., INN)
     * @return array|null Customer data or null if not found
     * @throws Exception
     */
    public function eligibleConsultCallByOutlet(string $refId, ?string $labCode = null): ?array
    {
        Log::info('OctopusApiService: Looking up eligible consult call customer by RefID', [
            'refid'    => $refId,
            'lab_code' => $labCode,
        ]);

        $data = [
            'username' => $this->username,
            'password' => $this->password,
            'refid'    => $refId,
            'lab_code' => $labCode ?? '',
        ];

        try {
            $result = $this->callAPI('POST', '/eligibleConsultCallByOutlet.php', $data);

            if (($result['status'] ?? '') !== 'success' || ! isset($result['customer'])) {
                Log::info('OctopusApiService: Eligible consult call customer not found by RefID', [
                    'refid'   => $refId,
                    'message' => $result['message'] ?? null,
                ]);

                return null;
            }

            Log::info('OctopusApiService: Eligible consult call customer found by RefID', [
                'refid'       => $refId,
                'customer_id' => $result['customer']['customer_id'] ?? null,
                'outlet_id'   => $result['customer']['outlet_id'] ?? null,
            ]);

            return $result['customer'];

        } catch (Exception $e) {
            Log::error('OctopusApiService: Eligible consult call RefID lookup exception', [
                'error' => $e->getMessage(),
                'refid' => $refId,
            ]);

            throw $e;
        }
    }

    /**
     * Look up eligible consult call customer by patient IC across non-Melaka outlets (regional_id IN 11, 5).
     * Returns null if no matching customer is found in those regions.
     *
     * @param string $patientIc The patient IC number
     * @return array|null Customer data or null if not found
     * @throws Exception
     */
    public function eligibleConsultCallByOutletIc(string $patientIc): ?array
    {
        Log::info('OctopusApiService: Looking up eligible consult call customer by patient IC', [
            'patient_ic' => $patientIc,
        ]);

        $data = [
            'username'   => $this->username,
            'password'   => $this->password,
            'patient_ic' => $patientIc,
        ];

        try {
            $result = $this->callAPI('POST', '/eligibleConsultCallByOutlet.php', $data);

            if (($result['status'] ?? '') !== 'success' || ! isset($result['customer'])) {
                Log::info('OctopusApiService: Eligible consult call customer not found by patient IC', [
                    'patient_ic' => $patientIc,
                    'message'    => $result['message'] ?? null,
                ]);

                return null;
            }

            Log::info('OctopusApiService: Eligible consult call customer found by patient IC', [
                'patient_ic'  => $patientIc,
                'customer_id' => $result['customer']['customer_id'] ?? null,
                'outlet_id'   => $result['customer']['outlet_id'] ?? null,
            ]);

            return $result['customer'];

        } catch (Exception $e) {
            Log::error('OctopusApiService: Eligible consult call patient IC lookup exception', [
                'error'      => $e->getMessage(),
                'patient_ic' => $patientIc,
            ]);

            throw $e;
        }
    }

    /**
     * Look up customer by blood_test_sales reference ID, restricted to Melaka outlets.
     * Returns null if the ref ID does not belong to a Melaka outlet (regional_id = 6,
     * outlet code starting with 'M').
     *
     * @param string $refId The reference ID (e.g., INN10256)
     * @param string|null $labCode The lab code prefix used for normalization (e.g., INN)
     * @return array|null Customer data or null if not found or not a Melaka customer
     * @throws Exception
     */
    public function customerMelakaByRefId(string $refId, ?string $labCode = null): ?array
    {
        Log::info('OctopusApiService: Looking up Melaka customer by RefID', [
            'refid'    => $refId,
            'lab_code' => $labCode,
        ]);

        $data = [
            'username' => $this->username,
            'password' => $this->password,
            'refid'    => $refId,
            'lab_code' => $labCode ?? '',
        ];

        try {
            $result = $this->callAPI('POST', '/customerMelakaByRefId.php', $data);

            if (($result['status'] ?? '') !== 'success' || ! isset($result['customer'])) {
                Log::info('OctopusApiService: Melaka customer not found by RefID', [
                    'refid'   => $refId,
                    'message' => $result['message'] ?? null,
                ]);

                return null;
            }

            Log::info('OctopusApiService: Melaka customer found by RefID', [
                'refid'       => $refId,
                'customer_id' => $result['customer']['customer_id'] ?? null,
                'outlet_id'   => $result['customer']['outlet_id'] ?? null,
                'date'        => $result['customer']['date'] ?? null,
            ]);

            return $result['customer'];

        } catch (Exception $e) {
            Log::error('OctopusApiService: Melaka RefID lookup exception', [
                'error' => $e->getMessage(),
                'refid' => $refId,
            ]);

            throw $e;
        }
    }

    /**
     * Look up customer by exact IC number.
     *
     * @param string $ic The IC number
     * @param string|null $labCode The lab code prefix (e.g., INN) to return prefixed refid
     * @return array|null Customer data or null if not found
     * @throws Exception
     */
    public function getCustomerByIc(string $ic, ?string $labCode = null): ?array
    {
        Log::info('OctopusApiService: Looking up customer by IC', [
            'ic' => $ic,
            'lab_code' => $labCode,
        ]);

        $result = $this->fuzzySearch([
            'ic' => $ic,
            'ic_normalized' => $ic,
            'lab_code' => $labCode ?? '',
        ]);

        if ($result['exact_match']) {
            return $result['exact_match'];
        }

        return null;
    }

    /**
     * Get blood_test_sales records for a customer.
     *
     * @param int $customerId The Octopus customer ID
     * @return array List of sales records [['id' => int, 'date' => string], ...]
     * @throws Exception
     */
    public function getBloodTestSalesByCustomerId(int $customerId): array
    {
        Log::info('OctopusApiService: Fetching blood_test_sales by customer ID', [
            'customer_id' => $customerId,
        ]);

        $data = [
            'username' => $this->username,
            'password' => $this->password,
            'customer_id' => $customerId,
        ];

        try {
            $result = $this->callAPI('POST', '/customerSalesByCustomerId.php', $data);

            if (($result['status'] ?? '') !== 'success') {
                Log::warning('OctopusApiService: Sales lookup returned non-success status', [
                    'customer_id' => $customerId,
                    'status' => $result['status'] ?? null,
                    'message' => $result['message'] ?? null,
                ]);

                return [];
            }

            $sales = $result['sales'] ?? [];

            Log::info('OctopusApiService: Blood test sales fetched successfully', [
                'customer_id' => $customerId,
                'sales_count' => count($sales),
            ]);

            return $sales;

        } catch (Exception $e) {
            Log::error('OctopusApiService: Blood test sales lookup exception', [
                'error' => $e->getMessage(),
                'customer_id' => $customerId,
            ]);

            throw $e;
        }
    }

    /**
     * Test API connection.
     *
     * @return bool True if connection is successful
     */
    public function testConnection(): bool
    {
        try {
            $data = [
                'username' => $this->username,
                'password' => $this->password,
                'search_type' => 'test',
            ];

            $result = $this->callAPI('POST', '/customerFuzzySearch.php', $data);

            return !empty($result);

        } catch (Exception $e) {
            Log::error('OctopusApiService: Connection test failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Set custom base URL (useful for testing).
     *
     * @param string $url The base URL
     * @return self
     */
    public function setBaseUrl(string $url): self
    {
        $this->baseUrl = $url;
        return $this;
    }

    /**
     * Set custom credentials (useful for testing).
     *
     * @param string $username The username
     * @param string $password The password
     * @return self
     */
    public function setCredentials(string $username, string $password): self
    {
        $this->username = $username;
        $this->password = $password;
        return $this;
    }

    /**
     * Set HTTP request timeout.
     *
     * @param int $seconds Timeout in seconds
     * @return self
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }
}

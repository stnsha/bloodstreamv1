<?php

namespace App\Services;

use Exception;
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

    public function __construct()
    {
        $this->baseUrl = config('services.octopus.api_url', '');
        $this->username = config('credentials.odb_api.username', '');
        $this->password = config('credentials.odb_api.password', '');
    }

    /**
     * Call ODB API using cURL.
     *
     * @param string $method HTTP method (POST, GET, PUT)
     * @param string $url Full API URL
     * @param array $data Request data
     * @return string Raw response
     * @throws Exception
     */
    protected function callAPI(string $method, string $url, array $data): string
    {
        $curl = curl_init();

        $jsonData = json_encode($data);

        switch ($method) {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);
                if ($jsonData) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
                }
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                if ($jsonData) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
                }
                break;
            default:
                if ($data) {
                    $url = sprintf("%s?%s", $url, http_build_query($data));
                }
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);

        Log::info('OctopusApiService: API Request', [
            'method' => $method,
            'url' => $url,
            'data_length' => strlen($jsonData),
        ]);

        $result = curl_exec($curl);

        if (!$result) {
            $error = curl_error($curl);
            $errno = curl_errno($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            Log::error('OctopusApiService: Connection Failure', [
                'method' => $method,
                'url' => $url,
                'error_code' => $errno,
                'error_message' => $error,
                'http_code' => $httpCode,
            ]);

            curl_close($curl);
            throw new Exception("Connection Failure: [{$errno}] {$error}");
        }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        Log::info('OctopusApiService: API Response', [
            'method' => $method,
            'url' => $url,
            'http_code' => $httpCode,
            'response_length' => strlen($result),
        ]);

        curl_close($curl);

        return $result;
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

        $data = array(
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
        );

        $apiUrl = $this->baseUrl . '/customerFuzzySearch.php';

        try {
            $response = $this->callAPI('POST', $apiUrl, $data);
            $result = json_decode($response, true);

            if ($result === null) {
                Log::error('OctopusApiService: Invalid JSON response', [
                    'response' => substr($response, 0, 500),
                ]);
                throw new Exception('Invalid JSON response from ODB API');
            }

            Log::info('OctopusApiService: Fuzzy search completed', [
                'ic' => $params['ic'] ?? null,
                'has_exact_match' => isset($result['exact_match']) && $result['exact_match'] !== null,
                'candidate_count' => count($result['candidates'] ?? array()),
            ]);

            return array(
                'status' => $result['status'] ?? 'error',
                'exact_match' => $result['exact_match'] ?? null,
                'candidates' => $result['candidates'] ?? array(),
                'search_criteria_used' => $result['search_criteria_used'] ?? array(),
                'total_found' => $result['total_found'] ?? 0,
            );
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
    public function getCustomerByRefId(string $refId, ?string $labCode = null): ?array
    {
        Log::info('OctopusApiService: Looking up customer by RefID', [
            'refid' => $refId,
            'lab_code' => $labCode,
        ]);

        $data = array(
            'username' => $this->username,
            'password' => $this->password,
            'refid' => $refId,
            'lab_code' => $labCode ?? '',
        );

        $apiUrl = $this->baseUrl . '/customerByRefId.php';

        try {
            $response = $this->callAPI('POST', $apiUrl, $data);
            $result = json_decode($response, true);

            if (($result['status'] ?? '') !== 'success' || !isset($result['customer'])) {
                Log::info('OctopusApiService: Customer not found by RefID', [
                    'refid' => $refId,
                ]);

                return null;
            }

            Log::info('OctopusApiService: Customer found by RefID', [
                'refid' => $refId,
                'customer_id' => $result['customer']['customer_id'] ?? null,
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
     * Look up customer by exact IC number.
     *
     * @param string $ic The IC number
     * @return array|null Customer data or null if not found
     * @throws Exception
     */
    public function getCustomerByIc(string $ic): ?array
    {
        Log::info('OctopusApiService: Looking up customer by IC', [
            'ic' => $ic,
        ]);

        $result = $this->fuzzySearch(array(
            'ic' => $ic,
            'ic_normalized' => $ic,
        ));

        if ($result['exact_match']) {
            return $result['exact_match'];
        }

        return null;
    }

    /**
     * Test API connection.
     *
     * @return bool True if connection is successful
     */
    public function testConnection(): bool
    {
        try {
            $data = array(
                'username' => $this->username,
                'password' => $this->password,
                'search_type' => 'test',
            );

            $apiUrl = $this->baseUrl . '/customerFuzzySearch.php';
            $response = $this->callAPI('POST', $apiUrl, $data);

            return !empty($response);
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
}

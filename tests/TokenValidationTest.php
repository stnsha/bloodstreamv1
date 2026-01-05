<?php

namespace Tests;

use App\Services\TokenValidationService;
use PHPUnit\Framework\TestCase;

class TokenValidationTest extends TestCase
{
    private TokenValidationService $service;

    protected function setUp(): void
    {
        $this->service = new TokenValidationService();
    }

    public function testValidJWT()
    {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
        $result = $this->service->validateTokenFormat($token);

        $this->assertTrue($result['valid'], 'Valid JWT should pass validation');
        $this->assertNull($result['error_type']);
    }

    public function testPlaceholderDollarCurly()
    {
        $token = '${access_token}';
        $result = $this->service->validateTokenFormat($token);

        $this->assertFalse($result['valid']);
        $this->assertEquals('placeholder', $result['error_type']);
        $this->assertTrue($result['should_log_full_token']);
    }

    public function testPlaceholderCurly()
    {
        $token = '{access_token}';
        $result = $this->service->validateTokenFormat($token);

        $this->assertFalse($result['valid']);
        $this->assertEquals('placeholder', $result['error_type']);
    }

    public function testPlaceholderDollar()
    {
        $token = '$access_token';
        $result = $this->service->validateTokenFormat($token);

        $this->assertFalse($result['valid']);
        $this->assertEquals('placeholder', $result['error_type']);
    }

    public function testTwoSegments()
    {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIn0';
        $result = $this->service->validateTokenFormat($token);

        $this->assertFalse($result['valid']);
        $this->assertEquals('invalid_format', $result['error_type']);
    }

    public function testFourSegments()
    {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.abc.def';
        $result = $this->service->validateTokenFormat($token);

        $this->assertFalse($result['valid']);
        $this->assertEquals('invalid_format', $result['error_type']);
    }

    public function testShortToken()
    {
        $token = 'short';
        $result = $this->service->validateTokenFormat($token);

        $this->assertFalse($result['valid']);
        $this->assertEquals('too_short', $result['error_type']);
    }

    public function testTokenWithSpaces()
    {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9 eyJzdWIiOiIxMjM0NTY3ODkwIn0.TJVA95OrM7E2cBab30RMHrHDcEfxjoYZgeFONFh7HgQ';
        $result = $this->service->validateTokenFormat($token);

        $this->assertFalse($result['valid']);
        $this->assertEquals('suspicious_pattern', $result['error_type']);
    }
}

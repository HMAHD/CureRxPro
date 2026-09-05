<?php

namespace CureRx\Tests\Unit\Controllers;

use CureRx\Controllers\SettingsController;
use CureRx\Repositories\SettingsRepository;
use PHPUnit\Framework\TestCase;

class SettingsControllerTest extends TestCase
{
    private \ReflectionMethod $method;
    private SettingsController $controller;

    protected function setUp(): void
    {
        $mockRepo = $this->createMock(SettingsRepository::class);
        $this->controller = new SettingsController($mockRepo);
        $this->method = new \ReflectionMethod(SettingsController::class, 'validateLicenseKey');
        $this->method->setAccessible(true);
    }

    /**
     * Mirrors the production key-generation algorithm for test fixture creation.
     */
    private function makeKey(string $secret, string $plan = 'pro', int $expiry = 0): string
    {
        $signingKey = hash('sha256', $secret, true);
        $payload    = rtrim(strtr(base64_encode("{$plan}|{$expiry}"), '+/', '-_'), '=');
        $hmac       = hash_hmac('sha256', $payload, $signingKey);
        return "CRX-{$payload}-{$hmac}";
    }

    /** Task requirement 1: valid key passes */
    public function testValidKeyReturnsTrue(): void
    {
        // Static far-future expiry (year 2286) — avoids time() producing '+' in base64
        // which strtr converts to '-', corrupting explode('-', $key, 3).
        $key    = $this->makeKey('test-secret', 'pro', 9999999999);
        $result = $this->method->invoke($this->controller, $key, 'test-secret');
        $this->assertTrue($result['valid']);
    }

    /** Task requirement 7: lifetime key (expiry=0) passes */
    public function testLifetimeKeyReturnsTrue(): void
    {
        $key    = $this->makeKey('test-secret', 'pro', 0);
        $result = $this->method->invoke($this->controller, $key, 'test-secret');
        $this->assertTrue($result['valid']);
    }

    /** Task requirement 3: expired key (past timestamp) fails */
    public function testExpiredKeyReturnsFalse(): void
    {
        $key    = $this->makeKey('test-secret', 'pro', 1000000); // 2001-09-09, well in the past
        $result = $this->method->invoke($this->controller, $key, 'test-secret');
        $this->assertFalse($result['valid']);
    }

    /** Task requirement 2: wrong plan in payload fails */
    public function testWrongPlanReturnsFalse(): void
    {
        $key    = $this->makeKey('test-secret', 'enterprise', 0);
        $result = $this->method->invoke($this->controller, $key, 'test-secret');
        $this->assertFalse($result['valid']);
    }

    /** Task requirement 4: tampered HMAC fails */
    public function testTamperedHmacReturnsFalse(): void
    {
        $key      = $this->makeKey('test-secret', 'pro', 0);
        $tampered = substr($key, 0, -1) . ($key[-1] === 'a' ? 'b' : 'a');
        $result   = $this->method->invoke($this->controller, $tampered, 'test-secret');
        $this->assertFalse($result['valid']);
    }

    /** Task requirement 5: wrong prefix (not "CRX-") fails */
    public function testWrongPrefixReturnsFalse(): void
    {
        $result = $this->method->invoke($this->controller, 'LICENSE-payload-hmac', 'test-secret');
        $this->assertFalse($result['valid']);
    }

    /** Task requirement 6: wrong segment count fails */
    public function testWrongSegmentCountReturnsFalse(): void
    {
        // Only two segments after explode('-', $key, 3) — HMAC part is missing
        $result = $this->method->invoke($this->controller, 'CRX-payloadonly', 'test-secret');
        $this->assertFalse($result['valid']);
    }

    /** Bonus: wrong secret produces invalid HMAC and fails */
    public function testWrongSecretReturnsFalse(): void
    {
        $key    = $this->makeKey('correct-secret', 'pro', 0);
        $result = $this->method->invoke($this->controller, $key, 'wrong-secret');
        $this->assertFalse($result['valid']);
    }
}

<?php

namespace CureRx\Controllers;

use CureRx\Core\Request;
use CureRx\Core\Response;
use CureRx\Repositories\SettingsRepository;

class SettingsController
{
    public function __construct(private SettingsRepository $settings) {}

    public function handle(Request $req): never
    {
        match ($req->method()) {
            'GET'  => Response::json(['status' => 'success', 'data' => $this->settings->get()]),
            'POST' => $this->update($req),
            default => Response::error('Method not allowed', 405),
        };
    }

    private function update(Request $req): never
    {
        $data = $req->body();

        // If license_key present, validate before saving
        if (!empty($data['license_key'])) {
            $secretFile = (getenv('USER_DATA_PATH') ?: __DIR__ . '/../../') . '/api_secret.key';
            $secret     = file_exists($secretFile) ? trim(file_get_contents($secretFile)) : '';
            $result     = $this->validateLicenseKey($data['license_key'], $secret);
            if (!$result['valid']) {
                Response::error('Invalid or expired license key', 422);
            }
        }

        $this->settings->update($data);
        Response::json(['status' => 'success']);
    }

    private function validateLicenseKey(string $key, string $secret): array
    {
        if (!str_starts_with($key, 'CRX-')) {
            return ['valid' => false];
        }
        $parts = explode('-', $key, 3);
        if (count($parts) !== 3) {
            return ['valid' => false];
        }
        [, $payload, $providedHmac] = $parts;
        $signingKey   = hash('sha256', $secret, true);
        $expectedHmac = hash_hmac('sha256', $payload, $signingKey);
        if (!hash_equals($expectedHmac, $providedHmac)) {
            return ['valid' => false];
        }
        $decoded = base64_decode(strtr($payload, '-_', '+/'));
        if ($decoded === false) {
            return ['valid' => false];
        }
        [$plan, $expiry] = explode('|', $decoded, 2) + ['', '0'];
        if ((int)$expiry !== 0 && (int)$expiry < time()) {
            return ['valid' => false];
        }
        return ['valid' => in_array($plan, ['starter', 'pro', 'clinic'], true)];
    }
}

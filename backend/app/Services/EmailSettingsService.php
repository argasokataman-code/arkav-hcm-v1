<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class EmailSettingsService
{
    private const ENCRYPTED_PREFIX = 'enc::';

    /**
     * @return array<string, mixed>
     */
    public function getProfile(): array
    {
        $provider = (string) Setting::get('email_provider', 'mailtrap');
        $fromAddress = $this->normalizeNullableString(Setting::get('email_from_address'));
        $fromName = $this->normalizeNullableString(Setting::get('email_from_name'));

        $smtpHost = $this->normalizeNullableString(Setting::get('email_smtp_host'));
        $smtpPort = (int) (Setting::get('email_smtp_port', 587) ?? 587);
        $smtpEncryption = $this->normalizeNullableString(Setting::get('email_smtp_encryption'));
        $smtpUsername = $this->normalizeNullableString(Setting::get('email_smtp_username'));
        $smtpPassword = $this->readSecret('email_smtp_password');

        $mailtrapAccountId = $this->normalizeNullableInteger(Setting::get('email_mailtrap_account_id'));
        $mailtrapApiToken = $this->readSecret('email_mailtrap_api_token');

        return [
            'provider' => $provider !== '' ? $provider : 'mailtrap',
            'fromAddress' => $fromAddress,
            'fromName' => $fromName,
            'smtp' => [
                'host' => $smtpHost,
                'port' => $smtpPort > 0 ? $smtpPort : 587,
                'encryption' => $smtpEncryption,
                'username' => $smtpUsername,
                'passwordMasked' => $this->maskSecret($smtpPassword),
                'configured' => $smtpHost !== null && $smtpUsername !== null && $smtpPassword !== null,
            ],
            'mailtrap' => [
                'accountId' => $mailtrapAccountId,
                'apiTokenMasked' => $this->maskSecret($mailtrapApiToken),
                'configured' => $mailtrapAccountId !== null && $mailtrapApiToken !== null,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param mixed $user
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function updateProfile(array $data, mixed $user = null): array
    {
        Setting::set('email_provider', $data['provider'], 'email');
        Setting::set('email_from_address', $this->normalizeNullableString($data['fromAddress'] ?? null), 'email');
        Setting::set('email_from_name', $this->normalizeNullableString($data['fromName'] ?? null), 'email');

        if (array_key_exists('smtp', $data)) {
            $smtp = is_array($data['smtp']) ? $data['smtp'] : [];
            Setting::set('email_smtp_host', $this->normalizeNullableString($smtp['host'] ?? null), 'email');
            Setting::set('email_smtp_port', (string) ((int) ($smtp['port'] ?? 587)), 'email');
            Setting::set('email_smtp_encryption', $this->normalizeEncryption($smtp['encryption'] ?? null), 'email');
            Setting::set('email_smtp_username', $this->normalizeNullableString($smtp['username'] ?? null), 'email');

            if (array_key_exists('password', $smtp)) {
                $this->writeSecret('email_smtp_password', $this->normalizeNullableString($smtp['password'] ?? null));
            }
        }

        if (array_key_exists('mailtrap', $data)) {
            $mailtrap = is_array($data['mailtrap']) ? $data['mailtrap'] : [];
            $accountId = (int) ($mailtrap['accountId'] ?? 0);
            Setting::set('email_mailtrap_account_id', $accountId > 0 ? (string) $accountId : null, 'email');

            if (array_key_exists('apiToken', $mailtrap)) {
                $this->writeSecret('email_mailtrap_api_token', $this->normalizeNullableString($mailtrap['apiToken'] ?? null));
            }
        }

        $updatedAt = now()->toIso8601String();
        Setting::set('email_last_updated_by_id', isset($user?->id) ? (string) $user->id : null, 'email');
        Setting::set('email_last_updated_by_uuid', isset($user?->uuid) ? (string) $user->uuid : null, 'email');
        Setting::set('email_last_updated_by_email', isset($user?->email) ? (string) $user->email : null, 'email');
        Setting::set('email_last_updated_at', $updatedAt, 'email');

        return [
            'data' => $this->getProfile(),
            'meta' => [
                'updatedBy' => [
                    'id' => $this->normalizeNullableInteger(Setting::get('email_last_updated_by_id')),
                    'uuid' => $this->normalizeNullableString(Setting::get('email_last_updated_by_uuid')),
                    'email' => $this->normalizeNullableString(Setting::get('email_last_updated_by_email')),
                ],
                'updatedAt' => $this->normalizeNullableString(Setting::get('email_last_updated_at')),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @param mixed $user
     * @return array<string, mixed>
     */
    public function persistTestConnectionSnapshot(array $result, mixed $user = null): array
    {
        Setting::set('email_last_test_provider', $this->normalizeNullableString($result['provider'] ?? null), 'email');
        Setting::set('email_last_test_mode', $this->normalizeNullableString($result['mode'] ?? null), 'email');
        Setting::set('email_last_test_connected', ! empty($result['connected']) ? '1' : '0', 'email');
        Setting::set('email_last_test_tested_at', $this->normalizeNullableString($result['testedAt'] ?? now()->toIso8601String()), 'email');
        $details = is_array($result['details'] ?? null) ? $this->sanitizeSnapshotDetails($result['details']) : null;
        Setting::set('email_last_test_details', $details, 'email');

        $error = is_array($result['error'] ?? null) ? $result['error'] : null;
        Setting::set('email_last_test_error_code', $this->normalizeNullableString($error['code'] ?? null), 'email');
        Setting::set('email_last_test_error_message', $this->normalizeNullableString($error['message'] ?? null), 'email');

        Setting::set('email_last_test_by_id', isset($user?->id) ? (string) $user->id : null, 'email');
        Setting::set('email_last_test_by_uuid', isset($user?->uuid) ? (string) $user->uuid : null, 'email');
        Setting::set('email_last_test_by_email', isset($user?->email) ? (string) $user->email : null, 'email');

        return $this->getLastTestConnectionSnapshot();
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastTestConnectionSnapshot(): array
    {
        $details = Setting::get('email_last_test_details');

        return [
            'provider' => $this->normalizeNullableString(Setting::get('email_last_test_provider')),
            'mode' => $this->normalizeNullableString(Setting::get('email_last_test_mode')),
            'connected' => (bool) Setting::get('email_last_test_connected', false),
            'testedAt' => $this->normalizeNullableString(Setting::get('email_last_test_tested_at')),
            'details' => is_array($details) ? $this->sanitizeSnapshotDetails($details) : null,
            'error' => [
                'code' => $this->normalizeNullableString(Setting::get('email_last_test_error_code')),
                'message' => $this->normalizeNullableString(Setting::get('email_last_test_error_message')),
            ],
            'updatedBy' => [
                'id' => $this->normalizeNullableInteger(Setting::get('email_last_test_by_id')),
                'uuid' => $this->normalizeNullableString(Setting::get('email_last_test_by_uuid')),
                'email' => $this->normalizeNullableString(Setting::get('email_last_test_by_email')),
            ],
        ];
    }

    /**
     * Internal runtime profile for applying provider config to Laravel mail transport.
     *
     * @return array<string, mixed>
     */
    public function getRuntimeTransportProfile(): array
    {
        $provider = (string) Setting::get('email_provider', 'mailtrap');
        $fromAddress = $this->normalizeNullableString(Setting::get('email_from_address'));
        $fromName = $this->normalizeNullableString(Setting::get('email_from_name'));

        $smtpHost = $this->normalizeNullableString(Setting::get('email_smtp_host'));
        $smtpPort = (int) (Setting::get('email_smtp_port', 587) ?? 587);
        $smtpEncryption = $this->normalizeNullableString(Setting::get('email_smtp_encryption'));
        $smtpUsername = $this->normalizeNullableString(Setting::get('email_smtp_username'));
        $smtpPassword = $this->readSecret('email_smtp_password');

        $mailtrapAccountId = $this->normalizeNullableInteger(Setting::get('email_mailtrap_account_id'));
        $mailtrapApiToken = $this->readSecret('email_mailtrap_api_token');

        return [
            'provider' => $provider !== '' ? $provider : 'mailtrap',
            'fromAddress' => $fromAddress,
            'fromName' => $fromName,
            'smtp' => [
                'host' => $smtpHost,
                'port' => $smtpPort > 0 ? $smtpPort : 587,
                'encryption' => $smtpEncryption,
                'username' => $smtpUsername,
                'password' => $smtpPassword,
                'configured' => $smtpHost !== null && $smtpUsername !== null && $smtpPassword !== null,
            ],
            'mailtrap' => [
                'accountId' => $mailtrapAccountId,
                'apiToken' => $mailtrapApiToken,
                'configured' => $mailtrapAccountId !== null && $mailtrapApiToken !== null,
            ],
        ];
    }

    /**
     * @return array{accountId: ?int, apiToken: ?string, configured: bool}
     */
    public function getMailtrapCredentialsForProbe(): array
    {
        $accountId = $this->normalizeNullableInteger(Setting::get('email_mailtrap_account_id'));
        $apiToken = $this->readSecret('email_mailtrap_api_token');

        return [
            'accountId' => $accountId,
            'apiToken' => $apiToken,
            'configured' => $accountId !== null && $apiToken !== null,
        ];
    }

    /**
     * Resolve effective SMTP transport used by Laravel mailer at runtime.
     *
     * @return array<string, mixed>
     */
    public function resolveRuntimeSmtpTransport(): array
    {
        $profile = $this->getRuntimeTransportProfile();
        $provider = (string) ($profile['provider'] ?? '');
        $smtp = is_array($profile['smtp'] ?? null) ? $profile['smtp'] : [];

        $smtpHost = trim((string) ($smtp['host'] ?? ''));
        $smtpPort = (int) ($smtp['port'] ?? 587);
        $smtpEncryption = $smtp['encryption'] ?? null;
        $smtpUsername = trim((string) ($smtp['username'] ?? ''));
        $smtpPassword = (string) ($smtp['password'] ?? '');

        if ($provider === 'smtp') {
            $configured = $smtpHost !== '' && $smtpUsername !== '' && $smtpPassword !== '';

            return [
                'configured' => $configured,
                'source' => $configured ? 'smtp' : null,
                'host' => $smtpHost !== '' ? $smtpHost : null,
                'port' => $smtpPort > 0 ? $smtpPort : 587,
                'encryption' => $smtpEncryption,
                'username' => $smtpUsername !== '' ? $smtpUsername : null,
                'password' => $smtpPassword !== '' ? $smtpPassword : null,
            ];
        }

        if ($provider === 'mailtrap') {
            if ($smtpHost !== '' && $smtpUsername !== '' && $smtpPassword !== '') {
                return [
                    'configured' => true,
                    'source' => 'mailtrap-smtp-profile',
                    'host' => $smtpHost,
                    'port' => $smtpPort > 0 ? $smtpPort : 587,
                    'encryption' => $smtpEncryption,
                    'username' => $smtpUsername,
                    'password' => $smtpPassword,
                ];
            }

            $mailtrap = is_array($profile['mailtrap'] ?? null) ? $profile['mailtrap'] : [];
            $mailtrapToken = trim((string) ($mailtrap['apiToken'] ?? ''));
            if ($mailtrapToken !== '') {
                return [
                    'configured' => true,
                    'source' => 'mailtrap-token-default-smtp',
                    'host' => 'live.smtp.mailtrap.io',
                    'port' => 587,
                    'encryption' => 'tls',
                    'username' => 'api',
                    'password' => $mailtrapToken,
                ];
            }
        }

        return [
            'configured' => false,
            'source' => null,
            'host' => null,
            'port' => 587,
            'encryption' => null,
            'username' => null,
            'password' => null,
        ];
    }

    private function writeSecret(string $key, ?string $value): void
    {
        if ($value === null) {
            Setting::set($key, null, 'email');

            return;
        }

        $encrypted = Crypt::encryptString($value);
        Setting::set($key, self::ENCRYPTED_PREFIX.$encrypted, 'email');
    }

    private function readSecret(string $key): ?string
    {
        $stored = $this->normalizeNullableString(Setting::get($key));
        if ($stored === null) {
            return null;
        }

        if (! str_starts_with($stored, self::ENCRYPTED_PREFIX)) {
            return $stored;
        }

        $cipherText = substr($stored, strlen(self::ENCRYPTED_PREFIX));

        try {
            return $this->normalizeNullableString(Crypt::decryptString($cipherText));
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        $number = (int) ($value ?? 0);

        return $number > 0 ? $number : null;
    }

    /**
     * @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    private function sanitizeSnapshotDetails(array $details): array
    {
        $sanitized = [];

        foreach ($details as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if ($this->isSensitiveSnapshotKey($normalizedKey)) {
                continue;
            }

            if (is_array($value)) {
                $nested = $this->sanitizeSnapshotDetails($value);
                if ($nested !== []) {
                    $sanitized[(string) $key] = $nested;
                }

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $sanitized[(string) $key] = $value;
            }
        }

        return $sanitized;
    }

    private function isSensitiveSnapshotKey(string $key): bool
    {
        if (str_ends_with($key, 'masked')) {
            return false;
        }

        foreach (['password', 'token', 'secret', 'apikey', 'api_key', 'username'] as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeEncryption(mixed $value): ?string
    {
        $raw = strtolower(trim((string) ($value ?? '')));
        if ($raw === '' || $raw === 'none') {
            return null;
        }

        return in_array($raw, ['tls', 'ssl'], true) ? $raw : null;
    }

    private function maskSecret(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $last4 = substr($value, -4);

        return '****'.$last4;
    }
}
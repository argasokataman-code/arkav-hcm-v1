<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * UU PDP L4: Payslip encryption service.
 *
 * Encrypts payslip data before email delivery to protect sensitive
 * financial information (salary, NPWP, bank account) during transit.
 * Employee uses a password (default: NIK) to decrypt.
 */
class PayslipEncryptionService
{
    private const CIPHER = 'aes-256-cbc';

    /**
     * Encrypt plaintext payslip data with a password.
     */
    public function encrypt(string $plaintext, string $password): string
    {
        $key = $this->deriveKey($password);
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));

        $encrypted = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new \RuntimeException('Payslip encryption failed.');
        }

        // Format: base64(iv + encrypted + hmac)
        $hmac = hash_hmac('sha256', $iv.$encrypted, $key, true);
        $payload = $iv.$encrypted.$hmac;

        return base64_encode($payload);
    }

    /**
     * Decrypt payslip data. Returns false on failure (wrong password or tampered data).
     */
    public function decrypt(string $ciphertext, string $password): string|false
    {
        $payload = base64_decode($ciphertext, true);
        if ($payload === false) {
            return false;
        }

        $key = $this->deriveKey($password);
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $hmacLength = 32; // SHA-256 = 32 bytes

        if (strlen($payload) < $ivLength + $hmacLength) {
            return false;
        }

        $iv = substr($payload, 0, $ivLength);
        $hmac = substr($payload, -$hmacLength);
        $encrypted = substr($payload, $ivLength, -$hmacLength);

        // Verify HMAC
        $expectedHmac = hash_hmac('sha256', $iv.$encrypted, $key, true);
        if (! hash_equals($expectedHmac, $hmac)) {
            Log::warning('PayslipEncryptionService: HMAC mismatch — wrong password or tampered data');

            return false;
        }

        $decrypted = openssl_decrypt($encrypted, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);

        return $decrypted !== false ? $decrypted : false;
    }

    /**
     * Derive a default password from employee NIK.
     * Convention: "SLIP" + last 6 digits of NIK.
     */
    public function deriveDefaultPassword(string $nik): string
    {
        $cleanNik = preg_replace('/[^0-9]/', '', $nik);
        $lastDigits = substr($cleanNik, -6);

        return 'SLIP'.$lastDigits;
    }

    /**
     * Derive encryption key from password using PBKDF2.
     */
    private function deriveKey(string $password): string
    {
        // Use a fixed salt derived from app key for consistency
        $salt = hash('sha256', config('app.key', 'default-key'), true);

        return hash_pbkdf2('sha256', $password, $salt, 10000, 32, true);
    }
}

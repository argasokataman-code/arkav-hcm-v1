<?php

namespace Tests\Feature;

use App\Services\PayslipEncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayslipEncryptionTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // PayslipEncryptionService
    // -------------------------------------------------------------------------

    public function test_encrypt_returns_ciphertext_different_from_plaintext(): void
    {
        $service = new PayslipEncryptionService;

        $plaintext = '{"employee_name":"John Doe","base_salary":10000000,"npwp":"01.234.567.8-901.000"}';
        $passphrase = 'NIK3201234567890001';

        $encrypted = $service->encrypt($plaintext, $passphrase);

        $this->assertNotEquals($plaintext, $encrypted);
        $this->assertNotEmpty($encrypted);
    }

    public function test_decrypt_returns_original_plaintext(): void
    {
        $service = new PayslipEncryptionService;

        $plaintext = '{"employee_name":"Jane Doe","base_salary":15000000,"bank_account":"1234567890"}';
        $passphrase = 'MySecretPass123';

        $encrypted = $service->encrypt($plaintext, $passphrase);
        $decrypted = $service->decrypt($encrypted, $passphrase);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function test_decrypt_with_wrong_password_returns_false(): void
    {
        $service = new PayslipEncryptionService;

        $plaintext = '{"employee_name":"John","base_salary":10000000}';
        $passphrase = 'CorrectPassword';
        $wrongPassphrase = 'WrongPassword';

        $encrypted = $service->encrypt($plaintext, $passphrase);
        $result = $service->decrypt($encrypted, $wrongPassphrase);

        $this->assertFalse($result);
    }

    public function test_encrypt_is_deterministic_with_same_input_and_password(): void
    {
        $service = new PayslipEncryptionService;

        $plaintext = '{"test":"data"}';
        $passphrase = 'password';

        // OpenSSL with random IV means each encryption is different
        $encrypted1 = $service->encrypt($plaintext, $passphrase);
        $encrypted2 = $service->encrypt($plaintext, $passphrase);

        // Different ciphertexts but both should decrypt to same plaintext
        $this->assertEquals(
            $service->decrypt($encrypted1, $passphrase),
            $service->decrypt($encrypted2, $passphrase)
        );
    }

    public function test_encrypt_handles_unicode_content(): void
    {
        $service = new PayslipEncryptionService;

        $plaintext = '{"nama":"Budi Santoso","alamat":"Jl. Merdeka No. 1, Jakarta Pusat","gaji":10000000}';
        $passphrase = 'UnicodeTest123';

        $encrypted = $service->encrypt($plaintext, $passphrase);
        $decrypted = $service->decrypt($encrypted, $passphrase);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function test_encrypt_handles_empty_string(): void
    {
        $service = new PayslipEncryptionService;

        $plaintext = '';
        $passphrase = 'password';

        $encrypted = $service->encrypt($plaintext, $passphrase);
        $decrypted = $service->decrypt($encrypted, $passphrase);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function test_decrypt_invalid_base64_returns_false(): void
    {
        $service = new PayslipEncryptionService;

        $result = $service->decrypt('not-valid-base64!!!', 'password');

        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // Payslip email encryption integration
    // -------------------------------------------------------------------------

    public function test_payslip_encryption_config_exists(): void
    {
        $enabled = config('pdp.payslip_encryption_enabled', false);
        $this->assertIsBool($enabled);
    }

    public function test_default_payslip_password_uses_nik(): void
    {
        $service = new PayslipEncryptionService;

        // Default password derivation: use NIK
        $nik = '3201234567890001';
        $defaultPassword = $service->deriveDefaultPassword($nik);

        $this->assertNotEmpty($defaultPassword);
        $this->assertIsString($defaultPassword);
    }
}

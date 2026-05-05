<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Custom encryption cast that gracefully handles both encrypted and plaintext values.
 * 
 * Used for migration period where existing plaintext data coexists with new encrypted fields.
 * Once all data is encrypted (via EncryptExistingSensitiveData command), can safely use 
 * Laravel's built-in 'encrypted' cast.
 * 
 * UU PDP Compliance: C5 (Encryption at-rest)
 */
class EncryptedOrPlaintext implements CastsAttributes
{
    /**
     * Cast the given value when reading from model.
     * 
     * Attempts to decrypt; if decryption fails, returns value as-is (assuming plaintext).
     *
     * @param  array<string, mixed>  $attributes
     * @return string|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        // Try to decrypt the value
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException $e) {
            // Not encrypted or decryption failed - return as-is (plaintext or corrupted)
            // This supports backward compatibility during migration from plaintext to encrypted
            return $value;
        } catch (\Throwable $e) {
            // Any other error - return as-is
            return $value;
        }
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     * @return string|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        // Always encrypt on write
        return Crypt::encryptString((string) $value);
    }
}

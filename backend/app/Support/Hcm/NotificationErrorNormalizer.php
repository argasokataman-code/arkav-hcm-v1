<?php

namespace App\Support\Hcm;

class NotificationErrorNormalizer
{
    /**
     * Normalize delivery error message to standard error category.
     * Useful for grouping similar errors without exact message matching.
     */
    public static function normalize(?string $errorMessage): string
    {
        if (empty($errorMessage)) {
            return 'unknown_error';
        }

        $lower = strtolower($errorMessage);

        // SMTP/Email errors
        if (str_contains($lower, 'smtp') || str_contains($lower, 'mail server')) {
            if (str_contains($lower, 'timeout') || str_contains($lower, 'connection refused')) {
                return 'smtp_timeout';
            }
            if (str_contains($lower, 'authentication') || str_contains($lower, 'credentials')) {
                return 'smtp_auth_failed';
            }
            if (str_contains($lower, 'tls') || str_contains($lower, 'ssl')) {
                return 'smtp_tls_error';
            }
            return 'smtp_error';
        }

        // Bounce/Delivery failures
        if (str_contains($lower, 'bounce') || str_contains($lower, 'invalid')) {
            if (str_contains($lower, 'address') || str_contains($lower, 'email')) {
                return 'invalid_recipient';
            }
            return 'bounce_error';
        }

        // Rate limit / quota
        if (str_contains($lower, 'rate') || str_contains($lower, 'quota') || str_contains($lower, 'limit')) {
            return 'rate_limit';
        }

        // Network errors
        if (str_contains($lower, 'network') || str_contains($lower, 'connection') || str_contains($lower, 'refused')) {
            return 'network_error';
        }

        // Retry exhaustion
        if (str_contains($lower, 'retry') || str_contains($lower, 'attempt')) {
            return 'retry_exhausted';
        }

        // Configuration issues
        if (str_contains($lower, 'config') || str_contains($lower, 'missing') || str_contains($lower, 'invalid')) {
            return 'config_error';
        }

        // Default fallback
        return 'delivery_error';
    }

    /**
     * Map normalized category to human-readable description.
     */
    public static function describe(string $category): string
    {
        $descriptions = [
            'smtp_timeout' => 'SMTP connection timeout',
            'smtp_auth_failed' => 'SMTP authentication failed',
            'smtp_tls_error' => 'SMTP TLS/SSL error',
            'smtp_error' => 'SMTP error',
            'invalid_recipient' => 'Invalid recipient email',
            'bounce_error' => 'Message bounced',
            'rate_limit' => 'Rate limit exceeded',
            'network_error' => 'Network connectivity issue',
            'retry_exhausted' => 'Retry attempts exhausted',
            'config_error' => 'Configuration error',
            'delivery_error' => 'Delivery failed',
            'unknown_error' => 'Unknown error',
        ];

        return $descriptions[$category] ?? 'Delivery error';
    }
}

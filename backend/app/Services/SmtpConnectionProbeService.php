<?php

namespace App\Services;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Throwable;

class SmtpConnectionProbeService
{
    /**
     * @var callable|null
     */
    private $transportFactory;

    public function __construct(?callable $transportFactory = null)
    {
        $this->transportFactory = $transportFactory;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public function probe(array $config): array
    {
        $host = trim((string) ($config['host'] ?? ''));
        $encryption = $this->normalizeEncryption($config['encryption'] ?? null);
        $port = (int) ($config['port'] ?? ($encryption === 'ssl' ? 465 : 587));
        $timeout = $this->normalizeTimeout($config['timeout'] ?? null);

        $result = [
            'provider' => 'smtp',
            'mode' => 'ephemeral',
            'persisted' => false,
            'connected' => false,
            'testedAt' => now()->toIso8601String(),
            'details' => [
                'host' => $host !== '' ? $host : null,
                'port' => $port > 0 ? $port : 587,
                'encryption' => $encryption,
                'usernameMasked' => $this->maskProbeIdentifier($config['username'] ?? null),
                'timeout' => $timeout,
            ],
            'error' => null,
        ];

        try {
            $transport = $this->makeTransport([
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $this->normalizeNullableString($config['username'] ?? null),
                'password' => $this->normalizeNullableString($config['password'] ?? null),
                'timeout' => $timeout,
            ]);

            $transport->start();
            $transport->stop();

            $result['connected'] = true;

            return $result;
        } catch (TransportExceptionInterface|Throwable $e) {
            $result['error'] = $this->normalizeError($e->getMessage());

            return $result;
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function makeTransport(array $config): object
    {
        if (is_callable($this->transportFactory)) {
            return call_user_func($this->transportFactory, $config);
        }

        $encryption = $this->normalizeEncryption($config['encryption'] ?? null);
        $tls = $encryption === 'ssl';

        $stream = (new SocketStream())
            ->setTimeout($this->normalizeTimeout($config['timeout'] ?? null));

        $transport = new EsmtpTransport(
            host: (string) $config['host'],
            port: (int) $config['port'],
            tls: $tls,
            stream: $stream,
        );

        if ($encryption === 'tls') {
            $transport->setAutoTls(true);
            $transport->setRequireTls(true);
        }

        if ($encryption === null) {
            $transport->setAutoTls(false);
            $transport->setRequireTls(false);
        }

        $username = $this->normalizeNullableString($config['username'] ?? null);
        $password = $this->normalizeNullableString($config['password'] ?? null);

        if ($username !== null) {
            $transport->setUsername($username);
        }

        if ($password !== null) {
            $transport->setPassword($password);
        }

        return $transport;
    }

    /**
     * @return array{code: string, message: string}
     */
    private function normalizeError(string $message): array
    {
        $raw = strtolower(trim($message));

        if (str_contains($raw, 'timed out')) {
            return [
                'code' => 'TIMEOUT',
                'message' => 'SMTP connection timed out.',
            ];
        }

        if (str_contains($raw, 'php_network_getaddresses') || str_contains($raw, 'getaddrinfo') || str_contains($raw, 'name or service not known')) {
            return [
                'code' => 'DNS_ERROR',
                'message' => 'SMTP host could not be resolved.',
            ];
        }

        if (str_contains($raw, 'connection refused')) {
            return [
                'code' => 'CONNECTION_REFUSED',
                'message' => 'SMTP server refused the connection.',
            ];
        }

        if (str_contains($raw, 'starttls') || str_contains($raw, 'tls required') || str_contains($raw, 'crypto')) {
            return [
                'code' => 'TLS_ERROR',
                'message' => 'SMTP TLS negotiation failed.',
            ];
        }

        if (str_contains($raw, 'auth') || str_contains($raw, '535') || str_contains($raw, 'authentication')) {
            return [
                'code' => 'AUTH_FAILED',
                'message' => 'SMTP authentication failed.',
            ];
        }

        return [
            'code' => 'CONNECTION_FAILED',
            'message' => 'SMTP connection failed.',
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private function normalizeEncryption(mixed $value): ?string
    {
        $raw = strtolower(trim((string) ($value ?? '')));

        if ($raw === '' || $raw === 'none') {
            return null;
        }

        return in_array($raw, ['tls', 'ssl'], true) ? $raw : null;
    }

    private function normalizeTimeout(mixed $value): int
    {
        $timeout = (int) ($value ?? 10);

        if ($timeout < 1) {
            return 10;
        }

        return min($timeout, 30);
    }

    private function maskProbeIdentifier(mixed $value): ?string
    {
        $text = $this->normalizeNullableString($value);
        if ($text === null) {
            return null;
        }

        $length = strlen($text);
        if ($length <= 2) {
            return str_repeat('*', $length);
        }

        return substr($text, 0, 1).str_repeat('*', max(1, $length - 2)).substr($text, -1);
    }
}
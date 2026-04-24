<?php

namespace Tests\Unit;

use App\Services\SmtpConnectionProbeService;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class SmtpConnectionProbeServiceTest extends TestCase
{
    public function test_probe_returns_connected_for_successful_smtp_handshake(): void
    {
        $service = new SmtpConnectionProbeService(static fn () => new class {
            public function start(): void {}

            public function stop(): void {}
        });

        $result = $service->probe([
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'smtp-user',
            'password' => 'secret',
            'timeout' => 8,
        ]);

        $this->assertTrue($result['connected']);
        $this->assertNull($result['error']);
        $this->assertSame('smtp', $result['provider']);
        $this->assertSame('ephemeral', $result['mode']);
    }

    public function test_probe_normalizes_auth_failure(): void
    {
        $service = new SmtpConnectionProbeService(static fn () => new class {
            public function start(): void
            {
                throw new TransportException('535 5.7.8 Authentication credentials invalid');
            }

            public function stop(): void {}
        });

        $result = $service->probe([
            'host' => 'smtp.example.com',
            'username' => 'smtp-user',
            'password' => 'bad-secret',
        ]);

        $this->assertFalse($result['connected']);
        $this->assertSame('AUTH_FAILED', $result['error']['code']);
    }

    public function test_probe_normalizes_timeout_failure(): void
    {
        $service = new SmtpConnectionProbeService(static fn () => new class {
            public function start(): void
            {
                throw new TransportException('Connection timed out');
            }

            public function stop(): void {}
        });

        $result = $service->probe([
            'host' => 'smtp.example.com',
            'username' => 'smtp-user',
            'password' => 'secret',
        ]);

        $this->assertFalse($result['connected']);
        $this->assertSame('TIMEOUT', $result['error']['code']);
    }

    public function test_probe_normalizes_tls_failure(): void
    {
        $service = new SmtpConnectionProbeService(static fn () => new class {
            public function start(): void
            {
                throw new TransportException('Unable to connect with STARTTLS.');
            }

            public function stop(): void {}
        });

        $result = $service->probe([
            'host' => 'smtp.example.com',
            'encryption' => 'tls',
            'username' => 'smtp-user',
            'password' => 'secret',
        ]);

        $this->assertFalse($result['connected']);
        $this->assertSame('TLS_ERROR', $result['error']['code']);
    }
}
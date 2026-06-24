<?php

namespace Tests\Feature;

use Tests\TestCase;

class RedisConfigTest extends TestCase
{
    public function test_env_file_has_redis_cache_store(): void
    {
        if (! app()->environment('production')) {
            $this->markTestSkipped('Redis cache store check only applies in production.');
        }

        $env = file_get_contents(base_path('.env'));
        $this->assertStringContainsString(
            'CACHE_STORE=redis',
            $env,
            '.env harus pakai CACHE_STORE=redis untuk production'
        );
    }

    public function test_env_file_has_redis_session_driver(): void
    {
        if (! app()->environment('production')) {
            $this->markTestSkipped('Redis session driver check only applies in production.');
        }

        $env = file_get_contents(base_path('.env'));
        $this->assertStringContainsString(
            'SESSION_DRIVER=redis',
            $env,
            '.env harus pakai SESSION_DRIVER=redis untuk production'
        );
    }

    public function test_env_file_has_predis_client(): void
    {
        $env = file_get_contents(base_path('.env'));
        $this->assertStringContainsString(
            'REDIS_CLIENT=predis',
            $env,
            '.env harus pakai REDIS_CLIENT=predis (pure PHP, tanpa C extension)'
        );
    }

    public function test_testing_env_uses_array_for_cache(): void
    {
        // Testing environment sengaja pake array biar isolated
        $this->assertSame(
            'array',
            config('cache.default'),
            'Test environment harus pakai array cache driver'
        );
    }

    public function test_testing_env_uses_array_for_session(): void
    {
        $this->assertSame(
            'array',
            config('session.driver'),
            'Test environment harus pakai array session driver'
        );
    }

    public function test_testing_env_uses_sync_for_queue(): void
    {
        $this->assertSame(
            'sync',
            config('queue.default'),
            'Test environment harus pakai sync queue'
        );
    }

    public function test_redis_extension_or_predis_is_available(): void
    {
        // Either phpredis extension OR predis/predis package must be available
        $hasExt = extension_loaded('redis');
        $hasPredis = class_exists('\Predis\Client');

        $this->assertTrue(
            $hasExt || $hasPredis,
            'Redis driver harus tersedia (phpredis ext atau predis/predis)'
        );

        if ($hasPredis) {
            $this->markTestIncomplete('Redis connection test skipped — predis tersedia tapi konek manual hanya di production.');
        }
    }
}

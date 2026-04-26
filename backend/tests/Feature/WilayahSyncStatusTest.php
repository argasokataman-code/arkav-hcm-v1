<?php

namespace Tests\Feature;

use App\Services\Wilayah\WilayahSyncService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WilayahSyncStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create([
            'is_super_admin' => true,
        ]));
    }

    public function test_sync_status_returns_idle_default_when_cache_empty(): void
    {
        Cache::forget(WilayahSyncService::PROGRESS_CACHE_KEY);

        $this->getJson('/locations/sync-status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.running', false)
            ->assertJsonPath('data.progress', 0)
            ->assertJsonPath('data.stage', 'idle');
    }

    public function test_sync_status_returns_cached_progress_payload(): void
    {
        Cache::put(WilayahSyncService::PROGRESS_CACHE_KEY, [
            'running' => true,
            'progress' => 67,
            'stage' => 'districts',
            'message' => 'Sync districts dari seluruh regencies.',
            'processed' => 340,
            'total' => 514,
            'error' => null,
            'summary' => [
                'provinces' => 38,
                'regencies' => 514,
                'districts' => 341,
                'villages' => 0,
            ],
            'startedAt' => now()->subMinute()->toIso8601String(),
            'updatedAt' => now()->toIso8601String(),
            'finishedAt' => null,
        ], 60);

        $this->getJson('/locations/sync-status')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.running', true)
            ->assertJsonPath('data.progress', 67)
            ->assertJsonPath('data.stage', 'districts')
            ->assertJsonPath('data.processed', 340)
            ->assertJsonPath('data.total', 514)
            ->assertJsonPath('data.summary.provinces', 38)
            ->assertJsonPath('data.summary.regencies', 514);
    }
}

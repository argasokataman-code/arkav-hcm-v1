<?php

namespace Tests\Feature;

use App\Models\ErasureRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurgeCompletedErasuresTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_completed_erasures_removes_old_records(): void
    {
        // Old completed request (>90 days)
        $old = ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) Str::uuid(),
            'company_id' => 1,
            'status' => 'completed',
            'reason' => 'Old erasure',
            'completed_at' => now()->subDays(100),
        ]);

        // Recent completed request (<90 days)
        $recent = ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) Str::uuid(),
            'company_id' => 1,
            'status' => 'completed',
            'reason' => 'Recent erasure',
            'completed_at' => now()->subDays(30),
        ]);

        // Force timestamps (bypass model mutators)
        DB::table('erasure_requests')->where('id', $old->id)->update([
            'completed_at' => now()->subDays(100),
        ]);
        DB::table('erasure_requests')->where('id', $recent->id)->update([
            'completed_at' => now()->subDays(30),
        ]);

        Artisan::call('pdp:purge-completed-erasures', ['--days' => 90]);

        $this->assertDatabaseMissing('erasure_requests', ['id' => $old->id]);
        $this->assertDatabaseHas('erasure_requests', ['id' => $recent->id]);
    }

    public function test_purge_does_not_affect_non_completed_requests(): void
    {
        $pending = ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) Str::uuid(),
            'company_id' => 1,
            'status' => 'pending',
            'reason' => 'Still pending',
        ]);

        $approved = ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) Str::uuid(),
            'company_id' => 1,
            'status' => 'approved',
            'reason' => 'Approved but not completed',
        ]);

        $rejected = ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) Str::uuid(),
            'company_id' => 1,
            'status' => 'rejected',
            'reason' => 'Rejected request',
        ]);

        Artisan::call('pdp:purge-completed-erasures', ['--days' => 90]);

        $this->assertDatabaseHas('erasure_requests', ['id' => $pending->id]);
        $this->assertDatabaseHas('erasure_requests', ['id' => $approved->id]);
        $this->assertDatabaseHas('erasure_requests', ['id' => $rejected->id]);
    }

    public function test_purge_with_custom_days_option(): void
    {
        $erasure = ErasureRequest::query()->create([
            'uuid' => (string) Str::uuid(),
            'subject_uuid' => (string) Str::uuid(),
            'company_id' => 1,
            'status' => 'completed',
            'completed_at' => now()->subDays(15),
        ]);

        DB::table('erasure_requests')->where('id', $erasure->id)->update([
            'completed_at' => now()->subDays(15),
        ]);

        // Purge with 10 days cutoff
        Artisan::call('pdp:purge-completed-erasures', ['--days' => 10]);

        $this->assertDatabaseMissing('erasure_requests', ['id' => $erasure->id]);
    }

    public function test_purge_with_no_matching_records_succeeds(): void
    {
        $exitCode = Artisan::call('pdp:purge-completed-erasures', ['--days' => 90]);

        $this->assertEquals(0, $exitCode);
    }
}

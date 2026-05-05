<?php

namespace Tests\Feature;

use App\Models\AiChatLog;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PdpRetentionCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_ai_chat_logs_removes_expired_rows(): void
    {
        $user = User::factory()->create();

        $oldLog = AiChatLog::query()->create([
            'user_uuid' => (string) $user->uuid,
            'user_legacy_id' => $user->id,
            'company_id' => null,
            'session_id' => (string) Str::uuid(),
            'intent' => 'unknown',
            'allowed' => true,
            'created_at' => now()->subDays(400),
        ]);

        $newLog = AiChatLog::query()->create([
            'user_uuid' => (string) $user->uuid,
            'user_legacy_id' => $user->id,
            'company_id' => null,
            'session_id' => (string) Str::uuid(),
            'intent' => 'unknown',
            'allowed' => true,
            'created_at' => now()->subDays(30),
        ]);

        DB::table('ai_chat_logs')->where('id', $oldLog->id)->update([
            'created_at' => now()->subDays(400),
        ]);
        DB::table('ai_chat_logs')->where('id', $newLog->id)->update([
            'created_at' => now()->subDays(30),
        ]);

        Artisan::call('pdp:purge-ai-chat-logs', [
            '--days' => 365,
        ]);

        $this->assertDatabaseMissing('ai_chat_logs', ['id' => $oldLog->id]);
        $this->assertDatabaseHas('ai_chat_logs', ['id' => $newLog->id]);
    }

    public function test_purge_attendance_records_removes_expired_rows(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();

        $oldAttendance = AttendanceRecord::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'user_id' => $user->id,
            'work_date' => now()->subYears(6)->toDateString(),
            'status' => 'present',
            'check_in_at' => now()->subYears(6),
        ]);

        $newAttendance = AttendanceRecord::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'user_id' => $user->id,
            'work_date' => now()->subYears(1)->toDateString(),
            'status' => 'present',
            'check_in_at' => now()->subYears(1),
        ]);

        Artisan::call('pdp:purge-attendance-records', [
            '--years' => 5,
        ]);

        $this->assertDatabaseMissing('attendance_records', ['id' => $oldAttendance->id]);
        $this->assertDatabaseHas('attendance_records', ['id' => $newAttendance->id]);
    }
}

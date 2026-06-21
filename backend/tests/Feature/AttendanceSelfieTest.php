<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceSelfieTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private string $baseUrl = '/v1/hcm';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test company
        $this->company = Company::factory()->create([
            'timezone' => 'UTC',
        ]);

        // Login via identity flow to get a real API token (api.token middleware)
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Employee User',
            'email' => 'employee@company.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'employee@company.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        $this->token = (string) $login->json('data.accessToken');
        $this->assertNotSame('', $this->token);

        $this->user = User::query()->where('email', 'employee@company.com')->firstOrFail();

        CompanyUser::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        // Create test attendance record for today
        AttendanceRecord::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => now()->toDateString(),
            'status' => 'present',
            'check_in_at' => now(),
        ]);
    }

    public function test_authenticated_employee_can_access_selfie_endpoints(): void
    {
        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->token,
                'X-Company-Id' => $this->company->id,
            ])
            ->getJson("{$this->baseUrl}/attendance/me/selfie/status");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_selfie', false)
            ->assertJsonPath('data.selfie', null);
    }

    public function test_selfie_upload_succeeds_for_valid_image_after_checkin(): void
    {
        Storage::fake('private');

        // 1x1 JPEG image
        $testImage = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8VAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k=';

        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->token,
                'X-Company-Id' => $this->company->id,
            ])
            ->postJson("{$this->baseUrl}/attendance/me/selfie", [
                'selfie_base64' => $testImage,
                'timestamp' => time(),
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['attendance_id', 'selfie_path', 'uploaded_at'],
            ]);

        $path = (string) $response->json('data.selfie_path');
        $this->assertStringStartsWith('selfie/'.$this->company->id.'/', $path);
        Storage::disk('private')->assertExists($path);

        $record = AttendanceRecord::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->whereDate('work_date', now()->toDateString())
            ->firstOrFail();

        $this->assertNotNull($record->selfie_encrypted_hash);
        $this->assertSame(64, strlen((string) $record->selfie_encrypted_hash));
    }

    public function test_selfie_upload_rejects_when_attendance_not_started(): void
    {
        AttendanceRecord::query()
            ->where('company_id', $this->company->id)
            ->where('user_id', $this->user->id)
            ->update(['check_in_at' => null]);

        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->token,
                'X-Company-Id' => $this->company->id,
            ])
            ->postJson("{$this->baseUrl}/attendance/me/selfie", [
                'selfie_base64' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAA==',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ATTENDANCE_NOT_STARTED');
    }

    public function test_selfie_endpoint_validates_base64_data(): void
    {
        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->token,
                'X-Company-Id' => $this->company->id,
            ])
            ->postJson("{$this->baseUrl}/attendance/me/selfie", [
                'selfie_base64' => '',
            ]);

        $this->assertSame(422, $response->status(), $response->getContent());
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_selfie_endpoint_rejects_non_image_payload(): void
    {
        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->token,
                'X-Company-Id' => $this->company->id,
            ])
            ->postJson("{$this->baseUrl}/attendance/me/selfie", [
                'selfie_base64' => 'data:image/png;base64,'.base64_encode('not-an-image'),
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_selfie_endpoint_rejects_oversized_payload(): void
    {
        $largeBinary = random_bytes((5 * 1024 * 1024) + 10);

        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->token,
                'X-Company-Id' => $this->company->id,
            ])
            ->postJson("{$this->baseUrl}/attendance/me/selfie", [
                'selfie_base64' => 'data:image/jpeg;base64,'.base64_encode($largeBinary),
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_selfie_status_returns_uploaded_data_after_successful_upload(): void
    {
        Storage::fake('private');

        $testImage = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2Yq5sAAAAASUVORK5CYII=';

        $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => $this->company->id,
        ])->postJson("{$this->baseUrl}/attendance/me/selfie", [
            'selfie_base64' => $testImage,
        ])->assertOk();

        $status = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Id' => $this->company->id,
        ])->getJson("{$this->baseUrl}/attendance/me/selfie/status");

        $status
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_selfie', true)
            ->assertJsonPath('data.selfie.is_encrypted', true);
    }
}

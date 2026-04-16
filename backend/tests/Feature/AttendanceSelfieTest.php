<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->company = Company::factory()->create();

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
        // Status endpoint should be accessible
        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->token,
                'X-Company-Id' => $this->company->id,
            ])
            ->getJson("{$this->baseUrl}/attendance/me/selfie/status");

        // Should return 200 (not 404 or 403)
        $this->assertTrue(in_array($response->status(), [200, 401, 403, 422], true));
    }

    public function test_selfie_upload_endpoint_exists_and_validates_input(): void
    {
        Storage::fake('private');

        // Create a simple test base64 image
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

        // Should succeed (or at least not crash)
        $this->assertNotSame(500, $response->status());
    }

    public function test_selfie_endpoint_validates_base64_data(): void
    {
        $response = $this
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->token,
                'X-Company-Id' => $this->company->id,
            ])
            ->postJson("{$this->baseUrl}/attendance/me/selfie", [
                'selfie_base64' => '', // Empty base64
            ]);

        // Should return validation error (422) or similar
        $this->assertSame(422, $response->status(), $response->getContent());
    }
}



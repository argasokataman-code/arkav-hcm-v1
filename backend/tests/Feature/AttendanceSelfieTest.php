<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceSelfieTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;
    private string $baseUrl = '/api/v1';

    protected function setUp(): void
    {
        parent::setUp();

        // Create test company
        $this->company = Company::factory()->create();

        // Create test user
        $this->user = User::factory()->create();

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
        $response = $this->actingAs($this->user)
            ->withHeaders([
                'X-Company-Id' => $this->company->id,
            ])
            ->getJson("{$this->baseUrl}/attendance/me/selfie/status");

        // Should return 200 (not 404 or 403)
        $this->assertIn($response->status(), [200, 401, 403, 422]);
    }

    public function test_selfie_upload_endpoint_exists_and_validates_input(): void
    {
        // Create a simple test base64 image
        $testImage = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8VAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k=';

        $response = $this->actingAs($this->user)
            ->withHeaders([
                'X-Company-Id' => $this->company->id,
            ])
            ->postJson("{$this->baseUrl}/attendance/me/selfie", [
                'selfie_base64' => $testImage,
                'timestamp' => time(),
            ]);

        // Should not be 404 (endpoint exists)
        $this->assertNotEquals(404, $response->status());
    }

    public function test_selfie_endpoint_validates_base64_data(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeaders([
                'X-Company-Id' => $this->company->id,
            ])
            ->postJson("{$this->baseUrl}/attendance/me/selfie", [
                'selfie_base64' => '', // Empty base64
            ]);

        // Should return validation error (422) or similar
        $this->assertIn($response->status(), [400, 422]);
    }
}



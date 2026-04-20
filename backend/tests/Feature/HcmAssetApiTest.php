<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HcmAssetApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private Company $company;
    private EmployeeProfile $employeeProfile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::query()->create([
            'code' => 'asset_co',
            'name' => 'Asset Company',
            'legal_name' => 'Asset Company Ltd',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $package = Package::query()->create([
            'code' => 'asset-pro',
            'name' => 'Asset Pro',
            'monthly_price' => 199000,
            'yearly_price' => 1990000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        PackageFeature::query()->create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'asset_management',
            'feature_name' => 'Asset Management',
            'limit' => null,
        ]);

        Subscription::query()->create([
            'company_id' => $this->company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 199000,
        ]);

        $admin = User::query()->create([
            'name' => 'QA Admin',
            'email' => 'qa.login@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        CompanyUser::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        $employeeUser = User::query()->create([
            'name' => 'Employee One',
            'email' => 'employee.one@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $this->employeeProfile = EmployeeProfile::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $employeeUser->id,
            'employment_status' => 'active',
            'designation' => 'Staff',
            'team' => 'Operations',
            'nik' => 'EMP-001',
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.login@example.com',
            'password' => 'StrongPass1',
            'companyCode' => $this->company->code,
        ]);

        $loginResponse->assertOk();
        $this->token = (string) $loginResponse->json('data.accessToken');
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'X-Company-Code' => $this->company->code,
        ];
    }

    public function test_asset_lifecycle_happy_path(): void
    {
        $categoryResponse = $this->withHeaders($this->headers())->postJson('/v1/hcm/asset-categories', [
            'code' => 'laptop',
            'name' => 'Laptop',
            'description' => 'Company laptop assets',
            'is_active' => true,
        ]);

        $categoryResponse->assertStatus(201);
        $categoryId = (int) $categoryResponse->json('data.id');

        $assetResponse = $this->withHeaders($this->headers())->postJson('/v1/hcm/assets', [
            'asset_category_id' => $categoryId,
            'name' => 'MacBook Pro 14',
            'brand' => 'Apple',
            'model' => 'M3 Pro',
            'serial_number' => 'MBP-001',
            'purchase_date' => now()->subMonth()->toDateString(),
            'purchase_price' => 35000000,
            'condition' => 'good',
            'status' => 'available',
            'location' => 'Jakarta Office',
        ]);

        $assetResponse->assertStatus(201);
        $assetId = (int) $assetResponse->json('data.id');

        $assignResponse = $this->withHeaders($this->headers())->postJson("/v1/hcm/assets/{$assetId}/assign", [
            'employee_id' => $this->employeeProfile->id,
            'assigned_date' => now()->toDateString(),
            'condition_at_assign' => 'good',
            'notes' => 'Issued for onboarding',
        ]);

        $assignResponse->assertStatus(201);
        $assignResponse->assertJsonPath('data.isActive', true);

        $returnResponse = $this->withHeaders($this->headers())->postJson("/v1/hcm/assets/{$assetId}/return", [
            'returned_date' => now()->toDateString(),
            'condition_at_return' => 'good',
            'notes' => 'Returned in working condition',
        ]);

        $returnResponse->assertOk();
        $returnResponse->assertJsonPath('data.isActive', false);

        $this->assertDatabaseHas('assets', [
            'id' => $assetId,
            'status' => 'available',
        ]);

        $this->assertDatabaseHas('asset_assignments', [
            'asset_id' => $assetId,
            'employee_id' => $this->employeeProfile->id,
        ]);
    }

    public function test_asset_endpoints_are_feature_gated(): void
    {
        $company = Company::query()->create([
            'code' => 'no_asset_feature',
            'name' => 'No Asset Feature',
            'legal_name' => 'No Asset Feature Ltd',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $user = User::query()->create([
            'name' => 'QA Admin Two',
            'email' => 'qa.asset.gated@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'employment_status' => 'active',
            'designation' => 'Admin',
            'team' => 'Platform',
            'nik' => 'EMP-002',
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.asset.gated@example.com',
            'password' => 'StrongPass1',
            'companyCode' => $company->code,
        ]);

        $loginResponse->assertOk();
        $token = (string) $loginResponse->json('data.accessToken');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Code' => $company->code,
        ])->getJson('/v1/hcm/assets');

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FEATURE_DISABLED');
    }

    public function test_asset_endpoints_treat_zero_limit_feature_as_disabled(): void
    {
        $company = Company::query()->create([
            'code' => 'zero_limit_asset',
            'name' => 'Zero Limit Asset Co',
            'legal_name' => 'Zero Limit Asset Co Ltd',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $package = Package::query()->create([
            'code' => 'zero-limit-pkg',
            'name' => 'Zero Limit Package',
            'monthly_price' => 99000,
            'yearly_price' => 990000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);

        PackageFeature::query()->create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'asset_management',
            'feature_name' => 'Asset Management',
            'limit' => 0,
        ]);

        Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 99000,
        ]);

        $user = User::query()->create([
            'name' => 'QA Admin Zero Limit',
            'email' => 'qa.asset.zero.limit@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'employment_status' => 'active',
            'designation' => 'Admin',
            'team' => 'Platform',
            'nik' => 'EMP-003',
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'qa.asset.zero.limit@example.com',
            'password' => 'StrongPass1',
            'companyCode' => $company->code,
        ]);

        $loginResponse->assertOk();
        $token = (string) $loginResponse->json('data.accessToken');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Code' => $company->code,
        ])->getJson('/v1/hcm/assets');

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FEATURE_DISABLED');
    }

    public function test_reporting_asset_issue_creates_ticket_in_same_company(): void
    {
        $category = AssetCategory::query()->create([
            'company_id' => $this->company->id,
            'code' => 'device',
            'name' => 'Device',
            'description' => 'Device assets',
            'is_active' => true,
        ]);

        $asset = Asset::query()->create([
            'company_id' => $this->company->id,
            'asset_category_id' => $category->id,
            'asset_code' => 'AST-ISSUE-001',
            'name' => 'Work Laptop',
            'brand' => 'Lenovo',
            'model' => 'T14',
            'serial_number' => 'SN-ISSUE-001',
            'purchase_date' => now()->subMonth()->toDateString(),
            'purchase_price' => 18000000,
            'condition' => 'good',
            'status' => 'assigned',
            'location' => 'Jakarta',
        ]);

        $response = $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/assets/'.$asset->id.'/issue-report', [
                'issue_type' => 'maintenance',
                'priority' => 'high',
                'description' => 'Battery health dropping quickly.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $ticketId = (int) $response->json('data.ticketId');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticketId,
            'company_id' => $this->company->id,
            'user_id' => User::query()->where('email', 'qa.login@example.com')->value('id'),
            'category' => 'asset_issue',
            'priority' => 'high',
            'status' => 'open',
        ]);

        $ticket = Ticket::query()->findOrFail($ticketId);
        $this->assertStringContainsString('AST-ISSUE-001', $ticket->subject);
        $this->assertStringContainsString('Battery health dropping quickly.', (string) $ticket->description);
    }
}
<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use App\Models\HcmUserRole;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        PackageFeature::query()->create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'asset_attachments',
            'feature_name' => 'Asset Attachments',
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

    private function createCategory(string $code = 'laptop', string $name = 'Laptop'): AssetCategory
    {
        return AssetCategory::query()->create([
            'company_id' => $this->company->id,
            'code' => strtoupper($code),
            'name' => $name,
            'description' => $name.' assets',
            'is_active' => true,
        ]);
    }

    private function createAsset(AssetCategory $category, array $overrides = []): Asset
    {
        return Asset::query()->create(array_merge([
            'company_id' => $this->company->id,
            'asset_category_id' => $category->id,
            'asset_code' => 'AST-TEST-001',
            'name' => 'Test Asset',
            'brand' => 'Brand',
            'model' => 'Model',
            'serial_number' => 'SN-001',
            'purchase_date' => now()->subMonth()->toDateString(),
            'purchase_price' => 1500000,
            'condition' => 'good',
            'status' => 'available',
            'location' => 'Jakarta',
        ], $overrides));
    }

    private function viewOnlyHeaders(): array
    {
        $viewer = User::query()->create([
            'name' => 'Asset Viewer',
            'email' => 'asset.viewer@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $viewer->id,
            'employment_status' => 'active',
            'designation' => 'Staff',
            'team' => 'Operations',
            'nik' => 'EMP-004',
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        CompanyUser::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $viewer->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        $permission = HcmPermission::query()->create([
            'code' => 'asset.view',
            'module' => 'asset_management',
            'resource' => 'asset',
            'action' => 'view',
            'name' => 'View Assets',
            'is_active' => true,
        ]);

        $role = HcmRole::query()->create([
            'company_id' => $this->company->id,
            'code' => 'ASSET_VIEWER',
            'name' => 'Asset Viewer',
            'status' => 'active',
            'is_system' => false,
        ]);

        DB::table('hcm_role_permissions')->insert([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'company_id' => $this->company->id,
            'company_uuid' => $this->company->uuid,
            'uuid' => (string) Str::uuid(),
        ]);

        HcmUserRole::query()->create([
            'user_id' => $viewer->id,
            'company_id' => $this->company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => $viewer->email,
            'password' => 'StrongPass1',
            'companyCode' => $this->company->code,
        ]);

        $loginResponse->assertOk();

        return [
            'Authorization' => 'Bearer '.(string) $loginResponse->json('data.accessToken'),
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
        $category = $this->createCategory('device', 'Device');

        $asset = $this->createAsset($category, [
            'asset_code' => 'AST-ISSUE-001',
            'name' => 'Work Laptop',
            'brand' => 'Lenovo',
            'model' => 'T14',
            'serial_number' => 'SN-ISSUE-001',
            'purchase_price' => 18000000,
            'status' => 'assigned',
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

    public function test_asset_detail_returns_uploaded_attachments(): void
    {
        Storage::fake('public');

        $category = $this->createCategory();
        $asset = $this->createAsset($category, [
            'asset_code' => 'AST-ATTACH-001',
            'name' => 'Attachment Asset',
        ]);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/assets/'.$asset->id.'/attachments', [
                'file' => UploadedFile::fake()->create('asset-note.txt', 12, 'text/plain'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.originalName', 'asset-note.txt');

        $this->withHeaders($this->headers())
            ->getJson('/v1/hcm/assets/'.$asset->id)
            ->assertOk()
            ->assertJsonPath('data.attachments.0.originalName', 'asset-note.txt')
            ->assertJsonPath('data.attachmentsCount', 1);
    }

    public function test_view_only_permission_can_list_assets_but_cannot_mutate_assets_or_categories(): void
    {
        $category = $this->createCategory();
        $viewerHeaders = $this->viewOnlyHeaders();

        $this->withHeaders($viewerHeaders)
            ->getJson('/v1/hcm/assets')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders($viewerHeaders)
            ->postJson('/v1/hcm/assets', [
                'asset_category_id' => $category->id,
                'name' => 'Viewer Cannot Create',
                'purchase_date' => now()->toDateString(),
                'purchase_price' => 200000,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders($viewerHeaders)
            ->postJson('/v1/hcm/asset-categories', [
                'name' => 'Viewer Category',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_asset_create_rejects_category_from_other_company(): void
    {
        $otherCompany = Company::query()->create([
            'code' => 'other_asset_co',
            'name' => 'Other Asset Company',
            'legal_name' => 'Other Asset Company Ltd',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $foreignCategory = AssetCategory::query()->create([
            'company_id' => $otherCompany->id,
            'code' => 'FOREIGN',
            'name' => 'Foreign Category',
            'description' => 'Category from another tenant',
            'is_active' => true,
        ]);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/assets', [
                'asset_category_id' => $foreignCategory->id,
                'name' => 'Cross Tenant Asset',
                'purchase_date' => now()->toDateString(),
                'purchase_price' => 500000,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['asset_category_id']);
    }

    public function test_return_rejects_date_before_assignment_date(): void
    {
        $category = $this->createCategory();
        $asset = $this->createAsset($category);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/assets/'.$asset->id.'/assign', [
                'employee_id' => $this->employeeProfile->id,
                'assigned_date' => '2026-04-10',
                'condition_at_assign' => 'good',
            ])
            ->assertStatus(201);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/assets/'.$asset->id.'/return', [
                'returned_date' => '2026-04-01',
                'condition_at_return' => 'good',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ASSET_RETURN_DATE_INVALID');
    }

    public function test_retired_assets_remain_visible_in_asset_list(): void
    {
        $category = $this->createCategory();
        $asset = $this->createAsset($category, [
            'asset_code' => 'AST-RET-001',
            'name' => 'Retired Asset',
        ]);

        $this->withHeaders($this->headers())
            ->deleteJson('/v1/hcm/assets/'.$asset->id)
            ->assertOk();

        $this->withHeaders($this->headers())
            ->getJson('/v1/hcm/assets?status=retired')
            ->assertOk()
            ->assertJsonPath('data.0.assetCode', 'AST-RET-001')
            ->assertJsonPath('data.0.status', 'retired');
    }

    public function test_assign_rejects_already_assigned_asset(): void
    {
        $category = $this->createCategory();
        $asset = $this->createAsset($category);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/assets/'.$asset->id.'/assign', [
                'employee_id' => $this->employeeProfile->id,
                'assigned_date' => now()->toDateString(),
            ])
            ->assertStatus(201);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/assets/'.$asset->id.'/assign', [
                'employee_id' => $this->employeeProfile->id,
                'assigned_date' => now()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ASSET_NOT_AVAILABLE');
    }

    public function test_assign_rejects_non_available_asset(): void
    {
        $category = $this->createCategory();
        $asset = $this->createAsset($category, [
            'status' => 'maintenance',
        ]);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/assets/'.$asset->id.'/assign', [
                'employee_id' => $this->employeeProfile->id,
                'assigned_date' => now()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ASSET_NOT_AVAILABLE');
    }

    public function test_return_rejects_not_assigned_asset(): void
    {
        $category = $this->createCategory();
        $asset = $this->createAsset($category);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/assets/'.$asset->id.'/return', [
                'returned_date' => now()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ASSET_NOT_ASSIGNED');
    }

    public function test_assign_rejects_cross_company_employee(): void
    {
        $category = $this->createCategory();
        $asset = $this->createAsset($category);

        $otherCompany = Company::query()->create([
            'code' => 'other_emp_co',
            'name' => 'Other Employee Company',
            'legal_name' => 'Other Employee Company Ltd',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $otherUser = User::query()->create([
            'name' => 'Other Employee',
            'email' => 'other.employee@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        $otherProfile = EmployeeProfile::query()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherUser->id,
            'employment_status' => 'active',
            'designation' => 'Staff',
            'team' => 'Ops',
            'nik' => 'EMP-OTHER-001',
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/assets/'.$asset->id.'/assign', [
                'employee_id' => $otherProfile->id,
                'assigned_date' => now()->toDateString(),
            ])
            ->assertStatus(404);
    }

    public function test_delete_category_rejects_if_has_assets(): void
    {
        $category = $this->createCategory('monitor', 'Monitor');
        $this->createAsset($category, [
            'asset_code' => 'AST-CAT-001',
            'name' => 'Monitor Asset',
        ]);

        $this->withHeaders($this->headers())
            ->deleteJson('/v1/hcm/asset-categories/'.$category->id)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CATEGORY_IN_USE');
    }

    public function test_cross_company_asset_access_returns_404(): void
    {
        $category = $this->createCategory();
        $asset = $this->createAsset($category);

        $otherCompany = Company::query()->create([
            'code' => 'other_cross_co',
            'name' => 'Other Cross Company',
            'legal_name' => 'Other Cross Ltd',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $otherUser = User::query()->create([
            'name' => 'Cross Admin',
            'email' => 'cross.admin@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        CompanyUser::query()->create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherUser->id,
            'role' => 'admin',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'cross.admin@example.com',
            'password' => 'StrongPass1',
            'companyCode' => $otherCompany->code,
        ]);
        $login->assertOk();
        $otherToken = (string) $login->json('data.accessToken');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$otherToken,
            'X-Company-Code' => $otherCompany->code,
        ])->getJson('/v1/hcm/assets/'.$asset->id)
            ->assertStatus(404);
    }

    public function test_asset_not_found_returns_404(): void
    {
        $this->withHeaders($this->headers())
            ->getJson('/v1/hcm/assets/99999')
            ->assertStatus(404);
    }

    public function test_category_not_found_returns_404(): void
    {
        $this->withHeaders($this->headers())
            ->getJson('/v1/hcm/asset-categories')
            ->assertOk();

        $this->withHeaders($this->headers())
            ->putJson('/v1/hcm/asset-categories/99999', ['name' => 'Ghost'])
            ->assertStatus(404);
    }

    public function test_issue_report_lost_sets_condition_lost_and_status_retired(): void
    {
        $category = $this->createCategory('device', 'Device');
        $asset = $this->createAsset($category, [
            'asset_code' => 'AST-LOST-001',
            'name' => 'Lost Asset',
            'serial_number' => 'SN-LOST-001',
        ]);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/assets/'.$asset->id.'/issue-report', [
                'issue_type' => 'lost',
                'priority' => 'high',
                'description' => 'Asset lost in transit.',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'condition' => 'lost',
            'status' => 'retired',
        ]);
    }

    public function test_create_asset_rejects_duplicate_serial_number(): void
    {
        $category = $this->createCategory();

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/assets', [
                'asset_category_id' => $category->id,
                'name' => 'Asset One',
                'serial_number' => 'SN-DUP-001',
                'purchase_date' => now()->subMonth()->toDateString(),
                'purchase_price' => 1000000,
            ])
            ->assertStatus(201);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/assets', [
                'asset_category_id' => $category->id,
                'name' => 'Asset Two',
                'serial_number' => 'SN-DUP-001',
                'purchase_date' => now()->subMonth()->toDateString(),
                'purchase_price' => 2000000,
            ])
            ->assertStatus(422);
    }

    public function test_attachment_rejects_file_too_large(): void
    {
        Storage::fake('public');

        $category = $this->createCategory();
        $asset = $this->createAsset($category, [
            'asset_code' => 'AST-BIG-001',
            'name' => 'Big File Asset',
        ]);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/assets/'.$asset->id.'/attachments', [
                'file' => UploadedFile::fake()->create('huge-file.pdf', 15360, 'application/pdf'),
            ])
            ->assertStatus(422);
    }

    public function test_non_admin_cannot_access_assets(): void
    {
        $viewer = User::query()->create([
            'name' => 'No Permission User',
            'email' => 'no.perm@example.com',
            'password' => Hash::make('StrongPass1'),
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $viewer->id,
            'employment_status' => 'active',
            'designation' => 'Staff',
            'team' => 'Ops',
            'nik' => 'EMP-NOPERM',
            'hire_date' => now()->subMonth()->toDateString(),
        ]);

        CompanyUser::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $viewer->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now()->subDay(),
            'invited_by_user_id' => null,
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'no.perm@example.com',
            'password' => 'StrongPass1',
            'companyCode' => $this->company->code,
        ]);
        $login->assertOk();
        $viewerToken = (string) $login->json('data.accessToken');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$viewerToken,
            'X-Company-Code' => $this->company->code,
        ])->getJson('/v1/hcm/assets')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_create_category_rejects_duplicate_name(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/asset-categories', [
                'name' => 'Unique Category',
            ])
            ->assertStatus(201);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/asset-categories', [
                'name' => 'Unique Category',
            ])
            ->assertStatus(422);
    }

    public function test_create_category_rejects_duplicate_code(): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/asset-categories', [
                'name' => 'Cat Alpha',
                'code' => 'ALPHA',
            ])
            ->assertStatus(201);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/asset-categories', [
                'name' => 'Cat Beta',
                'code' => 'ALPHA',
            ])
            ->assertStatus(422);
    }

    public function test_attachment_rejects_invalid_mime_type(): void
    {
        Storage::fake('public');

        $category = $this->createCategory();
        $asset = $this->createAsset($category, [
            'asset_code' => 'AST-MIME-001',
            'name' => 'Mime Test Asset',
        ]);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/assets/'.$asset->id.'/attachments', [
                'file' => UploadedFile::fake()->create('malware.php', 512, 'text/x-php'),
            ])
            ->assertStatus(422);
    }

    public function test_assign_rejects_future_assigned_date(): void
    {
        $category = $this->createCategory();
        $asset = $this->createAsset($category);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/assets/'.$asset->id.'/assign', [
                'employee_id' => $this->employeeProfile->id,
                'assigned_date' => now()->addYear()->toDateString(),
            ])
            ->assertStatus(422);
    }

    public function test_return_rejects_future_returned_date(): void
    {
        $category = $this->createCategory();
        $asset = $this->createAsset($category);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/assets/'.$asset->id.'/assign', [
                'employee_id' => $this->employeeProfile->id,
                'assigned_date' => now()->subDay()->toDateString(),
            ])
            ->assertStatus(201);

        $this->withHeaders($this->headers())
            ->postJson('/v1/hcm/assets/'.$asset->id.'/return', [
                'returned_date' => now()->addYear()->toDateString(),
            ])
            ->assertStatus(422);
    }

    public function test_category_list_returns_paginated(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            AssetCategory::query()->create([
                'company_id' => $this->company->id,
                'code' => 'CAT_'.$i,
                'name' => 'Pagination Category '.$i,
                'is_active' => true,
            ]);
        }

        $response = $this->withHeaders($this->headers())
            ->getJson('/v1/hcm/asset-categories')
            ->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(25, $data);
    }
}

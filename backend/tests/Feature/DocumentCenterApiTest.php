<?php

namespace Tests\Feature;

use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use App\Models\HcmRolePermission;
use App\Models\HcmUserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentCenterApiTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function adminSetup(): array
    {
        $data = $this->createHcmAdminWithCompany([
            'name'  => 'Doc Admin',
            'email' => 'doc-admin@example.com',
        ]);

        $headers = $this->withCompanyContext(
            ['Authorization' => 'Bearer ' . $data['token']],
            $data['company']
        );

        return ['headers' => $headers, 'company' => $data['company'], 'token' => $data['token']];
    }

    private function employeeSetup(int $companyId): array
    {
        $this->postJson('/v1/identity/auth/register', [
            'name'            => 'Doc Employee',
            'email'           => 'doc-employee@example.com',
            'password'        => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', 'doc-employee@example.com')->firstOrFail();

        // Add as company member with employee role
        CompanyUser::firstOrCreate(
            ['user_id' => $user->id, 'company_id' => $companyId],
            ['role' => 'member', 'status' => 'active']
        );

        // Assign document_center.view permission via a viewer role
        $permission = HcmPermission::query()->firstOrCreate(
            ['code' => 'document_center.view'],
            ['module' => 'document_center', 'resource' => 'document_center', 'action' => 'view',
             'name' => 'Document Center View', 'is_active' => true]
        );
        $viewRole = HcmRole::query()->firstOrCreate(
            ['company_id' => $companyId, 'code' => 'DOC_VIEWER'],
            ['name' => 'Document Viewer', 'status' => 'active']
        );
        HcmRolePermission::withoutTimestamps(function () use ($viewRole, $permission, $companyId): void {
            HcmRolePermission::firstOrCreate([
                'role_id'       => $viewRole->id,
                'permission_id' => $permission->id,
                'company_id'    => $companyId,
            ]);
        });
        HcmUserRole::updateOrCreate(
            ['user_id' => $user->id, 'company_id' => $companyId],
            ['role_id' => $viewRole->id, 'status' => 'active']
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email'    => 'doc-employee@example.com',
            'password' => 'StrongPass1',
            'companyCode' => \App\Models\Company::find($companyId)->code,
        ])->assertOk();

        $token = (string) $login->json('data.accessToken');

        // Create an employee profile for the employee user linked to the company
        $profile = EmployeeProfile::query()->create([
            'company_id' => $companyId,
            'user_id'    => $user->id,
            'first_name' => 'Doc',
            'last_name'  => 'Employee',
            'status'     => 'active',
        ]);

        $headers = ['Authorization' => 'Bearer ' . $token, 'X-Company-Id' => (string) $companyId];

        return ['user' => $user, 'profile' => $profile, 'headers' => $headers, 'token' => $token];
    }

    /**
     * Employee with NO document_center permissions — uses controller self-service path.
     * canView() = false → shows only own employee_visible documents.
     */
    private function selfServiceEmployeeSetup(int $companyId): array
    {
        $this->postJson('/v1/identity/auth/register', [
            'name'            => 'Self Service Employee',
            'email'           => 'self-service@example.com',
            'password'        => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $user = User::query()->where('email', 'self-service@example.com')->firstOrFail();

        // Company membership only — no HCM role, no doc_center permission
        CompanyUser::firstOrCreate(
            ['user_id' => $user->id, 'company_id' => $companyId],
            ['role' => 'member', 'status' => 'active']
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email'       => 'self-service@example.com',
            'password'    => 'StrongPass1',
            'companyCode' => \App\Models\Company::find($companyId)->code,
        ])->assertOk();

        $token = (string) $login->json('data.accessToken');

        $profile = EmployeeProfile::query()->create([
            'company_id' => $companyId,
            'user_id'    => $user->id,
            'first_name' => 'Self',
            'last_name'  => 'Service',
            'status'     => 'active',
        ]);

        $headers = ['Authorization' => 'Bearer ' . $token, 'X-Company-Id' => (string) $companyId];

        return ['user' => $user, 'profile' => $profile, 'headers' => $headers, 'token' => $token];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Category Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_categories_require_tenant_context(): void
    {
        // Register a fresh user with no company membership
        $this->postJson('/v1/identity/auth/register', [
            'name'            => 'No Company User',
            'email'           => 'no-company@example.com',
            'password'        => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email'    => 'no-company@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        $token = (string) $login->json('data.accessToken');
        $headers = ['Authorization' => 'Bearer ' . $token]; // no X-Company-Id, no membership

        // Without any company membership, the middleware + controller chain returns
        // 403 AUTH_FORBIDDEN (controller canView = false after testing tenant bypass)
        $this->withHeaders($headers)->getJson('/v1/hcm/document-center/categories')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_admin_can_crud_categories(): void
    {
        $setup = $this->adminSetup();
        $headers = $setup['headers'];

        // List (empty)
        $this->withHeaders($headers)->getJson('/v1/hcm/document-center/categories')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');

        // Create
        $res = $this->withHeaders($headers)->postJson('/v1/hcm/document-center/categories', [
            'name'     => 'Kontrak Kerja',
            'isActive' => true,
        ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Kontrak Kerja')
            ->assertJsonPath('data.isActive', true);

        $id = (int) $res->json('data.id');
        $this->assertGreaterThan(0, $id);

        // Update
        $this->withHeaders($headers)->putJson("/v1/hcm/document-center/categories/{$id}", [
            'name'     => 'Kontrak Kerja Updated',
            'isActive' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Kontrak Kerja Updated')
            ->assertJsonPath('data.isActive', false);

        // List should include inactive for admin
        $this->withHeaders($headers)->getJson('/v1/hcm/document-center/categories')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Kontrak Kerja Updated']);

        // Delete
        $this->withHeaders($headers)->deleteJson("/v1/hcm/document-center/categories/{$id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders($headers)->getJson('/v1/hcm/document-center/categories')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_employee_cannot_mutate_categories(): void
    {
        $admin = $this->adminSetup();
        $emp = $this->employeeSetup($admin['company']->id);

        // Employee can list categories (active only — but returns empty since none created)
        $this->withHeaders($emp['headers'])->getJson('/v1/hcm/document-center/categories')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Employee cannot create
        $this->withHeaders($emp['headers'])->postJson('/v1/hcm/document-center/categories', [
            'name' => 'Attempted Category',
        ])
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_category_delete_orphans_documents(): void
    {
        Storage::fake('public');

        $admin = $this->adminSetup();
        $emp = $this->employeeSetup($admin['company']->id);

        // Create category
        $catRes = $this->withHeaders($admin['headers'])->postJson('/v1/hcm/document-center/categories', [
            'name' => 'To Delete',
        ])->assertStatus(201);
        $catId = (int) $catRes->json('data.id');

        // Upload a document assigned to this category
        $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');
        $docRes = $this->withHeaders($admin['headers'])->post('/v1/hcm/document-center/documents', [
            'file'              => $file,
            'employeeProfileId' => $emp['profile']->id,
            'title'             => 'Test Contract',
            'visibility'        => 'hr_only',
            'categoryId'        => $catId,
        ])->assertStatus(201);
        $docId = (int) $docRes->json('data.id');

        // Delete category
        $this->withHeaders($admin['headers'])->deleteJson("/v1/hcm/document-center/categories/{$catId}")
            ->assertOk();

        // Document should still exist but category = null
        $this->assertDatabaseHas('hcm_employee_documents', [
            'id'          => $docId,
            'category_id' => null,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Document Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_admin_can_upload_and_list_documents(): void
    {
        Storage::fake('public');

        $admin = $this->adminSetup();
        $emp = $this->employeeSetup($admin['company']->id);

        // Upload document
        $file = UploadedFile::fake()->create('contract.pdf', 512, 'application/pdf');
        $res = $this->withHeaders($admin['headers'])->post('/v1/hcm/document-center/documents', [
            'file'              => $file,
            'employeeProfileId' => $emp['profile']->id,
            'title'             => 'Employment Contract 2026',
            'description'       => 'Standard contract',
            'visibility'        => 'employee_visible',
        ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Employment Contract 2026')
            ->assertJsonPath('data.visibility', 'employee_visible')
            ->assertJsonPath('data.originalName', 'contract.pdf');

        $docId = (int) $res->json('data.id');
        $this->assertGreaterThan(0, $docId);

        // Admin list returns document
        $this->withHeaders($admin['headers'])->getJson('/v1/hcm/document-center/documents')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['id' => $docId, 'title' => 'Employment Contract 2026']);
    }

    public function test_admin_can_update_document_metadata(): void
    {
        Storage::fake('public');

        $admin = $this->adminSetup();
        $emp = $this->employeeSetup($admin['company']->id);

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $docRes = $this->withHeaders($admin['headers'])->post('/v1/hcm/document-center/documents', [
            'file'              => $file,
            'employeeProfileId' => $emp['profile']->id,
            'title'             => 'Original Title',
            'visibility'        => 'hr_only',
        ])->assertStatus(201);
        $docId = (int) $docRes->json('data.id');

        // Update metadata
        $this->withHeaders($admin['headers'])->putJson("/v1/hcm/document-center/documents/{$docId}", [
            'title'      => 'Updated Title',
            'visibility' => 'employee_visible',
            'expiresAt'  => '2027-12-31',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.visibility', 'employee_visible')
            ->assertJsonPath('data.expiresAt', '2027-12-31');
    }

    public function test_admin_can_delete_document(): void
    {
        Storage::fake('public');

        $admin = $this->adminSetup();
        $emp = $this->employeeSetup($admin['company']->id);

        $file = UploadedFile::fake()->create('to-delete.pdf', 100, 'application/pdf');
        $docRes = $this->withHeaders($admin['headers'])->post('/v1/hcm/document-center/documents', [
            'file'              => $file,
            'employeeProfileId' => $emp['profile']->id,
            'title'             => 'To Delete',
            'visibility'        => 'hr_only',
        ])->assertStatus(201);
        $docId = (int) $docRes->json('data.id');

        $this->withHeaders($admin['headers'])->deleteJson("/v1/hcm/document-center/documents/{$docId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('hcm_employee_documents', ['id' => $docId]);
    }

    public function test_employee_sees_only_own_employee_visible_documents(): void
    {
        Storage::fake('public');

        $admin = $this->adminSetup();
        // Self-service employee: no doc_center permission → canView = false → sees only own employee_visible
        $emp = $this->selfServiceEmployeeSetup($admin['company']->id);

        // Upload hr_only doc for employee
        $file1 = UploadedFile::fake()->create('private.pdf', 100, 'application/pdf');
        $hrOnly = $this->withHeaders($admin['headers'])->post('/v1/hcm/document-center/documents', [
            'file'              => $file1,
            'employeeProfileId' => $emp['profile']->id,
            'title'             => 'HR Only Doc',
            'visibility'        => 'hr_only',
        ])->assertStatus(201);
        $hrOnlyId = (int) $hrOnly->json('data.id');

        // Upload employee_visible doc for employee
        $file2 = UploadedFile::fake()->create('visible.pdf', 100, 'application/pdf');
        $visible = $this->withHeaders($admin['headers'])->post('/v1/hcm/document-center/documents', [
            'file'              => $file2,
            'employeeProfileId' => $emp['profile']->id,
            'title'             => 'Visible Doc',
            'visibility'        => 'employee_visible',
        ])->assertStatus(201);
        $visibleId = (int) $visible->json('data.id');

        // Employee list — should only see employee_visible
        $empList = $this->withHeaders($emp['headers'])->getJson('/v1/hcm/document-center/documents')
            ->assertOk()
            ->assertJsonPath('success', true);

        $data = $empList->json('data');
        $ids = array_column($data, 'id');
        $this->assertContains($visibleId, $ids, 'Employee should see employee_visible document');
        $this->assertNotContains($hrOnlyId, $ids, 'Employee should NOT see hr_only document');
    }

    public function test_employee_cannot_upload_documents(): void
    {
        Storage::fake('public');

        $admin = $this->adminSetup();
        $emp = $this->employeeSetup($admin['company']->id);

        $file = UploadedFile::fake()->create('sneaky.pdf', 100, 'application/pdf');
        $this->withHeaders($emp['headers'])->post('/v1/hcm/document-center/documents', [
            'file'              => $file,
            'employeeProfileId' => $emp['profile']->id,
            'title'             => 'Sneaky Upload',
            'visibility'        => 'hr_only',
        ])
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_employee_cannot_delete_documents(): void
    {
        Storage::fake('public');

        $admin = $this->adminSetup();
        $emp = $this->employeeSetup($admin['company']->id);

        $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');
        $docRes = $this->withHeaders($admin['headers'])->post('/v1/hcm/document-center/documents', [
            'file'              => $file,
            'employeeProfileId' => $emp['profile']->id,
            'title'             => 'Contract',
            'visibility'        => 'employee_visible',
        ])->assertStatus(201);
        $docId = (int) $docRes->json('data.id');

        $this->withHeaders($emp['headers'])->deleteJson("/v1/hcm/document-center/documents/{$docId}")
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_employee_cannot_download_hr_only_document(): void
    {
        Storage::fake('public');

        $admin = $this->adminSetup();
        // Self-service employee: no doc_center permission → download guard blocks hr_only
        $emp = $this->selfServiceEmployeeSetup($admin['company']->id);

        $file = UploadedFile::fake()->create('hr-doc.pdf', 100, 'application/pdf');
        $docRes = $this->withHeaders($admin['headers'])->post('/v1/hcm/document-center/documents', [
            'file'              => $file,
            'employeeProfileId' => $emp['profile']->id,
            'title'             => 'HR Only',
            'visibility'        => 'hr_only',
        ])->assertStatus(201);
        $docId = (int) $docRes->json('data.id');

        $this->withHeaders($emp['headers'])->getJson("/v1/hcm/document-center/documents/{$docId}/download")
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_tenant_isolation_documents(): void
    {
        Storage::fake('public');

        $adminA = $this->createHcmAdminWithCompany(['name' => 'Admin A', 'email' => 'doc-admin-a@example.com']);
        $adminB = $this->createHcmAdminWithCompany(['name' => 'Admin B', 'email' => 'doc-admin-b@example.com']);

        $headersA = $this->withCompanyContext(['Authorization' => 'Bearer ' . $adminA['token']], $adminA['company']);
        $headersB = $this->withCompanyContext(['Authorization' => 'Bearer ' . $adminB['token']], $adminB['company']);

        // Create employee profiles for each company (user_id required - reuse admin users)
        $userA = User::query()->where('email', 'doc-admin-a@example.com')->firstOrFail();
        $userB = User::query()->where('email', 'doc-admin-b@example.com')->firstOrFail();

        $profileA = EmployeeProfile::query()->create([
            'company_id' => $adminA['company_id'],
            'user_id'    => $userA->id,
            'first_name' => 'Emp A',
            'last_name'  => '',
            'status'     => 'active',
        ]);
        $profileB = EmployeeProfile::query()->create([
            'company_id' => $adminB['company_id'],
            'user_id'    => $userB->id,
            'first_name' => 'Emp B',
            'last_name'  => '',
            'status'     => 'active',
        ]);

        // Admin A creates a category
        $catA = $this->withHeaders($headersA)->postJson('/v1/hcm/document-center/categories', [
            'name' => 'Company A Category',
        ])->assertStatus(201);
        $catAId = (int) $catA->json('data.id');

        // Admin B should not see Company A's category
        $this->withHeaders($headersB)->getJson('/v1/hcm/document-center/categories')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Company A Category']);

        // Admin A uploads document
        $file = UploadedFile::fake()->create('company-a-doc.pdf', 100, 'application/pdf');
        $docA = $this->withHeaders($headersA)->post('/v1/hcm/document-center/documents', [
            'file'              => $file,
            'employeeProfileId' => $profileA->id,
            'title'             => 'Company A Secret',
            'visibility'        => 'hr_only',
        ])->assertStatus(201);
        $docAId = (int) $docA->json('data.id');

        // Admin B cannot see or delete Company A's document
        $this->withHeaders($headersB)->getJson('/v1/hcm/document-center/documents')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Company A Secret']);

        $this->withHeaders($headersB)->deleteJson("/v1/hcm/document-center/documents/{$docAId}")
            ->assertStatus(404);
    }

    public function test_documents_filter_by_visibility(): void
    {
        Storage::fake('public');

        $admin = $this->adminSetup();
        $emp = $this->employeeSetup($admin['company']->id);

        $file1 = UploadedFile::fake()->create('doc1.pdf', 100, 'application/pdf');
        $this->withHeaders($admin['headers'])->post('/v1/hcm/document-center/documents', [
            'file'              => $file1,
            'employeeProfileId' => $emp['profile']->id,
            'title'             => 'HR Doc',
            'visibility'        => 'hr_only',
        ])->assertStatus(201);

        $file2 = UploadedFile::fake()->create('doc2.pdf', 100, 'application/pdf');
        $this->withHeaders($admin['headers'])->post('/v1/hcm/document-center/documents', [
            'file'              => $file2,
            'employeeProfileId' => $emp['profile']->id,
            'title'             => 'Employee Doc',
            'visibility'        => 'employee_visible',
        ])->assertStatus(201);

        $res = $this->withHeaders($admin['headers'])->getJson('/v1/hcm/document-center/documents?visibility=hr_only')
            ->assertOk();
        $data = $res->json('data');
        foreach ($data as $row) {
            $this->assertEquals('hr_only', $row['visibility']);
        }

        $res2 = $this->withHeaders($admin['headers'])->getJson('/v1/hcm/document-center/documents?visibility=employee_visible')
            ->assertOk();
        $data2 = $res2->json('data');
        foreach ($data2 as $row) {
            $this->assertEquals('employee_visible', $row['visibility']);
        }
    }
}

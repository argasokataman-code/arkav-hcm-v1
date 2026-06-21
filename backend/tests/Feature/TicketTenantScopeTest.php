<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class TicketTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private function cookieName(): string
    {
        return (string) config('auth.api_token_cookie.name', 'arcav_access_token');
    }

    private function readCookieValueFromLoginResponse(TestResponse $response): string
    {
        $setCookies = $response->headers->getCookies();
        foreach ($setCookies as $cookie) {
            if ($cookie->getName() === $this->cookieName()) {
                return (string) $cookie->getValue();
            }
        }

        return '';
    }

    public function test_ticket_list_is_scoped_to_active_company_for_admin(): void
    {
        $companyA = Company::query()->create([
            'name' => 'Company A',
            'code' => 'company_a',
            'status' => 'active',
        ]);
        $companyB = Company::query()->create([
            'name' => 'Company B',
            'code' => 'company_b',
            'status' => 'active',
        ]);

        // Create an HCM admin (global) based on configured admin email.
        $adminEmail = (string) config('hcm.admin_email', 'qa.login@example.com');
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'QA Admin',
            'email' => $adminEmail,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $admin = User::query()->where('email', $adminEmail)->first();
        $this->assertNotNull($admin);

        CompanyUser::query()->create([
            'company_id' => $companyA->id,
            'user_id' => $admin->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        CompanyUser::query()->create([
            'company_id' => $companyB->id,
            'user_id' => $admin->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $reporterA = User::query()->create([
            'name' => 'Reporter A',
            'email' => 'reporter.a@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);
        CompanyUser::query()->create([
            'company_id' => $companyA->id,
            'user_id' => $reporterA->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $reporterB = User::query()->create([
            'name' => 'Reporter B',
            'email' => 'reporter.b@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);
        CompanyUser::query()->create([
            'company_id' => $companyB->id,
            'user_id' => $reporterB->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $ticketA = Ticket::query()->create([
            'company_id' => $companyA->id,
            'user_id' => $reporterA->id,
            'code' => 'TIC-A-001',
            'subject' => 'Issue A',
            'description' => 'A',
            'category' => null,
            'category_id' => null,
            'priority' => 'low',
            'status' => 'open',
        ]);
        $ticketB = Ticket::query()->create([
            'company_id' => $companyB->id,
            'user_id' => $reporterB->id,
            'code' => 'TIC-B-001',
            'subject' => 'Issue B',
            'description' => 'B',
            'category' => null,
            'category_id' => null,
            'priority' => 'low',
            'status' => 'open',
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => $adminEmail,
            'password' => 'StrongPass1',
        ])->assertOk()->assertCookie($this->cookieName());

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $this->assertNotEmpty($token);
        $cookieHeader = $this->cookieName().'='.$token;

        $resA = $this->withHeader('Cookie', $cookieHeader)
            ->withHeader('X-Company-Id', (string) $companyA->id)
            ->getJson('/v1/hcm/tickets?perPage=50');

        $resA->assertOk()->assertJsonPath('success', true);
        $idsA = collect($resA->json('data'))->pluck('id')->all();
        $this->assertContains($ticketA->id, $idsA);
        $this->assertNotContains($ticketB->id, $idsA);

        $resB = $this->withHeader('Cookie', $cookieHeader)
            ->withHeader('X-Company-Id', (string) $companyB->id)
            ->getJson('/v1/hcm/tickets?perPage=50');

        $resB->assertOk()->assertJsonPath('success', true);
        $idsB = collect($resB->json('data'))->pluck('id')->all();
        $this->assertContains($ticketB->id, $idsB);
        $this->assertNotContains($ticketA->id, $idsB);
    }

    public function test_ticket_created_in_one_active_company_does_not_leak_to_other_membership(): void
    {
        $companyA = Company::query()->create([
            'name' => 'Scoped A',
            'code' => 'scoped_a',
            'status' => 'active',
        ]);
        $companyB = Company::query()->create([
            'name' => 'Scoped B',
            'code' => 'scoped_b',
            'status' => 'active',
        ]);

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Multi Tenant Reporter',
            'email' => 'multi-ticket@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $reporter = User::query()->where('email', 'multi-ticket@example.com')->firstOrFail();

        CompanyUser::query()->create([
            'company_id' => $companyA->id,
            'user_id' => $reporter->id,
            'role' => 'employee',
            'status' => 'active',
        ]);
        CompanyUser::query()->create([
            'company_id' => $companyB->id,
            'user_id' => $reporter->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => 'multi-ticket@example.com',
            'password' => 'StrongPass1',
        ])->assertOk()->assertCookie($this->cookieName());

        $token = $this->readCookieValueFromLoginResponse($loginResponse);
        $cookieHeader = $this->cookieName().'='.$token;

        $create = $this->withHeader('Cookie', $cookieHeader)
            ->withHeader('X-Company-Id', (string) $companyA->id)
            ->postJson('/v1/hcm/tickets', [
                'subject' => 'Scoped ticket',
                'description' => 'Only for company A',
                'priority' => 'medium',
            ])->assertStatus(201);

        $ticketId = (int) $create->json('data.id');

        $this->withHeader('Cookie', $cookieHeader)
            ->withHeader('X-Company-Id', (string) $companyA->id)
            ->getJson('/v1/hcm/tickets?perPage=50')
            ->assertOk()
            ->assertJsonFragment(['id' => $ticketId]);

        $this->withHeader('Cookie', $cookieHeader)
            ->withHeader('X-Company-Id', (string) $companyB->id)
            ->getJson('/v1/hcm/tickets?perPage=50')
            ->assertOk()
            ->assertJsonMissing(['id' => $ticketId]);
    }

    public function test_assignable_users_are_scoped_to_active_company(): void
    {
        $companyA = Company::query()->create([
            'name' => 'Assignable A',
            'code' => 'assignable_a',
            'status' => 'active',
        ]);
        $companyB = Company::query()->create([
            'name' => 'Assignable B',
            'code' => 'assignable_b',
            'status' => 'active',
        ]);

        $adminEmail = (string) config('hcm.admin_email', 'qa.login@example.com');
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Assignable Admin',
            'email' => $adminEmail,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $admin = User::query()->where('email', $adminEmail)->firstOrFail();

        CompanyUser::query()->create([
            'company_id' => $companyA->id,
            'user_id' => $admin->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $userA = User::query()->create([
            'name' => 'Assignable User A',
            'email' => 'assignable.user.a@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);
        CompanyUser::query()->create([
            'company_id' => $companyA->id,
            'user_id' => $userA->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $userB = User::query()->create([
            'name' => 'Assignable User B',
            'email' => 'assignable.user.b@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);
        CompanyUser::query()->create([
            'company_id' => $companyB->id,
            'user_id' => $userB->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        $loginResponse = $this->postJson('/v1/identity/auth/login', [
            'email' => $adminEmail,
            'password' => 'StrongPass1',
        ])->assertOk();

        $token = (string) $loginResponse->json('data.accessToken');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Company-Id', (string) $companyA->id)
            ->getJson('/v1/hcm/tickets/assignable-users')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($userA->id, $ids);
        $this->assertNotContains($userB->id, $ids);
    }
}

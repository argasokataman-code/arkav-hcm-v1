<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\EmployeeProfile;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function readCookieValueFromLoginResponse(\Illuminate\Testing\TestResponse $response): string
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
}


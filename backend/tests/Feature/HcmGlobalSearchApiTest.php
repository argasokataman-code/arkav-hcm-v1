<?php

namespace Tests\Feature;

use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HcmGlobalSearchApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{token:string,user:User,companyId:int}
     */
    private function authContext(string $email): array
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Search User',
            'email' => $email,
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => 'StrongPass1',
        ])->assertOk();

        $token = (string) $login->json('data.accessToken');
        $user = User::query()->where('email', $email)->firstOrFail();

        $me = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/v1/identity/auth/me')
            ->assertOk();

        return [
            'token' => $token,
            'user' => $user,
            'companyId' => (int) $me->json('data.activeCompany.id'),
        ];
    }

    public function test_non_global_user_cannot_see_global_only_catalog_entries(): void
    {
        $ctx = $this->authContext('search-regular@example.com');

        CompanyUser::query()
            ->where('user_id', $ctx['user']->id)
            ->where('company_id', $ctx['companyId'])
            ->update(['role' => 'employee', 'status' => 'active']);

        $ctx['user']->forceFill(['is_super_admin' => false])->save();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['companyId'],
        ])->getJson('/v1/hcm/search?q=saas&limit=20');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissing(['routeName' => 'saas.packages'])
            ->assertJsonMissing(['routeName' => 'companies']);
    }

    public function test_global_admin_can_see_global_only_catalog_entries(): void
    {
        $ctx = $this->authContext('search-global@example.com');
        $ctx['user']->forceFill(['is_super_admin' => true])->save();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['token'],
            'X-Company-Id' => (string) $ctx['companyId'],
        ])->getJson('/v1/hcm/search?q=paket&limit=20')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['routeName' => 'saas.packages']);
    }
}

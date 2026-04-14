<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\HcmEmailSettingsController;
use App\Services\MailtrapAccountApiService;
use Illuminate\Http\Request;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class HcmEmailSettingsControllerTest extends TestCase
{
    public function test_mailtrap_status_returns_connected_for_admin(): void
    {
        config()->set('services.mailtrap.api_token', 'a1b2c3d4e5f6');
        config()->set('services.mailtrap.account_id', 3229);

        $user = new class {
            public function isHcmAdmin(): bool
            {
                return true;
            }
        };

        $request = Request::create('/v1/hcm/email-settings/mailtrap-status', 'GET');
        $request->setUserResolver(static fn () => $user);

        $service = Mockery::mock(MailtrapAccountApiService::class);
        $service->shouldReceive('listApiTokens')->once()->andReturn([
            [
                'id' => 12345,
                'name' => 'My API Token',
                'last_4_digits' => 'x7k9',
                'expires_at' => null,
            ],
        ]);

        $response = (new HcmEmailSettingsController())->mailtrapStatus($request, $service);

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertTrue($payload['data']['connected']);
        $this->assertSame(1, $payload['data']['visibleTokenCount']);
        $this->assertSame('e5f6', $payload['data']['tokenLast4']);
    }

    public function test_mailtrap_status_returns_forbidden_for_non_admin(): void
    {
        $user = new class {
            public function isHcmAdmin(): bool
            {
                return false;
            }
        };

        $request = Request::create('/v1/hcm/email-settings/mailtrap-status', 'GET');
        $request->setUserResolver(static fn () => $user);

        $service = Mockery::mock(MailtrapAccountApiService::class);

        $response = (new HcmEmailSettingsController())->mailtrapStatus($request, $service);

        $this->assertSame(403, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertSame('AUTH_FORBIDDEN', $payload['error']['code']);
    }

    public function test_mailtrap_status_handles_runtime_exception(): void
    {
        config()->set('services.mailtrap.api_token', 'a1b2c3d4e5f6');
        config()->set('services.mailtrap.account_id', 3229);

        $user = new class {
            public function isHcmAdmin(): bool
            {
                return true;
            }
        };

        $request = Request::create('/v1/hcm/email-settings/mailtrap-status', 'GET');
        $request->setUserResolver(static fn () => $user);

        $service = Mockery::mock(MailtrapAccountApiService::class);
        $service->shouldReceive('listApiTokens')->once()->andThrow(new RuntimeException('Mailtrap API request failed (401): Unauthorized'));

        $response = (new HcmEmailSettingsController())->mailtrapStatus($request, $service);

        $this->assertSame(200, $response->getStatusCode());
        $payload = $response->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertFalse($payload['data']['connected']);
        $this->assertSame('Mailtrap API request failed (401): Unauthorized', $payload['data']['error']);
    }
}

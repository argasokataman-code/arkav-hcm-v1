<?php

namespace Tests\Unit;

use App\Services\MailtrapAccountApiService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MailtrapAccountApiServiceTest extends TestCase
{
    public function test_it_lists_api_tokens_with_bearer_auth(): void
    {
        config()->set('services.mailtrap.api_token', 'mt_test_token');
        config()->set('services.mailtrap.account_id', 3229);
        config()->set('services.mailtrap.base_url', 'https://mailtrap.io/api');
        config()->set('services.mailtrap.timeout', 10);

        Http::fake([
            'mailtrap.io/api/accounts/3229/api_tokens' => Http::response([
                [
                    'id' => 12345,
                    'name' => 'My API Token',
                    'last_4_digits' => 'x7k9',
                    'created_by' => 'user@example.com',
                    'expires_at' => null,
                ],
            ], 200),
        ]);

        $service = new MailtrapAccountApiService();
        $tokens = $service->listApiTokens();

        $this->assertCount(1, $tokens);
        $this->assertSame(12345, $tokens[0]['id']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://mailtrap.io/api/accounts/3229/api_tokens'
                && $request->header('Authorization')[0] === 'Bearer mt_test_token';
        });
    }

    public function test_it_throws_when_mailtrap_token_missing(): void
    {
        config()->set('services.mailtrap.api_token', '');
        config()->set('services.mailtrap.account_id', 3229);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MAILTRAP_API_TOKEN is not configured.');

        (new MailtrapAccountApiService())->listApiTokens();
    }
}

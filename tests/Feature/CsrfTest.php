<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsrfTest extends TestCase
{
    use RefreshDatabase;

    public function test_state_changing_requests_reject_missing_csrf_token(): void
    {
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->post('/login', ['email' => 'unknown@example.test', 'password' => 'invalid'])->assertStatus(419);
        $this->post('/logout')->assertStatus(419);
        $this->post('/assets/import/preview')->assertStatus(419);
        $this->post('/assets/import/commit')->assertStatus(419);
        $this->post('/asset-categories')->assertStatus(419);
    }
}

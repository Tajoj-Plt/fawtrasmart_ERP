<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSubscription;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnsureSubscriptionUnlinkedTest extends TestCase
{
    public function test_unlinked_system_is_allowed_when_assume_active(): void
    {
        config([
            'fawtra.enforce_subscription' => true,
            'fawtra.assume_active_when_unlinked' => true,
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => new User(['email' => 'admin@example.com']));

        $response = app(EnsureSubscription::class)->handle(
            $request,
            fn () => response('ok')
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function test_unlinked_system_is_blocked_when_not_assumed_active(): void
    {
        config([
            'fawtra.enforce_subscription' => true,
            'fawtra.assume_active_when_unlinked' => false,
        ]);

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => new User(['email' => 'admin@example.com']));

        $response = app(EnsureSubscription::class)->handle(
            $request,
            fn () => response('ok')
        );

        $this->assertSame(402, $response->getStatusCode());
    }
}

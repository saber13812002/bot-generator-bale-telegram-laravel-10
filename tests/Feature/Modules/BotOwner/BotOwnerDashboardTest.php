<?php

namespace Tests\Feature\Modules\BotOwner;

use App\Modules\BotOwner\Models\BotOwner;
use App\Modules\BotOwner\Services\BotOwnerAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotOwnerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_login(): void
    {
        $this->get('/bots/dashboard')->assertRedirect(route('bot-owner.login'));
    }

    public function test_dashboard_shows_for_authenticated_owner(): void
    {
        $owner = BotOwner::create([
            'phone' => '989123456789',
            'status' => 'active',
        ]);

        $this->withSession([BotOwnerAuthService::SESSION_KEY => $owner->id])
            ->get('/bots/dashboard')
            ->assertStatus(200)
            ->assertSee('989123456789');
    }
}

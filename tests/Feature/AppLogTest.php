<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_app_logs_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('app-logs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AppLog/Index')
                ->has('logs')
                ->has('system')
                ->where('system.app_name', config('app.name'))
                ->where('logs.0.version', '1.4.0')
            );
    }

    public function test_kasir_cannot_access_app_logs_page(): void
    {
        $kasir = User::factory()->create(['role' => 'kasir']);

        $this->actingAs($kasir)
            ->get(route('app-logs.index'))
            ->assertForbidden();
    }

    public function test_admin_can_search_app_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('app-logs.index', ['search' => 'Smart Re-Alerting']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AppLog/Index')
                ->has('logs', 1)
                ->where('logs.0.version', '1.2.0')
            );
    }
}

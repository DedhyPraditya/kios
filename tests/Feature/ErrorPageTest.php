<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_tak_dikenal_memakai_halaman_error_kustom(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 404));
    }

    public function test_kasir_membuka_menu_admin_dapat_halaman_403_kustom(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'kasir']))
            ->get(route('products.index'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 403));
    }

    public function test_tamu_yang_belum_masuk_tetap_diarahkan_ke_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_permintaan_json_tidak_mendapat_halaman_inertia(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'kasir']))
            ->getJson(route('products.index'))
            ->assertForbidden()
            ->assertJsonStructure(['message']);
    }
}

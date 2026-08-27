<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('SUN ENTERPRICES');
        $response->assertSee('Hardware Store & Construction');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_cashier_login_shows_welcome_with_their_name(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('cashier');

        $user = User::factory()->create(['name' => 'Kasun Perera']);
        $user->assignRole('cashier');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('Kasun Perera')
            ->assertSee('cashier-welcome', false);
    }

    public function test_non_cashier_login_does_not_flash_welcome(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('admin');

        $user = User::factory()->create(['name' => 'Admin User']);
        $user->assignRole('admin');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('cashier-welcome', false);
    }
}

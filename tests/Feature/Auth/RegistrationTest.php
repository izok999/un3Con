<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.register');
    }

    public function test_new_users_can_register(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('documento', '1234567')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_exported_users_can_complete_their_registration_with_documento(): void
    {
        $user = User::factory()->create([
            'name' => 'Alumno Exportado',
            'documento' => '7654321',
            'email' => 'alumno-7654321@consultor.invalid',
            'password' => Hash::make('legacy-random-password'),
            'email_verified_at' => null,
        ]);

        $component = Volt::test('pages.auth.register')
            ->set('name', 'Alumno Activado')
            ->set('documento', '7.654.321')
            ->set('email', 'alumno.activado@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));

        $user->refresh();

        $this->assertSame('Alumno Activado', $user->name);
        $this->assertSame('alumno.activado@example.com', $user->email);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertAuthenticatedAs($user);
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_route_redirects_to_login_with_first_access_hint(): void
    {
        $response = $this->get('/register');

        $response
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('status');
    }

    public function test_registration_screen_is_no_longer_available(): void
    {
        $this->assertFalse(
            view()->exists('livewire.pages.auth.register'),
            'El registro público permitía declarar una cédula ajena sin probar su pertenencia. El alta de alumnos ocurre en el login con cédula + PIN.',
        );
    }

    public function test_exported_account_cannot_be_claimed_through_the_registration_route(): void
    {
        $user = User::factory()->create([
            'name' => 'Alumno Exportado',
            'documento' => '7654321',
            'email' => 'alumno-7654321@consultor.invalid',
            'password' => Hash::make('legacy-random-password'),
            'email_verified_at' => null,
        ]);

        $this->get('/register')->assertRedirect(route('login', absolute: false));

        $user->refresh();

        $this->assertSame('alumno-7654321@consultor.invalid', $user->email);
        $this->assertTrue(Hash::check('legacy-random-password', $user->password));
        $this->assertGuest();
    }
}

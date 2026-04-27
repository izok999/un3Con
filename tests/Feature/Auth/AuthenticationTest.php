<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\AlumnoExternoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Mockery;
use stdClass;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.login');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password');

        $component->call('login');

        $component
            ->assertHasErrors()
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_alumnos_can_authenticate_using_legacy_documento_and_pin(): void
    {
        $alumno = new stdClass;
        $alumno->per_nombre = 'Juan';
        $alumno->per_apelli = 'Perez';
        $alumno->alu_perdoc = '1234567';

        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('autenticarConsultor')
            ->once()
            ->with('1234567', '4321', '127.0.0.1')
            ->andReturn(['logged' => true]);
        $service->shouldReceive('resolverAlumno')
            ->once()
            ->with('1234567')
            ->andReturn($alumno);

        $this->app->instance(AlumnoExternoService::class, $service);

        $component = Volt::test('pages.auth.login')
            ->set('legacyForm.documento', '1234567')
            ->set('legacyForm.pin', '4321');

        $component->call('loginAlumno');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('alumno.carreras', absolute: false));

        $user = User::query()->firstWhere('documento', '1234567');

        $this->assertNotNull($user);
        $this->assertSame('Juan Perez', $user->name);
        $this->assertSame('alumno-1234567@consultor.invalid', $user->email);
        $this->assertTrue($user->hasRole('ALUMNO'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_alumnos_can_not_authenticate_using_invalid_legacy_credentials(): void
    {
        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('autenticarConsultor')
            ->once()
            ->with('1234567', 'wrong-pin', '127.0.0.1')
            ->andReturnNull();
        $service->shouldNotReceive('resolverAlumno');

        $this->app->instance(AlumnoExternoService::class, $service);

        $component = Volt::test('pages.auth.login')
            ->set('legacyForm.documento', '1234567')
            ->set('legacyForm.pin', 'wrong-pin');

        $component->call('loginAlumno');

        $component
            ->assertHasErrors(['legacyForm.documento'])
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_navigation_menu_can_be_rendered(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response
            ->assertOk()
            ->assertSeeVolt('layout.navigation');
    }

    public function test_users_can_logout(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('layout.navigation');

        $component->call('logout');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
    }
}

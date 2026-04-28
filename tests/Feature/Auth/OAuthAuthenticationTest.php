<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\AlumnoExternoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Volt\Volt;
use Mockery;
use Spatie\Permission\Models\Role;
use stdClass;
use Tests\TestCase;

class OAuthAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_google_user_is_redirected_to_documento_linking(): void
    {
        Socialite::fake('google', $this->fakeGoogleUser(
            id: 'google-123',
            name: 'Isaac Britez',
            email: 'isaacbritez99@gmail.com',
            avatar: 'https://example.com/avatar.jpg',
        ));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('auth.oauth.link-documento'));

        /** @var User|null $user */
        $user = User::query()->where('auth_provider', 'google')->first();

        $this->assertNotNull($user);
        $this->assertSame('google-123', $user->auth_provider_id);
        $this->assertSame('isaacbritez99@gmail.com', $user->email);
        $this->assertNull($user->documento);
        $this->assertTrue($user->hasRole('ALUMNO'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_existing_google_user_can_sign_in_again_and_receives_default_role(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'name' => 'Isaac Britez',
            'email' => 'isaacbritez99@gmail.com',
            'documento' => '1234567',
            'password' => null,
            'auth_provider' => 'google',
            'auth_provider_id' => 'google-123',
            'avatar' => null,
        ]);

        Socialite::fake('google', $this->fakeGoogleUser(
            id: 'google-123',
            name: 'Isaac Rafael Britez',
            email: 'isaacbritez99@gmail.com',
            avatar: 'https://example.com/new-avatar.jpg',
        ));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('alumno.carreras'));

        $user->refresh();

        $this->assertSame('https://example.com/new-avatar.jpg', $user->avatar);
        $this->assertTrue($user->hasRole('ALUMNO'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_login_is_rejected_when_email_already_belongs_to_another_account(): void
    {
        User::factory()->create([
            'email' => 'isaacbritez99@gmail.com',
            'documento' => '1234567',
            'auth_provider' => null,
            'auth_provider_id' => null,
        ]);

        Socialite::fake('google', $this->fakeGoogleUser(
            id: 'google-999',
            name: 'Isaac Britez',
            email: 'isaacbritez99@gmail.com',
            avatar: 'https://example.com/avatar.jpg',
        ));

        $response = $this->get(route('auth.google.callback'));

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['oauth']);

        $this->assertGuest();
        $this->assertSame(1, User::query()->count());
    }

    public function test_oauth_users_without_documento_are_redirected_before_dashboard_access(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'isaacbritez99@gmail.com',
            'documento' => null,
            'password' => null,
            'auth_provider' => 'google',
            'auth_provider_id' => 'google-123',
        ]);

        Role::findOrCreate('ALUMNO', 'web');
        $user->assignRole('ALUMNO');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('auth.oauth.link-documento'));
    }

    public function test_oauth_users_can_link_their_documento_and_merge_into_an_existing_student_account(): void
    {
        Role::findOrCreate('ALUMNO', 'web');

        /** @var User $existingStudent */
        $existingStudent = User::factory()->create([
            'name' => 'Isaac Rafael Britez Paredes',
            'email' => 'alumno-1234567@consultor.invalid',
            'documento' => '1234567',
            'auth_provider' => null,
            'auth_provider_id' => null,
            'avatar' => null,
        ]);
        $existingStudent->assignRole('ALUMNO');

        /** @var User $oauthUser */
        $oauthUser = User::factory()->create([
            'name' => 'Isaac Britez',
            'email' => 'isaacbritez99@gmail.com',
            'documento' => null,
            'password' => null,
            'auth_provider' => 'google',
            'auth_provider_id' => 'google-123',
            'avatar' => 'https://example.com/avatar.jpg',
        ]);

        $alumno = new stdClass;
        $alumno->per_nombre = 'Isaac Rafael';
        $alumno->per_apelli = 'Britez Paredes';
        $alumno->alu_perdoc = '1234567';

        $service = Mockery::mock(AlumnoExternoService::class);
        $service->shouldReceive('resolverAlumno')
            ->once()
            ->with('1234567')
            ->andReturn($alumno);

        $this->app->instance(AlumnoExternoService::class, $service);
        $this->actingAs($oauthUser);

        $component = Volt::test('pages.auth.link-documento')
            ->set('documento', '1234567');

        $component->call('linkDocumento');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('alumno.carreras', absolute: false));

        $existingStudent->refresh();

        $this->assertSame('google', $existingStudent->auth_provider);
        $this->assertSame('google-123', $existingStudent->auth_provider_id);
        $this->assertSame('isaacbritez99@gmail.com', $existingStudent->email);
        $this->assertSame('https://example.com/avatar.jpg', $existingStudent->avatar);
        $this->assertTrue($existingStudent->hasRole('ALUMNO'));
        $this->assertAuthenticatedAs($existingStudent);
        $this->assertNull($oauthUser->fresh());
    }

    protected function fakeGoogleUser(string $id, string $name, string $email, string $avatar): SocialiteUser
    {
        return (new SocialiteUser)->map([
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'avatar' => $avatar,
        ]);
    }
}

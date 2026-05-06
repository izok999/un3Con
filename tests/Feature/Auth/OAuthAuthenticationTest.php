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

    protected string $longAvatarUrl = 'https://lh3.googleusercontent.com/a-/ALV-UjXFfsyZsSUknluTHD7IJWipIBaiKRlWmQSe-Eur85Wl4K_IifyO_V0Esw0_OgoRQL6VhAgFk_0WQ6Ia2RKjVvi7GBnUvsSLWqKGbZWQOUNYDmsiZmBFZu4JbEdKwGwn11TtvlWylT1OFEmK2WbUWd4tU369hKCgptQn5rA5YkplOsMxc0j42Z1oGIJn2l30X0ydLECC0r3YoXnX2wusFoyZI4yrNJvKx2-TpRSOLmbEyWRVw_qoXhkdf7U3MihK-OMjX-aUIXkZN7XcDU_PW3U0fvV6j4yvJf1HHYQFHQxkIl5X7ylKYfcO2jPio_v9ZHKBHTS7evJkEANvXSgepMbi-wXqFt3aiyskJf2YbGLEvpzCpO6S_EhIy6sKlH3XHoPwsb4NqJQmXbgCWnYLkSGejsjanNAHQk_Hf9LdtADoRFVFdvtne_mfUPJNUCug1oL74kjMsBlgV6IeLf38NUKtXeCN3WRTIY3hy6bKuKEkBDrExBRa26ZKUeCd8WHoQHzjFoBBKslmc91TFxVDYHhlB7hrwk5xrgUAzrMRDV-9Ha17yQ2q_BT6egtZZEsKmafdmFNpOTCyXpslfk4NCuS-OFTb_BrvUNty03mvzJ2NkAvjvafb09AHoobx8juY_JRAu1_RJVLLfwdIxN1aaogqwfumYV1uENKgI9Wu0bqbpyzJeBT56E3odxm-I9eSTglgOMuOLfl7EVW0_bpiX9VQQX2kUmJ8iL4H38M4mj5c86JHlkjNFRFBD5lONYz-F_ynwwnJ6z40ph-1fTFgIgGO7C1BUD_nOgR_exqRYqwt4wPoxZYmWvXUJVYmvkwcWGRcbqQ9yEPCGK17kmMuP6r49uaOBjHkDtAuNhlXSOZl2SFvtO04lHRZqKbKbMYTCC4OTLon7U4YAs5fmno-OXRnscWuVvJ3CaB5xSCZZBi5OczmoImuL2QoUZwqypnVRTR2Fm5-sQuitOAMp87bZGKQYd_ZzKTKegaxY6r1DaBD6_OiRCjR8wI0yZV5sxEZdDXS148017re_A_HPuIi6Mz5uQ_vP4Q6fLn5XzWDLTwuUWMZtY6uRmJ9Zg=s96-c';

    public function test_new_google_user_is_redirected_to_documento_linking(): void
    {
        Socialite::fake('google', $this->fakeGoogleUser(
            id: 'google-123',
            name: 'Isaac Britez',
            email: 'isaacbritez99@gmail.com',
            avatar: $this->longAvatarUrl,
        ));

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('auth.oauth.link-documento'));

        /** @var User|null $user */
        $user = User::query()->where('auth_provider', 'google')->first();

        $this->assertNotNull($user);
        $this->assertSame('google-123', $user->auth_provider_id);
        $this->assertSame('isaacbritez99@gmail.com', $user->email);
        $this->assertSame($this->longAvatarUrl, $user->avatar);
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

    public function test_existing_local_user_can_start_google_link_flow_from_dedicated_redirect(): void
    {
        Socialite::fake('google');

        $response = $this->get(route('auth.google.link-existing'));

        $response->assertRedirect();
        $this->assertSame('link-existing', session('auth.google.intent'));
    }

    public function test_authenticated_users_can_start_google_link_flow_from_profile_cta(): void
    {
        Socialite::fake('google');

        $user = User::factory()->create([
            'auth_provider' => null,
            'auth_provider_id' => null,
        ]);

        $response = $this->actingAs($user)->get(route('auth.google.link-existing'));

        $response->assertRedirect();
        $this->assertSame('link-existing', session('auth.google.intent'));
    }

    public function test_existing_local_user_can_link_google_account_when_using_explicit_flow(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'name' => 'Isaac Britez',
            'email' => 'isaacbritez99@gmail.com',
            'documento' => '1234567',
            'auth_provider' => null,
            'auth_provider_id' => null,
            'avatar' => null,
        ]);

        Socialite::fake('google', $this->fakeGoogleUser(
            id: 'google-123',
            name: 'Isaac Rafael Britez',
            email: 'isaacbritez99@gmail.com',
            avatar: $this->longAvatarUrl,
        ));

        $this->withSession(['auth.google.intent' => 'link-existing']);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('alumno.carreras'));

        $user->refresh();

        $this->assertSame('google', $user->auth_provider);
        $this->assertSame('google-123', $user->auth_provider_id);
        $this->assertSame($this->longAvatarUrl, $user->avatar);
        $this->assertAuthenticatedAs($user);
        $this->assertFalse(session()->has('auth.google.intent'));
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

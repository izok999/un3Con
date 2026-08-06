<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-password-form')
            ->set('current_password', 'password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-password-form')
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword');

        $component
            ->assertHasErrors(['current_password'])
            ->assertNoRedirect();
    }

    public function test_wrong_current_password_is_reported_as_soon_as_the_field_is_left(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-password-form')
            ->set('current_password', 'wrong-password')
            ->assertHasErrors(['current_password' => 'current_password']);
    }

    public function test_new_password_must_be_different_from_the_current_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-password-form')
            ->set('current_password', 'password')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('updatePassword')
            ->assertHasErrors(['password' => 'different']);

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_new_password_cannot_be_longer_than_the_supported_maximum(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-password-form')
            ->set('current_password', 'password')
            ->set('password', str_repeat('a', 65))
            ->set('password_confirmation', str_repeat('a', 65))
            ->call('updatePassword')
            ->assertHasErrors(['password' => 'max']);
    }

    public function test_the_new_password_the_user_typed_survives_a_failed_submit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-password-form')
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword')
            ->assertSet('password', 'new-password')
            ->assertSet('password_confirmation', 'new-password')
            ->assertSet('current_password', '');
    }

    public function test_requirement_checklist_reacts_to_what_is_typed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-password-form')
            ->set('current_password', 'password')
            ->set('password', 'short')
            ->set('password_confirmation', 'other')
            ->assertSee(__('Al menos :min caracteres.', ['min' => 8]))
            ->assertSee(__('Las contraseñas no coinciden. Volvé a escribirlas.'));

        $checks = collect($component->instance()->passwordChecks())
            ->pluck('state', 'label');

        $this->assertSame('fail', $checks[__('Al menos :min caracteres.', ['min' => 8])]);
        $this->assertSame('ok', $checks[__('Como máximo :max caracteres.', ['max' => 64])]);
        $this->assertSame('ok', $checks[__('Distinta a tu contraseña actual.')]);
        $this->assertSame('fail', $checks[__('Las dos contraseñas coinciden.')]);
    }
}

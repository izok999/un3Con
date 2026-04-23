<?php

namespace Tests\Feature\Console;

use App\Models\User;
use App\Services\AlumnoExternoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\LazyCollection;
use Mockery\MockInterface;
use Tests\TestCase;

class SyncLegacyAlumnoUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_new_local_users_from_legacy_students(): void
    {
        $this->mockLegacyStudents([
            [
                'alu_id' => 1,
                'alu_perdoc' => '1234567',
                'per_nombre' => 'Juan',
                'per_apelli' => 'Perez',
                'duplicate_count' => 1,
            ],
            [
                'alu_id' => 2,
                'alu_perdoc' => '2345678',
                'per_nombre' => 'Ana',
                'per_apelli' => 'Gomez',
                'duplicate_count' => 1,
            ],
        ]);

        $this->artisan('alumnos:sync-legacy-users')
            ->expectsOutputToContain('Sincronización finalizada.')
            ->expectsOutputToContain('Creados: 2')
            ->expectsOutputToContain('Actualizados: 0')
            ->assertSuccessful();

        /** @var User $juan */
        $juan = User::query()->firstWhere('documento', '1234567');
        /** @var User $ana */
        $ana = User::query()->firstWhere('documento', '2345678');

        $this->assertNotNull($juan);
        $this->assertNotNull($ana);
        $this->assertSame('Juan Perez', $juan->name);
        $this->assertSame('Ana Gomez', $ana->name);
        $this->assertSame('alumno-1234567@consultor.invalid', $juan->email);
        $this->assertNull($juan->email_verified_at);
        $this->assertTrue($juan->hasRole('ALUMNO'));
        $this->assertTrue($ana->hasRole('ALUMNO'));
    }

    public function test_command_updates_existing_user_and_preserves_email(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'documento' => '1234567',
            'name' => 'Nombre Viejo',
            'email' => 'isaac@example.com',
        ]);

        $this->mockLegacyStudents([
            [
                'alu_id' => 1,
                'alu_perdoc' => '1234567',
                'per_nombre' => 'Isaac Rafael',
                'per_apelli' => 'Britez Paredes',
                'duplicate_count' => 1,
            ],
        ]);

        $this->artisan('alumnos:sync-legacy-users')
            ->expectsOutputToContain('Actualizados: 1')
            ->assertSuccessful();

        $user->refresh();

        $this->assertSame('Isaac Rafael Britez Paredes', $user->name);
        $this->assertSame('isaac@example.com', $user->email);
        $this->assertTrue($user->hasRole('ALUMNO'));
    }

    public function test_command_respects_dry_run(): void
    {
        $this->mockLegacyStudents([
            [
                'alu_id' => 1,
                'alu_perdoc' => '7654321',
                'per_nombre' => 'Maria',
                'per_apelli' => 'Lopez',
                'duplicate_count' => 1,
            ],
        ]);

        $this->artisan('alumnos:sync-legacy-users', ['--dry-run' => true])
            ->expectsOutputToContain('Creados: 1')
            ->assertSuccessful();

        $this->assertNull(User::query()->firstWhere('documento', '7654321'));
    }

    public function test_command_respects_only_missing_option(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'documento' => '9999999',
            'name' => 'Usuario Local',
            'email' => 'local@example.com',
        ]);

        $this->mockLegacyStudents([
            [
                'alu_id' => 1,
                'alu_perdoc' => '9999999',
                'per_nombre' => 'Nombre',
                'per_apelli' => 'Externo',
                'duplicate_count' => 1,
            ],
        ]);

        $this->artisan('alumnos:sync-legacy-users', ['--solo-faltantes' => true])
            ->expectsOutputToContain('Omitidos: 1')
            ->assertSuccessful();

        $user->refresh();

        $this->assertSame('Usuario Local', $user->name);
        $this->assertSame('local@example.com', $user->email);
    }

    /**
     * @param  array<int, array<string, mixed>>  $legacyStudents
     */
    protected function mockLegacyStudents(array $legacyStudents, ?string $documento = null): void
    {
        $this->mock(AlumnoExternoService::class, function (MockInterface $mock) use ($legacyStudents, $documento): void {
            $mock->shouldReceive('alumnosParaSincronizar')
                ->once()
                ->with($documento)
                ->andReturn(LazyCollection::make($legacyStudents));
        });
    }
}
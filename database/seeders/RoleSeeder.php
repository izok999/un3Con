<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\AcademicUnit;
use App\Models\User;
use App\Models\UserAcademicUnitScope;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleName::cases() as $roleName) {
            Role::findOrCreate($roleName->value, 'web');
        }

        $this->createDefaultUser(
            email: 'admin@une.edu.py',
            name: 'Administrador General',
            documento: '0000000',
            roleName: RoleName::Admin,
        );

        $this->createDefaultUser(
            email: 'admin.uac@une.edu.py',
            name: 'Administrador Unidad Academica',
            documento: '0000001',
            roleName: RoleName::AdminUnidadAcademica,
        );

        $this->createDefaultUser(
            email: 'funcionario@une.edu.py',
            name: 'Funcionario de Prueba',
            documento: '0000002',
            roleName: RoleName::Funcionario,
        );

        $this->createDefaultUser(
            email: 'alumno@une.edu.py',
            name: 'Alumno de Prueba',
            documento: '1111111',
            roleName: RoleName::Alumno,
        );
    }

    protected function createDefaultUser(
        string $email,
        string $name,
        string $documento,
        RoleName $roleName,
    ): void {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'documento' => $documento,
                'password' => Hash::make('password'),
            ],
        );

        $user->syncRoles([$roleName->value]);

        if ($roleName === RoleName::AdminUnidadAcademica) {
            $this->assignDefaultAcademicUnitScope($user);
        }
    }

    protected function assignDefaultAcademicUnitScope(User $user): void
    {
        $academicUnit = Schema::hasTable('academic_units')
            ? AcademicUnit::query()->firstWhere('slug', 'ingenieria-agronomica')
            : null;

        if ($academicUnit) {
            UserAcademicUnitScope::query()
                ->where('user_id', $user->id)
                ->whereNull('academic_unit_id')
                ->delete();

            UserAcademicUnitScope::query()->firstOrCreate([
                'user_id' => $user->id,
                'academic_unit_id' => $academicUnit->id,
            ], [
                'sed_id' => (int) $academicUnit->legacy_sede_ids[0],
            ]);

            return;
        }

        UserAcademicUnitScope::query()->firstOrCreate([
            'user_id' => $user->id,
            'sed_id' => 8,
        ]);
    }
}

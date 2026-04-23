<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear roles
        $adminRole = Role::firstOrCreate(['name' => 'ADMIN']);
        $alumnoRole = Role::firstOrCreate(['name' => 'ALUMNO']);

        // 2. Crear usuario admin por defecto
        $admin = User::firstOrCreate(
            ['email' => 'admin@une.edu.py'],
            [
                'name' => 'Administrador',
                'documento' => '0000000',
                'password' => bcrypt('password'),
            ]
        );
        $admin->assignRole($adminRole);

        // 3. Crear usuario alumno de prueba
        $alumno = User::firstOrCreate(
            ['email' => 'alumno@une.edu.py'],
            [
                'name' => 'Alumno de Prueba',
                'documento' => '1111111', // Documento ficticio
                'password' => bcrypt('password'),
            ]
        );
        $alumno->assignRole($alumnoRole);
    }
}
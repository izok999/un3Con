<?php

namespace Database\Seeders;

use App\Models\AcademicUnit;
use Illuminate\Database\Seeder;

class AcademicUnitSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->academicUnits() as $academicUnit) {
            AcademicUnit::query()->updateOrCreate(
                ['slug' => $academicUnit['slug']],
                $academicUnit,
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function academicUnits(): array
    {
        return [
            [
                'slug' => 'ingenieria-agronomica',
                'name' => 'Facultad de Ingeniería Agronómica',
                'address' => 'km. 17 1/2 - Minga Guazú',
                'office_hours' => ['07:00 a 17:00 hs'],
                'phone_numbers' => ['+595 21 327 1415'],
                'email_addresses' => ['secretgeneral@fia.une.edu.py'],
                'website_url' => 'https://www.fia.une.edu.py/',
                'image_url' => 'http://www.une.edu.py/web/images/unidades/eco.jpg',
                'legacy_sede_ids' => [1, 8, 21, 23, 27, 28],
                'is_active' => true,
            ],
            [
                'slug' => 'ciencias-economicas',
                'name' => 'Facultad de Ciencias Económicas',
                'address' => 'Campus Universitario Km 8 Acaray',
                'office_hours' => ['07:00 a 22:00 hs'],
                'phone_numbers' => ['+595 61 575 056'],
                'email_addresses' => ['sgfceune@fceune.edu.py'],
                'website_url' => 'http://www.fceune.edu.py',
                'image_url' => 'http://www.une.edu.py/web/images/unidades/fafi2.jpg',
                'legacy_sede_ids' => [3, 7, 15, 17, 22, 24],
                'is_active' => true,
            ],
            [
                'slug' => 'filosofia',
                'name' => 'Facultad de Filosofía',
                'address' => 'Campus Universitario Km 8 Acaray',
                'office_hours' => ['07:00 a 21:30 hs'],
                'phone_numbers' => ['+595 61 574 930/931 - Central', '+595 675 265 498 - Mallorquín', '+595 983 513 911 - Santa Rita'],
                'email_addresses' => ['facultadfilosofia@filosofiaune.edu.py'],
                'website_url' => 'http://www.filosofiaune.edu.py',
                'image_url' => 'http://www.une.edu.py/web/images/unidades/politecnica.jpg',
                'legacy_sede_ids' => [4, 14, 16],
                'is_active' => true,
            ],
            [
                'slug' => 'politecnica',
                'name' => 'Facultad Politécnica',
                'address' => 'Campus Universitario Km 8 Acaray',
                'office_hours' => ['07:00 a 21:30 hs'],
                'phone_numbers' => ['+595 61 575112', '+595 975 553 702'],
                'email_addresses' => ['secretaria@fpune.edu.py', 'gestion_documental@fpune.edu.py'],
                'website_url' => 'http://www.fpune.edu.py',
                'image_url' => 'http://www.une.edu.py/web/images/unidades/derecho2.jpg',
                'legacy_sede_ids' => [5],
                'is_active' => true,
            ],
            [
                'slug' => 'derecho-ciencias-sociales',
                'name' => 'Facultad de Derecho y Ciencias Sociales',
                'address' => 'Campus Universitario Km 8 Acaray',
                'office_hours' => ['07:30 a 13:00 hs - Área 3', '16:00 a 22:00 hs - Central'],
                'phone_numbers' => ['+595 21 338 283 - Central', '+595 21 338 9695 - Área 3'],
                'email_addresses' => ['secretaria.derecho@gmail.com'],
                'website_url' => 'https://derechoune.edu.py/web/historia-de-la-facultad',
                'image_url' => 'http://www.une.edu.py/web/images/unidades/facisa.jpg',
                'legacy_sede_ids' => [2, 9, 25, 29],
                'is_active' => true,
            ],
            [
                'slug' => 'ciencias-salud',
                'name' => 'Facultad de Ciencias de la Salud',
                'address' => 'Minga Guazú Km 16',
                'office_hours' => ['07:00 a 16:00 hs'],
                'phone_numbers' => ['+595 644 21290/21210', '+595 984 582 239'],
                'email_addresses' => ['comunicacioninstitucional@facisaune.edu.py', 'secretariageneral@facisaune.edu.py'],
                'website_url' => 'http://www.facisaune.edu.py',
                'image_url' => 'http://www.une.edu.py/web/images/unidades/facisa.jpg',
                'legacy_sede_ids' => [6],
                'is_active' => true,
            ],
        ];
    }
}

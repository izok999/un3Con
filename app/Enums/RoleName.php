<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'ADMIN';
    case AdminUnidadAcademica = 'ADMIN_UNIDAD_ACADEMICA';
    case Funcionario = 'FUNCIONARIO';
    case Alumno = 'ALUMNO';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function administrationValues(): array
    {
        return [
            self::Admin->value,
            self::AdminUnidadAcademica->value,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function welcomeValues(): array
    {
        return [
            self::Admin->value,
            self::AdminUnidadAcademica->value,
            self::Funcionario->value,
        ];
    }

    public static function middleware(self ...$roles): string
    {
        return implode('|', array_map(
            static fn (self $role): string => $role->value,
            $roles,
        ));
    }
}

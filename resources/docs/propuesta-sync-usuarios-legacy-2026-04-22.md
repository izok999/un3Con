# Propuesta de sincronización masiva de usuarios legacy

Fecha: 2026-04-22

## Objetivo

Definir un comando Artisan para crear o actualizar usuarios locales `users` a partir de la base de datos vieja, usando el `documento` como vínculo principal con el alumno externo.

La idea no es copiar los datos académicos al sistema nuevo. La base externa sigue siendo la fuente de verdad para carreras, extracto, materias, deudas y demás consultas. Lo que se sincroniza localmente es solamente la identidad de acceso.

La sincronización planteada en este documento es masiva sobre todo el universo de alumnos disponible en la vista externa principal, no solamente sobre alumnos activos.

## Decisión de diseño

La propuesta es sincronizar identidad, no migrar el esquema completo de autenticación vieja.

Eso implica:

- Crear o actualizar registros en `users`.
- Asignar el rol `ALUMNO`.
- Conservar `documento` como clave de enlace con la base externa.
- No copiar el `PIN` viejo a `password` local.
- No importar datos académicos al PostgreSQL local.

Con este enfoque, el alumno puede seguir entrando con `documento + PIN` del consultor viejo, y además queda preparado para una transición posterior a acceso local por email si se decide habilitarlo.

## Firma propuesta del comando

```bash
vendor/bin/sail artisan alumnos:sync-legacy-users
```

Opciones sugeridas:

```bash
vendor/bin/sail artisan alumnos:sync-legacy-users \
  {--documento=} \
  {--solo-faltantes} \
  {--chunk=500} \
  {--dry-run}
```

### Significado de las opciones

- `--documento=`: sincroniza un solo alumno. Sirve para reprocesar casos puntuales.
- `--solo-faltantes`: crea sólo los usuarios que todavía no existen localmente.
- `--chunk=500`: cantidad de registros a procesar por lote.
- `--dry-run`: muestra qué haría el comando sin persistir cambios.

## Ejemplos de uso

Sincronización completa:

```bash
vendor/bin/sail artisan alumnos:sync-legacy-users
```

Probar un caso puntual sin escribir cambios:

```bash
vendor/bin/sail artisan alumnos:sync-legacy-users --documento=5413971 --dry-run
```

Sincronizar todos los alumnos que todavía no tienen cuenta local:

```bash
vendor/bin/sail artisan alumnos:sync-legacy-users --solo-faltantes
```

## Fuente de datos externa

La sincronización debería leer desde la conexión `pgsql_externa`.

### Fuente principal

- `sh_maestros.vw_alumnos_00`

Campos esperados:

- `alu_id`
- `alu_perdoc`
- `per_nombre`
- `per_apelli`

La consulta base del comando debe recorrer todos los registros disponibles en esta vista y sólo reducirse cuando se use `--documento=` para un caso puntual.

## Reglas de sincronización

### 1. Clave de vínculo

El vínculo local-external se resuelve siempre por `documento`.

- Si el alumno externo no tiene `alu_perdoc`, se omite y se reporta.
- Si el `documento` ya existe en `users`, se actualiza ese usuario.
- Si no existe, se crea un nuevo `User`.

### 2. Nombre del usuario

Se arma con:

- `per_nombre`
- `per_apelli`

Fallback:

- `Alumno` si el nombre no viene completo.

### 3. Email local

La estrategia recomendada para la sincronización masiva es conservadora:

- Si el usuario local ya tiene email, se respeta.
- Si la fuente externa no ofrece un email confiable, se genera uno técnico:

```text
alumno-{documento}@consultor.invalid
```

- Si aparece conflicto de email con otro usuario local y el conflicto no es por el mismo `documento`, se omite el registro y se reporta.

### 4. Password local

No se debe copiar el `PIN` viejo como contraseña local.

Para usuarios nuevos:

- se genera una contraseña aleatoria,
- o se deja una clave imposible de conocer por el usuario,
- y el acceso legacy sigue funcionando vía `documento + PIN`.

Eso evita mezclar el esquema histórico de autenticación con el hash local de Laravel.

### 5. Verificación de email

Para la sincronización masiva, lo más seguro es no asumir verificación de email salvo que el dato venga validado por una fuente confiable.

Recomendación:

- si el email es técnico `@consultor.invalid`, dejar `email_verified_at` en `null`,
- si existe un proceso administrativo posterior para validar email real, recién ahí habilitar acceso local por email.

### 6. Rol

Todo usuario sincronizado desde alumnos debe quedar con rol `ALUMNO`.

El comando debe:

- asegurar que el rol exista,
- asignarlo si el usuario no lo tiene todavía.

## Política de conflictos

El comando no debe intentar resolver conflictos dudosos automáticamente.

Casos a reportar como `skipped` o `conflict`:

- alumno externo sin `documento`,
- email local ya usado por otro `documento`,
- más de un registro externo para el mismo `documento`,
- error de conexión o lectura en la base externa,
- actualización local fallida.

El resultado final debería mostrar algo así:

```text
Procesados: 1250
Creados: 430
Actualizados: 760
Omitidos: 54
Conflictos: 6
Errores: 0
```

## Arquitectura sugerida

Aunque hoy el proyecto sólo tiene un comando closure en `routes/console.php`, para este caso conviene separar responsabilidades.

### Opción recomendada

#### 1. Acción o servicio reutilizable

Archivo sugerido:

```text
app/Services/LegacyAlumnoUserSyncService.php
```

Responsabilidades:

- consultar alumnos externos,
- transformar los datos,
- aplicar reglas de sincronización,
- devolver contadores y conflictos.

#### 2. Comando Artisan

Archivo sugerido:

```text
app/Console/Commands/SyncLegacyAlumnoUsersCommand.php
```

Responsabilidades:

- parsear opciones,
- invocar el servicio,
- mostrar progreso,
- imprimir resumen final.

#### 3. Reutilización futura en ADMIN

La misma acción o servicio puede reutilizarse luego desde el panel de administración para crear o actualizar accesos individuales desde `admin/consulta-alumno`.

## Borrador de implementación

### Comando

```php
<?php

namespace App\Console\Commands;

use App\Services\LegacyAlumnoUserSyncService;
use Illuminate\Console\Command;

class SyncLegacyAlumnoUsersCommand extends Command
{
    protected $signature = 'alumnos:sync-legacy-users
        {--documento= : Sincroniza un solo documento}
        {--solo-faltantes : Solo crea usuarios inexistentes}
        {--chunk=500 : Tamaño del lote}
        {--dry-run : Simula el proceso sin guardar cambios}';

    protected $description = 'Sincroniza usuarios locales de alumnos desde la base legacy';

    public function handle(LegacyAlumnoUserSyncService $service): int
    {
        $result = $service->sync([
            'documento' => $this->option('documento'),
            'solo_faltantes' => (bool) $this->option('solo-faltantes'),
            'chunk' => (int) $this->option('chunk'),
            'dry_run' => (bool) $this->option('dry-run'),
        ], $this);

        $this->newLine();
        $this->info('Sincronización finalizada.');
        $this->line('Procesados: '.$result['processed']);
        $this->line('Creados: '.$result['created']);
        $this->line('Actualizados: '.$result['updated']);
        $this->line('Omitidos: '.$result['skipped']);
        $this->line('Conflictos: '.$result['conflicts']);
        $this->line('Errores: '.$result['errors']);

        return self::SUCCESS;
    }
}
```

### Servicio

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class LegacyAlumnoUserSyncService
{
    public function sync(array $options, ?Command $console = null): array
    {
        $stats = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'conflicts' => 0,
            'errors' => 0,
        ];

        Role::findOrCreate('ALUMNO', 'web');

        $query = DB::connection('pgsql_externa')
            ->table('sh_maestros.vw_alumnos_00')
            ->select(['alu_id', 'alu_perdoc', 'per_nombre', 'per_apelli'])
            ->whereNotNull('alu_perdoc');

        if ($options['documento']) {
            $query->where('alu_perdoc', $options['documento']);
        }

        $query->orderBy('alu_id')
            ->chunkById($options['chunk'], function ($rows) use (&$stats, $options) {
                foreach ($rows as $row) {
                    $stats['processed']++;

                    $documento = trim((string) $row->alu_perdoc);

                    if ($documento === '') {
                        $stats['skipped']++;
                        continue;
                    }

                    $user = User::query()->firstWhere('documento', $documento);

                    if ($options['solo_faltantes'] && $user) {
                        $stats['skipped']++;
                        continue;
                    }

                    $email = $user?->email ?: sprintf('alumno-%s@consultor.invalid', $documento);
                    $name = trim(($row->per_nombre ?? '').' '.($row->per_apelli ?? '')) ?: 'Alumno';

                    if (! $options['dry_run']) {
                        $user = User::query()->updateOrCreate(
                            ['documento' => $documento],
                            [
                                'name' => $name,
                                'email' => $email,
                                'password' => $user?->password ?: Str::random(40),
                            ],
                        );

                        if (! $user->hasRole('ALUMNO')) {
                            $user->assignRole('ALUMNO');
                        }
                    }

                    if ($user) {
                        $stats['updated']++;
                    } else {
                        $stats['created']++;
                    }
                }
            }, 'alu_id');

        return $stats;
    }
}
```

## Ajustes recomendados antes de implementar

Hay dos detalles del borrador que conviene cerrar antes de escribir el código real:

### 1. Contador `created` vs `updated`

En la implementación real hay que distinguir correctamente si el usuario existía antes de `updateOrCreate`, para no inflar `updated`.

### 2. Conflictos de email

Antes de persistir, habría que validar si el email propuesto ya pertenece a otro `documento`. En ese caso, se debe omitir el registro y sumarlo a `conflicts`.

## Pruebas mínimas esperadas

Antes de dar por bueno el comando, deberían existir tests para:

- crear usuario nuevo desde alumno externo,
- actualizar usuario existente por `documento`,
- omitir conflicto de email,
- omitir alumno sin `documento`,
- respetar `--solo-faltantes`,
- respetar `--documento=`,
- respetar `--dry-run` sin escribir cambios.

## Encaje con el panel ADMIN

La propuesta más sólida es que el botón administrativo no contenga la lógica de negocio.

En lugar de eso:

- el panel ADMIN llama al mismo servicio de sincronización para un solo `documento`,
- el comando masivo llama a ese mismo servicio para muchos registros,
- así se evita mantener dos lógicas distintas para crear usuarios de alumnos.

## Conclusión

La sincronización masiva debe servir para dejar preparada la tabla `users` del sistema nuevo sin perder el vínculo con la autenticación legacy.

La recomendación es:

- sincronizar identidad local por `documento`,
- conservar la consulta académica en tiempo real desde `pgsql_externa`,
- no migrar el `PIN` viejo como password local,
- reutilizar la misma lógica tanto desde consola como desde ADMIN.

Si este planteamiento te cierra, el siguiente paso sería implementarlo como servicio + comando real, y luego enganchar una versión puntual desde el panel de administración.
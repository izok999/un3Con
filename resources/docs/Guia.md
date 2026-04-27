# Guía Completa: Proyecto desde Cero con Stack Laravel + Livewire + MaryUI (Abril 2026)

> **Stack:** Laravel 12 · PHP 8.5 · Livewire 3 · Volt · MaryUI 2 · DaisyUI 5 · TailwindCSS 4 · PostgreSQL 18 · Vite 7 · Spatie Permission 6 · Laravel Sail (Docker)

---

## Índice

1. [Requisitos Previos](#1-requisitos-previos)
2. [Crear el Proyecto Laravel](#2-crear-el-proyecto-laravel)
3. [Configurar Docker con Sail](#3-configurar-docker-con-sail)
4. [Configurar PostgreSQL](#4-configurar-postgresql)
5. [Instalar y Configurar pgAdmin 4](#5-instalar-y-configurar-pgadmin-4)
6. [Conectar una Base de Datos Externa / Preexistente](#6-conectar-una-base-de-datos-externa--preexistente)
7. [Instalar Livewire 3 + Volt](#7-instalar-livewire-3--volt)
8. [Instalar MaryUI + DaisyUI 5 + TailwindCSS 4](#8-instalar-maryui--daisyui-5--tailwindcss-4)
9. [Configurar Vite correctamente](#9-configurar-vite-correctamente)
10. [Configurar TailwindCSS 4 + DaisyUI 5](#10-configurar-tailwindcss-4--daisyui-5)
11. [Instalar Spatie Permission (Roles y Permisos)](#11-instalar-spatie-permission-roles-y-permisos)
12. [Instalar Laravel Breeze (Autenticación)](#12-instalar-laravel-breeze-autenticación)
13. [Instalar DomPDF (Exportación a PDF)](#13-instalar-dompdf-exportación-a-pdf)
14. [Configurar Mailpit (Email en desarrollo)](#14-configurar-mailpit-email-en-desarrollo)
15. [Layout Principal con MaryUI + Sidebar](#15-layout-principal-con-maryui--sidebar)
16. [Crear un Componente Livewire de Ejemplo](#16-crear-un-componente-livewire-de-ejemplo)
17. [Tema Personalizado DaisyUI 5](#17-tema-personalizado-daisyui-5)
18. [Estructura de Archivos Final](#18-estructura-de-archivos-final)
19. [Comandos de Referencia Rápida](#19-comandos-de-referencia-rápida)
20. [Troubleshooting Común](#20-troubleshooting-común)
21. [Diferencias Clave vs. Versiones Anteriores](#21-diferencias-clave-vs-versiones-anteriores)

---

## 1. Requisitos Previos

### En tu máquina host necesitas:

| Herramienta | Versión Mínima | Verificar con |
|-------------|---------------|---------------|
| **Docker Desktop** o **Docker Engine + Compose** | Docker 24+, Compose v2 | `docker --version` / `docker compose version` |
| **Git** | 2.x | `git --version` |
| **cURL** | cualquiera | `curl --version` |

> **Nota:** NO necesitas PHP, Composer ni Node instalados localmente — todo corre dentro de los contenedores de Sail.

### Opcional (para desarrollo más rápido fuera de Docker):

| Herramienta | Versión | Instalar |
|-------------|---------|----------|
| PHP | 8.4+ / 8.5 | `sudo apt install php8.5-cli php8.5-xml php8.5-curl php8.5-pgsql php8.5-mbstring php8.5-zip` |
| Composer | 2.8+ | `composer self-update` |
| Node.js | 22 LTS+ | `nvm install 22` |
| npm | 10+ | viene con Node |

---

## 2. Crear el Proyecto Laravel

### Opción A: Con el instalador global de Laravel (recomendado)

```bash
# Instalar/actualizar el instalador global
composer global require laravel/installer

# Crear el proyecto (última versión de Laravel = 12.x)
laravel new mi-proyecto
```

El instalador interactivo te preguntará:

```
 Which starter kit would you like to install?
 > [0] None
   [1] Laravel Breeze
   [2] Laravel Jetstream

# Elegir [1] Laravel Breeze si querés autenticación lista

 Which Breeze stack would you like to install?
 > [0] Blade with Alpine
   [1] Livewire (Volt) with Alpine
   [2] React with Inertia
   [3] Vue with Inertia
   [4] API only

# Elegir [1] Livewire (Volt) with Alpine — es exactamente nuestro stack

 Which testing framework do you prefer?
 > [0] Pest     ← recomendado, más moderno
   [1] PHPUnit

 Would you like to initialize a git repository? (yes/no) [yes]

 Which database will your application use?
   [0] SQLite
   [1] MySQL
 > [2] PostgreSQL     ← elegir esta
   [3] MariaDB
   [4] SQL Server

 Would you like to run the default database migrations? [yes]
 > yes (si ya tenés la DB corriendo; sino, no)
```

### Opción B: Con Composer directamente

```bash
composer create-project laravel/laravel mi-proyecto
cd mi-proyecto
```

### Opción C: Usando Sail desde cero (sin PHP local)

```bash
# Laravel proporciona un script que bootstrapea todo con Docker
curl -s "https://laravel.build/mi-proyecto?with=pgsql,redis,mailpit" | bash

cd mi-proyecto
```

Esto automáticamente:
- Descarga Laravel 12 (última versión estable)
- Configura Sail con PostgreSQL, Redis y Mailpit
- Genera `.env` correctamente
- Ejecuta `composer install` dentro de Docker

---

## 3. Configurar Docker con Sail

### 3.1 Instalar Sail (si no vino preinstalado)

```bash
cd mi-proyecto

# Si usaste Opción B, Sail no viene por defecto:
composer require laravel/sail --dev

# Publicar el docker-compose (compose.yaml)
php artisan sail:install
# Seleccionar: [0] pgsql, [1] redis, [2] mailpit
```

### 3.2 Archivo `compose.yaml` resultante

Sail genera automáticamente un `compose.yaml`. Modificalo para ajustar puertos si es necesario:

```yaml
# compose.yaml
services:
    laravel.test:
        build:
            context: './vendor/laravel/sail/runtimes/8.5'
            dockerfile: Dockerfile
            args:
                WWWGROUP: '${WWWGROUP}'
        image: 'sail-8.5/app'
        extra_hosts:
            - 'host.docker.internal:host-gateway'
        ports:
            - '${APP_PORT:-80}:80'
            - '${VITE_PORT:-5173}:${VITE_PORT:-5173}'
        dns:
            - 8.8.8.8
            - 8.8.4.4
        environment:
            WWWUSER: '${WWWUSER}'
            LARAVEL_SAIL: 1
            XDEBUG_MODE: '${SAIL_XDEBUG_MODE:-off}'
            XDEBUG_CONFIG: '${SAIL_XDEBUG_CONFIG:-client_host=host.docker.internal}'
            IGNITION_LOCAL_SITES_PATH: '${PWD}'
        volumes:
            - '.:/var/www/html'
        networks:
            - sail
        depends_on:
            - pgsql
            - redis
            - mailpit

    pgsql:
        image: 'postgres:18-alpine'
        ports:
            - '${FORWARD_DB_PORT:-5432}:5432'
        environment:
            PGPASSWORD: '${DB_PASSWORD:-secret}'
            POSTGRES_DB: '${DB_DATABASE}'
            POSTGRES_USER: '${DB_USERNAME}'
            POSTGRES_PASSWORD: '${DB_PASSWORD:-secret}'
        volumes:
            - 'sail-pgsql:/var/lib/postgresql/data'
        networks:
            - sail
        healthcheck:
            test: ["CMD", "pg_isready", "-q", "-d", "${DB_DATABASE}", "-U", "${DB_USERNAME}"]
            retries: 3
            timeout: 5s

    redis:
        image: 'redis:alpine'
        ports:
            - '${FORWARD_REDIS_PORT:-6379}:6379'
        volumes:
            - 'sail-redis:/data'
        networks:
            - sail
        healthcheck:
            test: ["CMD", "redis-cli", "ping"]
            retries: 3
            timeout: 5s

    mailpit:
        image: 'axllent/mailpit:latest'
        ports:
            - '${FORWARD_MAILPIT_PORT:-1025}:1025'
            - '${FORWARD_MAILPIT_DASHBOARD_PORT:-8025}:8025'
        networks:
            - sail

    pgadmin:
        image: 'dpage/pgadmin4:latest'
        ports:
            - '${FORWARD_PGADMIN_PORT:-5050}:80'
        environment:
            PGADMIN_DEFAULT_EMAIL: '${PGADMIN_DEFAULT_EMAIL:-admin@admin.com}'
            PGADMIN_DEFAULT_PASSWORD: '${PGADMIN_DEFAULT_PASSWORD:-admin}'
            PGADMIN_CONFIG_SERVER_MODE: 'False'
            PGADMIN_REPLACE_SERVERS_ON_STARTUP: 'True'
        volumes:
            - 'sail-pgadmin:/var/lib/pgadmin'
            - './docker/pgadmin/servers.json:/pgadmin4/servers.json:ro'
        networks:
            - sail
        depends_on:
            - pgsql

networks:
    sail:
        driver: bridge

volumes:
    sail-pgsql:
        driver: local
    sail-redis:
        driver: local
    sail-pgadmin:
        driver: local
```

### 3.3 Alias útil para Sail

Agregar al `~/.bashrc` o `~/.zshrc`:

```bash
alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'
```

Ahora usás `sail` en vez de `./vendor/bin/sail`.

### 3.4 Levantar los contenedores

```bash
sail up -d
# o sin alias:
./vendor/bin/sail up -d
```

Verificar que todo está corriendo:

```bash
sail ps
# Deberías ver: laravel.test, pgsql, redis, mailpit, pgadmin
```

### 3.5 Entrar al contenedor

```bash
sail shell
# Ahora estás dentro del contenedor con PHP 8.5, Composer, Node, npm
```

---

## 4. Configurar PostgreSQL

### 4.1 Variables de entorno (`.env`)

```env
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

> **Importante:** `DB_HOST=pgsql` es el nombre del servicio en `compose.yaml`, no `localhost`.

### 4.2 Verificar conexión

```bash
sail artisan db:show
# Debe mostrar: PostgreSQL 18.x, base "laravel", etc.

sail artisan migrate
# Debe correr sin errores
```

### 4.3 Conectar con un cliente externo (DBeaver, pgAdmin, etc.)

```
Host: localhost
Puerto: 5432 (o el que pusiste en FORWARD_DB_PORT)
Base: laravel
Usuario: sail
Password: password
```

---

## 5. Instalar y Configurar pgAdmin 4

pgAdmin es la herramienta visual más completa para administrar PostgreSQL. En este proyecto lo levantamos como un contenedor Docker junto con Sail.

### 5.1 Variables de entorno para pgAdmin

Agregá estas variables en tu `.env` (son opcionales — tienen valores por defecto):

```env
# pgAdmin
FORWARD_PGADMIN_PORT=5050
PGADMIN_DEFAULT_EMAIL=admin@admin.com
PGADMIN_DEFAULT_PASSWORD=admin
```

> El servicio pgAdmin ya está incluido en el `compose.yaml` de la sección 3.2.

### 5.2 Acceder a pgAdmin

Una vez levantados los contenedores (`sail up -d`), abrí en tu navegador:

```
http://localhost:5050
```

Credenciales de acceso:
- **Email:** `admin@admin.com` (o el valor de `PGADMIN_DEFAULT_EMAIL`)
- **Password:** `admin` (o el valor de `PGADMIN_DEFAULT_PASSWORD`)

### 5.3 Precargar servidores automáticamente al iniciar pgAdmin (recomendado)

Si querés que pgAdmin ya arranque con los servidores cargados, agregá un archivo `docker/pgadmin/servers.json` y montalo en el contenedor.

Archivo recomendado:

```json
{
    "Servers": {
        "1": {
            "Name": "SEFUNE Local",
            "Group": "Locales",
            "Host": "pgsql",
            "Port": 5432,
            "MaintenanceDB": "laravel",
            "Username": "sail",
            "SSLMode": "prefer"
        },
        "2": {
            "Name": "UNE Base Consulta",
            "Group": "Externas",
            "Host": "10.10.254.252",
            "Port": 5432,
            "MaintenanceDB": "une_base",
            "Username": "usr_alu_web",
            "SSLMode": "prefer"
        }
    }
}
```

Con el `compose.yaml` de esta guía, el montaje queda así:

```yaml
pgadmin:
        image: 'dpage/pgadmin4:latest'
        ports:
                - '${FORWARD_PGADMIN_PORT:-5050}:80'
        environment:
                PGADMIN_DEFAULT_EMAIL: '${PGADMIN_DEFAULT_EMAIL:-admin@admin.com}'
                PGADMIN_DEFAULT_PASSWORD: '${PGADMIN_DEFAULT_PASSWORD:-admin}'
                PGADMIN_CONFIG_SERVER_MODE: 'False'
                PGADMIN_REPLACE_SERVERS_ON_STARTUP: 'True'
        volumes:
                - 'sail-pgadmin:/var/lib/pgadmin'
                - './docker/pgadmin/servers.json:/pgadmin4/servers.json:ro'
```

Luego reiniciá pgAdmin:

```bash
sail up -d
```

Después de iniciar, deberías ver cargados:
- **SEFUNE Local**
- **UNE Base Consulta**

> **Importante:** pgAdmin no importa ni exporta passwords en `servers.json`. El servidor queda registrado automáticamente, pero la primera vez que conectes vas a tener que ingresar la contraseña y marcar **Save password** si querés persistirla.

> **Importante 2:** con `PGADMIN_REPLACE_SERVERS_ON_STARTUP=True`, la lista de servidores queda administrada de forma declarativa. Si agregás servidores manualmente en la UI, pueden ser reemplazados al reiniciar el contenedor.

### 5.4 Registrar manualmente el servidor PostgreSQL local del proyecto (opcional)

Dentro de pgAdmin:

1. Click derecho en **Servers** → **Register** → **Server...**
2. Pestaña **General**:
   - **Name:** `SEFUNE Local` (o el nombre que prefieras)
3. Pestaña **Connection**:

| Campo | Valor | Explicación |
|-------|-------|-------------|
| **Host name/address** | `pgsql` | Nombre del servicio Docker (NO `localhost`) |
| **Port** | `5432` | Puerto interno del contenedor |
| **Maintenance database** | `laravel` | O el valor de `DB_DATABASE` en `.env` |
| **Username** | `sail` | O el valor de `DB_USERNAME` en `.env` |
| **Password** | `password` | O el valor de `DB_PASSWORD` en `.env` |
| **Save password?** | ✅ Sí | Para no tener que ingresarlo cada vez |

> **¿Por qué `pgsql` y no `localhost`?** Porque pgAdmin corre dentro de la red Docker (`sail`), donde los servicios se comunican por nombre de servicio.

4. Click en **Save**. Deberías ver la base de datos listada.

### 5.5 Registrar manualmente una base de datos externa / remota (opcional)

Si necesitás conectar a una base de datos de otro servidor (producción, staging, legado):

1. Click derecho en **Servers** → **Register** → **Server...**
2. Pestaña **General**:
    - **Name:** `UNE Base Consulta` (o nombre descriptivo)
3. Pestaña **Connection**:

| Campo | Valor |
|-------|-------|
| **Host name/address** | `10.10.254.252` |
| **Port** | `5432` |
| **Maintenance database** | `une_base` |
| **Username** | `usr_alu_web` |
| **Password** | La contraseña del usuario de consulta |

4. Pestaña **SSL** (si el servidor lo requiere):
    - **SSL mode:** `Prefer` (o el modo exigido por el servidor)
5. Click en **Save**.

### 5.6 Uso recomendado de pgAdmin

| Tarea | Cómo hacerlo en pgAdmin |
|-------|------------------------|
| **Explorar estructura** | Expandir Servers → BD → Schemas → public → Tables |
| **Ver datos de una tabla** | Click derecho en tabla → **View/Edit Data** → **All Rows** |
| **Ejecutar SQL** | Click derecho en la BD → **Query Tool** → escribir SQL → F5 |
| **Ver relaciones (FK)** | Expandir tabla → Constraints → Foreign Keys |
| **Ver índices** | Expandir tabla → Indexes |
| **Exportar datos** | Click derecho en tabla → **Import/Export Data** |
| **Hacer backup** | Click derecho en BD → **Backup...** (formato `.sql` o custom) |
| **Restaurar backup** | Click derecho en BD → **Restore...** |
| **Comparar esquemas** | Abrir Query Tool en ambas BD y ejecutar `\dt` o queries de `information_schema` |

### 5.7 Tips para trabajar con pgAdmin

- **Guardar queries frecuentes:** En Query Tool, usá **Save** (Ctrl+S) para guardar consultas.
- **Múltiples pestañas:** Podés abrir varias pestañas de Query Tool, una por base de datos.
- **No ejecutar DDL en producción sin backup:** Siempre hacer backup antes de `ALTER TABLE`, `DROP`, etc.
- **Modo solo lectura:** Si solo querés inspeccionar una BD de producción, creá un usuario PostgreSQL con permisos de solo `SELECT`.

---

## 6. Conectar una Base de Datos Externa / Preexistente

Si necesitás trabajar con una base de datos que ya existe en otro servidor (producción, legado, otro proyecto), Laravel permite configurar múltiples conexiones.

### 6.1 Agregar la conexión en `config/database.php`

Agregá una nueva entrada dentro del array `connections`:

```php
// config/database.php → dentro de 'connections' => [...]

'pgsql_externa' => [
    'driver' => 'pgsql',
    'url' => env('DB_EXTERNA_URL'),
    'host' => env('DB_EXTERNA_HOST', '127.0.0.1'),
    'port' => env('DB_EXTERNA_PORT', '5432'),
    'database' => env('DB_EXTERNA_DATABASE', 'mi_base_externa'),
    'username' => env('DB_EXTERNA_USERNAME', 'postgres'),
    'password' => env('DB_EXTERNA_PASSWORD', ''),
    'charset' => env('DB_CHARSET', 'utf8'),
    'prefix' => '',
    'prefix_indexes' => true,
    'search_path' => 'public',
    'sslmode' => env('DB_EXTERNA_SSLMODE', 'prefer'),
],
```

### 6.2 Variables de entorno para la BD externa (`.env`)

```env
# Base de datos externa / consulta
DB_EXTERNA_HOST=10.10.254.252
DB_EXTERNA_PORT=5432
DB_EXTERNA_DATABASE=une_base
DB_EXTERNA_USERNAME=usr_alu_web
DB_EXTERNA_PASSWORD="tu_password_real"
DB_EXTERNA_SSLMODE=prefer
```

> **Nota:** Si el password contiene `#`, espacios u otros caracteres especiales, envolvelo entre comillas dobles en el `.env`.

> **Importante:** Si la BD externa está fuera de Docker, usá la IP real o hostname del servidor, NO `pgsql` (que es solo para la base local de Sail).
>
> Si estás dentro de Docker y la BD externa está en tu máquina host, usá `host.docker.internal` como host.

### 6.3 Arquitectura del Consultor Académico

Este proyecto es un **portal de consulta para estudiantes**. No importa ni replica datos de la BD externa — los consulta en **tiempo real** vía `DB::connection('pgsql_externa')`. La BD externa (`une_base`) es de solo lectura.

#### Modelo de vinculación

```
BD LOCAL (pgsql)                          BD EXTERNA (pgsql_externa / une_base)
┌──────────────┐                          ┌───────────────────────────────┐
│ users        │    documento (cédula)    │ sh_maestros.tb_personas       │
│  - id        │◄────────────────────────►│  - per_id                     │
│  - name      │                          │  - per_docume                 │
│  - email     │                          │  - per_nombre / per_apelli    │
│  - documento │                          └──────────┬────────────────────┘
│  - password  │                                     │ per_id = alu_idper
│  - roles     │                          ┌──────────▼────────────────────┐
└──────────────┘                          │ sh_academico.tb_alumnos       │
                                          │  - alu_id                     │
                                          │  - alu_idper                  │
                                          │  - alu_pin                    │
                                          └──────────┬────────────────────┘
                                                     │ alu_id
                                          ┌──────────▼────────────────────┐
                                          │ vw_alumnos_habilitacion_21    │
                                          │  (carreras, sede, periodo)    │
                                          └──────────┬────────────────────┘
                                                     │ hal_id
                              ┌──────────────────────┼──────────────────────┐
                              ▼                      ▼                      ▼
                   ┌───────────────────┐  ┌───────────────────┐  ┌───────────────────┐
                   │ extracto_         │  │ inscriptos_       │  │ deudas_           │
                   │ academico_01      │  │ materias_14       │  │ saldos_12         │
                   │ (calificaciones)  │  │ (matriculación)   │  │ (cuotas)          │
                   └───────────────────┘  └───────────────────┘  └───────────────────┘
```

El campo **`documento`** (número de cédula) en la tabla local `users` es el puente obligatorio. Al autenticarse, se busca `alu_perdoc` en la vista `vw_alumnos_00` de la BD externa para resolver `alu_id`, y desde ahí se accede a todo: habilitaciones, materias, calificaciones, deudas, asistencia.

#### Esquemas disponibles en `une_base`

| Esquema | Contenido principal |
|---------|--------------------|
| `sh_maestros` | Personas, alumnos, carreras, mallas, materias, instituciones, periodos lectivos |
| `sh_academico` | Alumnos, actas de calificaciones, extracto académico |
| `sh_movimientos` | Habilitaciones, inscripciones, calificaciones, deudas, pagos, asistencia, evaluaciones |
| `sh_rrhh` | Docentes |
| `sh_sistemas` | Usuarios, perfiles, logs del sistema |

#### Vistas principales para el estudiante

| Módulo | Vista externa | Filtro | Datos |
|--------|--------------|--------|-------|
| **Mi perfil** | `sh_maestros.vw_alumnos_00` | `alu_perdoc` | Nombre, apellido, documento, foto |
| **Mis carreras** | `sh_movimientos.vw_alumnos_habilitacion_21` | `alu_id` | Carreras activas, sede, periodo, vigencia |
| **Extracto académico** | `sh_movimientos.vw_extracto_academico_01` | `aci_idalu` | Calificaciones históricas, materia, acta, nota |
| **Materias inscriptas** | `sh_movimientos.vw_alumnos_inscriptos_materias_14` | `alu_id` | Materias del periodo, turno, sección |
| **Malla curricular** | `sh_movimientos.vw_malla_alumnos_00` | `hal_idalu` | Materias de la malla, avance |
| **Evaluaciones/parciales** | `sh_movimientos.vw_evaluaciones_puntajes_item_14` | `epi_idhal` | Puntajes de evaluaciones |
| **Asistencia** | `sh_movimientos.vw_asistencia_alumnos_14` | `aai_idalu` | Clases, presencias, justificadas |
| **Deudas/cuotas** | `sh_movimientos.vw_alumnos_deudas_saldos_12` | `deu_idalu` | Saldo de aranceles, vencimientos |
| **Certificados** | `sh_movimientos.vw_certificado_de_estudios_01` | `ces_idalu` | Certificados emitidos |
| **Avisos** | `sh_movimientos.vw_avisos_00` | `avi_idsed` | Avisos activos de la sede |

### 6.4 Consultar la BD externa con Query Builder (enfoque recomendado)

Para un portal de consulta con vistas externas de solo lectura, **no se crean modelos Eloquent** por cada vista. Se usa Query Builder directamente:

```php
use Illuminate\Support\Facades\DB;

// Buscar alumno por documento (cédula)
$alumno = DB::connection('pgsql_externa')
    ->table('sh_maestros.vw_alumnos_00')
    ->where('alu_perdoc', $documento)
    ->first();

// Carreras activas del alumno
$carreras = DB::connection('pgsql_externa')
    ->table('sh_movimientos.vw_alumnos_habilitacion_21')
    ->where('alu_id', $alumno->alu_id)
    ->where('hal_vigent', true)
    ->get();

// Extracto académico (calificaciones)
$extracto = DB::connection('pgsql_externa')
    ->table('sh_movimientos.vw_extracto_academico_01')
    ->where('aci_idalu', $alumno->alu_id)
    ->orderBy('act_fecha', 'desc')
    ->get();

// Materias inscriptas en el periodo actual
$materias = DB::connection('pgsql_externa')
    ->table('sh_movimientos.vw_alumnos_inscriptos_materias_14')
    ->where('alu_id', $alumno->alu_id)
    ->where('imi_vigent', true)
    ->get();

// Deudas pendientes
$deudas = DB::connection('pgsql_externa')
    ->table('sh_movimientos.vw_alumnos_deudas_saldos_12')
    ->where('deu_idalu', $alumno->alu_id)
    ->get();
```

### 6.5 Verificar la conexión externa

```bash
# Desde artisan tinker
sail artisan tinker

>>> DB::connection('pgsql_externa')->getPdo();
# Si conecta, muestra el objeto PDO. Si falla, muestra el error.

# Probar con una vista real:
>>> DB::connection('pgsql_externa')->table('sh_maestros.vw_alumnos_00')->limit(1)->get();
# Debe devolver un registro con alu_id, per_nombre, per_apelli, alu_perdoc, etc.

# Contar alumnos:
>>> DB::connection('pgsql_externa')->table('sh_academico.tb_alumnos')->count();
# → 62314
```

### 6.6 Consideraciones importantes para BD externas

| Aspecto | Recomendación |
|---------|---------------|
| **Migraciones** | **NO ejecutar** `migrate` en la conexión externa — esa base ya tiene su estructura |
| **Solo lectura** | Si solo vas a leer datos, usá un usuario PostgreSQL con permisos `SELECT` únicamente |
| **Sincronización** | Si necesitás copiar datos de la externa a la local, hacelo con seeders o comandos artisan personalizados |
| **Red Docker** | Si la BD externa está en tu host, usá `host.docker.internal` como host desde Sail |
| **Firewall** | Asegurate de que el puerto 5432 (u otro) esté abierto en el servidor externo |
| **SSL** | Para BD remotas en internet, usá `DB_EXTERNA_SSLMODE=require` como mínimo |
| **Backup antes de escribir** | Siempre hacer backup de la BD externa antes de cualquier operación de escritura |

### 6.7 Servicio de consulta académica (`AlumnoExternoService`)

Para centralizar todas las consultas a la BD externa, creá un servicio dedicado:

```php
// app/Services/AlumnoExternoService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AlumnoExternoService
{
    protected function query()
    {
        return DB::connection('pgsql_externa');
    }

    /**
     * Resolver alu_id a partir del documento (cédula).
     * Se cachea 30 minutos porque no cambia frecuentemente.
     */
    public function resolverAlumno(string $documento): ?object
    {
        return Cache::remember("alumno_doc_{$documento}", 1800, function () use ($documento) {
            $result = $this->query()
                ->table('sh_maestros.vw_alumnos_00')
                ->where('alu_perdoc', $documento)
                ->first();

            return $result ?: null;
        });
    }

    /**
     * Carreras activas (habilitaciones vigentes) del alumno.
     */
    public function carreras(int $aluId): \Illuminate\Support\Collection
    {
        return Cache::remember("alumno_{$aluId}_carreras", 1800, function () use ($aluId) {
            return $this->query()
                ->table('sh_movimientos.vw_alumnos_habilitacion_21')
                ->where('alu_id', $aluId)
                ->get();
        });
    }

    /**
     * Extracto académico completo (calificaciones históricas).
     */
    public function extractoAcademico(int $aluId): \Illuminate\Support\Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_extracto_academico_01')
            ->where('aci_idalu', $aluId)
            ->orderBy('act_fecha', 'desc')
            ->get();
    }

    /**
     * Materias inscriptas vigentes.
     */
    public function materiasInscriptas(int $aluId): \Illuminate\Support\Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_alumnos_inscriptos_materias_14')
            ->where('alu_id', $aluId)
            ->where('imi_vigent', true)
            ->get();
    }

    /**
     * Deudas y saldos pendientes.
     */
    public function deudas(int $aluId): \Illuminate\Support\Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_alumnos_deudas_saldos_12')
            ->where('deu_idalu', $aluId)
            ->get();
    }

    /**
     * Asistencia por materia.
     */
    public function asistencia(int $aluId): \Illuminate\Support\Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_asistencia_alumnos_14')
            ->where('aai_idalu', $aluId)
            ->get();
    }

    /**
     * Evaluaciones y puntajes.
     */
    public function evaluaciones(int $halId): \Illuminate\Support\Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_evaluaciones_puntajes_item_14')
            ->where('epi_idhal', $halId)
            ->get();
    }

    /**
     * Malla curricular del alumno.
     */
    public function mallaCurricular(int $aluId): \Illuminate\Support\Collection
    {
        return $this->query()
            ->table('sh_movimientos.vw_malla_alumnos_00')
            ->where('hal_idalu', $aluId)
            ->get();
    }
}
```

Uso desde un componente Livewire:

```php
use App\Services\AlumnoExternoService;

public function mount(AlumnoExternoService $service)
{
    $alumno = $service->resolverAlumno(auth()->user()->documento);
    $this->carreras = $service->carreras($alumno->alu_id);
}
```

> **Nota:** Este servicio NO importa datos — consulta en tiempo real. Los datos costosos (perfil, carreras) se cachean en Redis con TTL de 30 minutos.

> **Ajuste importante sobre el legacy:** en esta base externa, las vistas `sh_maestros.vw_alumnos_00`, `sh_movimientos.vw_alumnos_habilitacion_21`, `sh_movimientos.vw_extracto_academico_01`, `sh_movimientos.vw_alumnos_inscriptos_materias_14` y `sh_movimientos.vw_alumnos_deudas_saldos_12` responden correctamente y son las que usa hoy el portal de alumno. Algunas funciones heredadas documentadas en el consultor viejo, como `sh_academico.fn_busca_alumnos_habilitacion_extracto(...)` y `sh_movimientos.fn_consultor_alumnos_deudas(...)`, hoy fallan en esta base por dependencias rotas a vistas legacy no disponibles, así que no deben tomarse como fuente primaria para estas pantallas.

### 6.8 Inspeccionar la BD externa con pgAdmin

El flujo recomendado para trabajar con una BD preexistente:

1. **Verificar que el servidor ya esté precargado** en pgAdmin (sección 5.3) o registrarlo manualmente (sección 5.5)
2. **Explorar la estructura:** tablas, columnas, tipos de datos, PK/FK, índices
3. **Documentar**: anotar los nombres de tablas/campos relevantes que vas a usar
4. **Probar queries** en Query Tool antes de implementarlas en Laravel
5. **Crear modelos** en Laravel con `$connection = 'pgsql_externa'`
6. **Validar** que las consultas desde Laravel devuelven lo esperado con `tinker`

---

## 7. Instalar Livewire 3 + Volt

### 7.1 Instalar paquetes

Si usaste Breeze con Livewire (Opción A paso 2), ya viene incluido. Sino:

```bash
sail composer require livewire/livewire livewire/volt
```

### 7.2 Verificar versiones instaladas

```bash
sail composer show livewire/livewire | grep versions
# Debería mostrar: v3.x.x

sail composer show livewire/volt | grep versions
# Debería mostrar: v1.7+
```

### 7.3 Publicar configuración (opcional)

```bash
sail artisan livewire:publish --config
# Crea config/livewire.php
```

### 7.4 Verificar que el layout incluye los assets de Livewire

En tu layout principal (`resources/views/layouts/app.blade.php` o equivalente), asegurate de tener:

```html
<head>
    ...
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- Livewire inyecta automáticamente sus scripts en Laravel 12 -->
</head>
<body>
    {{ $slot }}
    <!-- NO necesitas @livewireScripts en Livewire 3 — se inyecta automáticamente -->
</body>
```

### 7.5 Crear un componente Livewire de prueba

```bash
# Componente clásico (clase + vista)
sail artisan make:livewire MiComponente

# Componente Volt (single-file, todo en la vista)
sail artisan make:volt mi-pagina
```

---

## 8. Instalar MaryUI + DaisyUI 5 + TailwindCSS 4

### 8.1 Instalar MaryUI

```bash
sail composer require robsontenorio/mary
```

### 8.2 Instalar dependencias frontend

> **⚠️ IMPORTANTE — TailwindCSS 4 vs 3:**
>
> MaryUI v2 soporta TailwindCSS 4. DaisyUI 5 **requiere** TailwindCSS 4.
> TailwindCSS 4 cambió completamente su sistema de configuración (ya no usa `tailwind.config.js`, usa CSS nativo).

```bash
# Dentro del contenedor Sail o con sail npm:
sail npm install -D tailwindcss@4 @tailwindcss/vite@4 daisyui@5
```

### 8.3 Verificar versiones npm

```bash
sail npm ls tailwindcss daisyui @tailwindcss/vite
# tailwindcss@4.x.x
# daisyui@5.x.x
# @tailwindcss/vite@4.x.x
```

### 8.4 Desinstalar paquetes obsoletos (TW3)

Si tenés restos de TailwindCSS 3, eliminá:

```bash
sail npm uninstall @tailwindcss/forms autoprefixer postcss
```

> **¿Por qué?**
> - `@tailwindcss/forms` → En TW4, se importa como plugin CSS: `@plugin "@tailwindcss/forms";`
> - `autoprefixer` → TW4 lo incluye internamente
> - `postcss` → TW4 con `@tailwindcss/vite` no necesita postcss separado

### 8.5 Eliminar archivos de configuración obsoletos

```bash
# Ya no se necesitan con TailwindCSS 4:
rm -f tailwind.config.js postcss.config.js
```

> En TailwindCSS 4, **toda la configuración va dentro del CSS**. Ya no existe `tailwind.config.js`.

---

## 9. Configurar Vite correctamente

### 9.1 `vite.config.js` — Configuración limpia para TailwindCSS 4

```javascript
// vite.config.js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),   // ← Plugin de TailwindCSS 4 para Vite
    ],

    // Configuración para Sail (Docker) — necesario para HMR
    server: {
        host: '0.0.0.0',
        port: 5173,
        cors: true,
        hmr: {
            host: 'localhost',
            port: 5173,
        },
        allowedHosts: 'all',
    },
});
```
Opción	Propósito
host: '0.0.0.0'	Escucha en todas las interfaces del contenedor
port: 5173	Puerto estándar de Vite
cors: true	Permite requests cross-origin desde el navegador
hmr.host: 'localhost'	El navegador conecta HMR a localhost, no al contenedor
allowedHosts: 'all'	Evita el error 403 de Vite en Docker
watch.ignored	Evita recargas por vistas compiladas de Blade

> **Notas:**
> - Ya no necesitás `postcss.config.js` — el plugin `@tailwindcss/vite` maneja todo.
> - El bloque `server` es necesario solo si usás Docker/Sail.
> - `allowedHosts: 'all'` evita el error "403 Forbidden" de Vite en Docker.

### 9.2 `package.json` limpio

```json
{
    "private": true,
    "type": "module",
    "scripts": {
        "build": "vite build",
        "dev": "vite"
    },
    "devDependencies": {
        "@tailwindcss/vite": "^4.0",
        "axios": "^1.7",
        "daisyui": "^5.0",
        "laravel-vite-plugin": "^2.0",
        "tailwindcss": "^4.0",
        "vite": "^7.0"
    }
}
```

---

## 10. Configurar TailwindCSS 4 + DaisyUI 5

### 10.1 `resources/css/app.css` — El archivo central de configuración

En TailwindCSS 4, **el CSS es tu archivo de configuración**. Así queda:

```css
/* resources/css/app.css */

/* ============================================
   1. IMPORTAR TAILWINDCSS 4
   ============================================ */
@import "tailwindcss";

/* ============================================
   2. PLUGINS
   ============================================ */
@plugin "daisyui" {
    themes: uneTheme --default;
}

/* ============================================
   3. SOURCES (para que TW escanee las clases)
   ============================================ */
/* Componentes MaryUI */
@source "../../vendor/robsontenorio/mary/src/View/Components/**/*.php";

/* Vistas de Laravel (pagination, etc.) */
@source "../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php";

/* ============================================
   4. TEMA PERSONALIZADO DaisyUI 5
   ============================================ */
@theme {
    /* Fuente personalizada */
    --font-sans: 'Figtree', ui-sans-serif, system-ui, sans-serif;
}

/* Tema institucional */
@layer base {
    [data-theme="uneTheme"] {
        color-scheme: light;
        --color-primary: #6A9149;
        --color-primary-content: #ffffff;
        --color-secondary: #CC9933;
        --color-secondary-content: #ffffff;
        --color-accent: #F6CD1B;
        --color-accent-content: #000000;
        --color-neutral: #2a2a2a;
        --color-neutral-content: #ffffff;
        --color-base-100: #ffffff;
        --color-base-200: #f3f4f6;
        --color-base-300: #e5e7eb;
        --color-base-content: #1f2937;
        --color-info: #6A9149;
        --color-info-content: #ffffff;
        --color-success: #6A9149;
        --color-success-content: #ffffff;
        --color-warning: #CC9933;
        --color-warning-content: #ffffff;
        --color-error: #af3030;
        --color-error-content: #ffffff;
    }
}

/* ============================================
   5. ESTILOS GLOBALES ADICIONALES
   ============================================ */
/* Agregá tus estilos personalizados aquí abajo */
```

### 10.2 Diferencias clave TailwindCSS 3 → 4

| Concepto | TailwindCSS 3 | TailwindCSS 4 |
|----------|---------------|---------------|
| Configuración | `tailwind.config.js` | Dentro de `app.css` con directivas CSS |
| Directivas base | `@tailwind base/components/utilities;` | `@import "tailwindcss";` |
| Plugins JS | `plugins: [forms, daisyui]` en config JS | `@plugin "daisyui";` en CSS |
| Content/Purge | `content: [...]` en config JS | `@source "..."` en CSS (auto-detecta en la mayoría de casos) |
| Tema | `theme.extend` en config JS | `@theme { ... }` en CSS |
| PostCSS | Requiere `postcss.config.js` | No necesario con `@tailwindcss/vite` |
| Prefix Autoprefixer | Paquete separado | Incluido automáticamente |
| Colores custom | `colors: { ... }` en JS | CSS custom properties directamente |

### 10.3 ¿Qué archivos ya NO necesitás?

```
❌ tailwind.config.js      → eliminado
❌ postcss.config.js        → eliminado  
❌ @tailwindcss/forms       → se usa @plugin en CSS si hiciera falta
❌ autoprefixer (paquete)   → incluido en TW4
```

---

## 11. Instalar Spatie Permission (Roles y Permisos)

### 11.1 Instalar

```bash
sail composer require spatie/laravel-permission
```

### 11.2 Publicar migraciones y configuración

```bash
sail artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
# Esto crea:
#   config/permission.php
#   database/migrations/xxxx_create_permission_tables.php

sail artisan migrate
```

### 11.3 Configurar el modelo User

> **Contexto proyecto:** En este consultor académico, el campo `documento` (cédula) es el puente entre la tabla local `users` y la BD externa `une_base`. Es obligatorio para vincular al estudiante con sus datos académicos.

```php
// app/Models/User.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'password',
    'documento',
    'email_verified_at',
    'auth_provider',
    'auth_provider_id',
    'avatar',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

> **Nota:** Laravel 12 usa atributos PHP (`#[Fillable]`, `#[Hidden]`) en vez de arrays `$fillable` / `$hidden`. Ambos formatos funcionan.

### 11.4 Registrar middleware de roles en `bootstrap/app.php`

```php
// bootstrap/app.php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

### 11.5 Crear Seeder de roles

```bash
sail artisan make:seeder RoleSeeder
```

```php
// database/seeders/RoleSeeder.php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Crear roles del consultor académico
        $adminRole = Role::firstOrCreate(['name' => 'ADMIN']);
        Role::firstOrCreate(['name' => 'ALUMNO']);

        // Crear usuario admin por defecto
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrador',
                'password' => bcrypt('password'),
                'documento' => '0000000',
            ]
        );
        $admin->assignRole($adminRole);
    }
}
```

> **Roles del proyecto:**
> - **ADMIN** — gestiona usuarios, configura el sistema
> - **ALUMNO** — consulta sus datos académicos desde la BD externa

### 11.6 Usar roles en rutas

```php
// routes/web.php
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:ADMIN'])->group(function () {
    Route::get('/admin', fn() => view('admin.dashboard'));
});

Route::middleware(['auth', 'role:ALUMNO'])->group(function () {
    // Rutas del consultor académico
    Route::get('/mis-carreras', \App\Livewire\Alumno\MisCarreras::class);
    Route::get('/extracto-academico', \App\Livewire\Alumno\ExtractoAcademico::class);
    Route::get('/mis-materias', \App\Livewire\Alumno\MisMaterias::class);
    Route::get('/mis-deudas', \App\Livewire\Alumno\MisDeudas::class);
});
```

### 11.7 Usar roles en vistas Blade

```blade
@role('ALUMNO')
    <x-mary-menu-item title="Mis Carreras" link="/mis-carreras" icon="o-academic-cap" />
    <x-mary-menu-item title="Extracto Académico" link="/extracto-academico" icon="o-document-text" />
    <x-mary-menu-item title="Materias Inscriptas" link="/mis-materias" icon="o-book-open" />
    <x-mary-menu-item title="Deudas" link="/mis-deudas" icon="o-banknotes" />
@endrole

@role('ADMIN')
    <x-mary-menu-separator />
    <x-mary-menu-item title="Administración" link="/admin" icon="o-cog" />
@endrole
```

---

## 12. Instalar Laravel Breeze (Autenticación)

> Si ya elegiste Breeze en el paso 2 (Opción A), saltá esta sección.

### 12.1 Instalar

```bash
sail composer require laravel/breeze --dev
sail artisan breeze:install livewire
```

Esto genera:
- Rutas de auth (`routes/auth.php`)
- Vistas de login, registro, etc.
- Middleware de autenticación configurado

### 12.2 Compilar assets y migrar

```bash
sail npm install
sail npm run build
sail artisan migrate
```

### 12.3 Si vas a usar OAuth, este es el momento correcto para definirlo

Sí. Si querés login con Google, GitHub, Microsoft o similar, conviene decidirlo ahora, idealmente antes de estabilizar migraciones o salir a producción.

¿Por qué en este punto?
- OAuth impacta la tabla `users`
- Necesitás credenciales del proveedor en `config/services.php` y `.env`
- Tenés que definir si tu auth será solo local, solo OAuth o mixta
- Si además usás Spatie Permission, conviene definir el rol inicial al primer login

La estrategia más práctica para este proyecto es:
- Mantener Breeze para sesión, middleware, reset de password y pantallas base
- Sumar OAuth con Socialite como método adicional de ingreso

### 12.4 Instalar Laravel Socialite

```bash
sail composer require laravel/socialite
```

> **Nota:** Para Google y GitHub, `laravel/socialite` suele ser suficiente. Para Microsoft Entra ID, Keycloak u otros proveedores menos comunes, puede hacer falta un driver adicional del ecosistema SocialiteProviders.

### 12.5 Ajustar la tabla `users` para OAuth y consultor académico

Además de OAuth, este proyecto necesita el campo `documento` (cédula) para vincular al usuario con la BD externa `une_base`.

Si todavía no corriste `sail artisan migrate`, modificá la migración base de usuarios. Si ya corriste migraciones, creá una migración adicional.

#### Caso A: todavía no migraste

```php
// database/migrations/0001_01_01_000000_create_users_table.php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('documento')->unique();          // Cédula — puente con une_base
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password')->nullable();          // Nullable para cuentas OAuth
    $table->string('auth_provider')->nullable();
    $table->string('auth_provider_id')->nullable();
    $table->string('avatar')->nullable();
    $table->rememberToken();
    $table->timestamps();

    $table->unique(['auth_provider', 'auth_provider_id']);
});
```

#### Caso B: ya migraste

```bash
sail artisan make:migration add_documento_and_oauth_to_users_table --table=users
```

```php
// database/migrations/xxxx_xx_xx_xxxxxx_add_documento_and_oauth_to_users_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('documento')->unique()->after('email');
            $table->string('password')->nullable()->change();
            $table->string('auth_provider')->nullable();
            $table->string('auth_provider_id')->nullable();
            $table->string('avatar')->nullable();
            $table->unique(['auth_provider', 'auth_provider_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_auth_provider_auth_provider_id_unique');
            $table->dropColumn(['documento', 'auth_provider', 'auth_provider_id', 'avatar']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
```

> **Importante:** Si una cuenta puede nacer desde OAuth, `password` debe poder ser `NULL`.
>
> Si además necesitás consumir la API del proveedor, agregá también columnas como `provider_token` y `provider_refresh_token`, idealmente encriptadas. Si solo querés login social, no hace falta guardarlas.

### 12.6 Ajustar el modelo `User`

El modelo `User` ya fue configurado en la sección 11.3 con todos los campos necesarios: `documento`, `auth_provider`, `auth_provider_id`, `avatar`. Si ya lo configuraste ahí, no necesitás cambiarlo de nuevo.

> El cast `'password' => 'hashed'` puede quedarse sin problema, aunque algunas cuentas OAuth tengan `password = null`.

### 12.7 Configurar `config/services.php` y `.env`

Ejemplo con Google:

```php
// config/services.php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URI'),
],
```

```env
APP_URL=http://localhost

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost/auth/google/callback
```

> **Importante:** La URL del callback debe coincidir exactamente con la registrada en la consola del proveedor. Si tu app corre en otro puerto, incluí ese puerto en la URL.

### 12.8 Crear rutas y controlador para OAuth

Podés declarar las rutas en `routes/web.php` o en `routes/auth.php` si querés dejar todo lo relacionado con autenticación agrupado.

```php
use App\Http\Controllers\Auth\OAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/auth/google/redirect', [OAuthController::class, 'redirectToGoogle'])
        ->name('auth.google.redirect');

    Route::get('/auth/google/callback', [OAuthController::class, 'handleGoogleCallback'])
        ->name('auth.google.callback');
});
```

```php
// app/Http/Controllers/Auth/OAuthController.php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        $socialUser = Socialite::driver('google')->user();
        $email = $socialUser->getEmail();

        abort_unless($email, 422, 'El proveedor OAuth no devolvio un email valido.');

        $user = User::query()
            ->where('email', $email)
            ->orWhere(function ($query) use ($socialUser) {
                $query->where('auth_provider', 'google')
                    ->where('auth_provider_id', $socialUser->getId());
            })
            ->first();

        if (! $user) {
            $user = User::create([
                'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: 'Usuario',
                'email' => $email,
                'password' => null,
                'auth_provider' => 'google',
                'auth_provider_id' => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
                'email_verified_at' => now(),
            ]);

            // Si ya instalaste Spatie Permission, podés asignar un rol base aca.
            // $user->assignRole('FUNCIONARIO');
        } else {
            $user->update([
                'auth_provider' => 'google',
                'auth_provider_id' => $socialUser->getId(),
                'avatar' => $socialUser->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?: now(),
            ]);
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/');
    }
}
```

### 12.9 Agregar el botón de login social

Por ejemplo, en la vista de login de Breeze podés sumar un botón como este:

```blade
<x-mary-button
    label="Ingresar con Google"
    link="{{ route('auth.google.redirect') }}"
    class="btn-outline w-full"
/>
```

### 12.10 Consideraciones importantes para OAuth

- Definí si una cuenta local existente con el mismo email se fusiona con la cuenta OAuth o si se bloquea por seguridad.
- Si querés limitar acceso a correos institucionales, validá el dominio antes de autenticar al usuario en la app.
- Si usás Spatie Permission, ejecutá primero el seeder de roles para poder asignar un rol base en el primer login social.
- Si ya estás en producción, no edites migraciones viejas: agregá una migración nueva.
- Si un proveedor no devuelve email verificado, no crees la cuenta automáticamente sin una validación extra.

---

## 13. Instalar DomPDF (Exportación a PDF)

### 13.1 Instalar

```bash
sail composer require barryvdh/laravel-dompdf
```

### 13.2 Publicar configuración (opcional)

```bash
sail artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
# Crea config/dompdf.php
```

### 13.3 Uso básico

```php
// En un controlador o ruta:
use Barryvdh\DomPDF\Facade\Pdf;

Route::get('/reporte/{id}/pdf', function ($id) {
    $datos = MiModelo::findOrFail($id);
    $pdf = Pdf::loadView('pdf.reporte', compact('datos'));
    return $pdf->stream('reporte.pdf'); // Ver en navegador
    // return $pdf->download('reporte.pdf'); // Descargar
});
```

### 13.4 Vista PDF de ejemplo

```blade
{{-- resources/views/pdf/reporte.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        /* DomPDF soporta CSS inline y básico — NO usa Tailwind */
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 6px; text-align: left; }
        th { background: #6A9149; color: white; }
    </style>
</head>
<body>
    <h1>Reporte</h1>
    <p>Generado: {{ now()->format('d/m/Y H:i') }}</p>
    <table>
        <tr><th>Campo</th><td>{{ $datos->campo }}</td></tr>
    </table>
</body>
</html>
```

---

## 14. Configurar Mailpit (Email en desarrollo)

Mailpit ya viene en `compose.yaml`. Solo configurá `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@miapp.test"
MAIL_FROM_NAME="${APP_NAME}"
```

Accedé al dashboard de Mailpit en: **http://localhost:8025**

Todos los emails enviados por la app se capturan ahí (no se envían realmente).

---

## 15. Layout Principal con MaryUI + Sidebar

### 15.1 Instalar íconos (requerido por MaryUI)

MaryUI usa íconos de Heroicons. Instalar blade-ui-kit:

```bash
sail composer require blade-ui-kit/blade-heroicons
```

### 15.2 Crear layout con sidebar

```blade
{{-- resources/views/components/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="es" data-theme="uneTheme">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-200">

    {{-- SIDEBAR --}}
    <x-mary-main full-width>
        <x-slot:sidebar drawer="main-drawer" class="bg-base-100 border-r border-base-300">
            <div class="p-4 text-center">
                <img src="/images/logo.png" alt="Logo" class="mx-auto w-20 h-20">
                <h2 class="mt-2 font-bold text-primary">Mi Aplicación</h2>
            </div>

            <x-mary-menu activate-by-route>
                <x-mary-menu-item title="Inicio" icon="o-home" link="/" />

                @role('ALUMNO')
                    <x-mary-menu-sub title="Académico" icon="o-academic-cap">
                        <x-mary-menu-item title="Mis Carreras" link="/mis-carreras" />
                        <x-mary-menu-item title="Extracto Académico" link="/extracto-academico" />
                        <x-mary-menu-item title="Materias Inscriptas" link="/mis-materias" />
                        <x-mary-menu-item title="Malla Curricular" link="/malla-curricular" />
                    </x-mary-menu-sub>
                    <x-mary-menu-sub title="Finanzas" icon="o-banknotes">
                        <x-mary-menu-item title="Deudas" link="/mis-deudas" />
                    </x-mary-menu-sub>
                @endrole

                @role('ADMIN')
                    <x-mary-menu-separator />
                    <x-mary-menu-item title="Admin" icon="o-cog" link="/admin" />
                @endrole
            </x-mary-menu>
        </x-slot:sidebar>

        {{-- CONTENIDO PRINCIPAL --}}
        <x-slot:content>
            <div class="navbar bg-base-100 shadow-sm mb-4 rounded-box">
                <label for="main-drawer" class="lg:hidden btn btn-ghost">
                    <x-mary-icon name="o-bars-3" />
                </label>
                <div class="flex-1">
                    <span class="text-lg font-semibold">{{ $title ?? 'Inicio' }}</span>
                </div>
                <div class="flex-none">
                    @auth
                        <x-mary-dropdown>
                            <x-slot:trigger>
                                <x-mary-button icon="o-user" class="btn-ghost btn-sm" />
                            </x-slot:trigger>
                            <x-mary-menu-item title="{{ auth()->user()->name }}" />
                            <x-mary-menu-separator />
                            <x-mary-menu-item title="Cerrar sesión" icon="o-arrow-left-on-rectangle"
                                link="/logout" no-wire-navigate />
                        </x-mary-dropdown>
                    @endauth
                </div>
            </div>

            {{ $slot }}
        </x-slot:content>
    </x-mary-main>

    {{-- Toast notifications de MaryUI --}}
    <x-mary-toast />
</body>
</html>
```

> **Importante:** El archivo debe estar en `resources/views/components/layouts/app.blade.php` para que Livewire lo encuentre automáticamente como layout.

---

## 16. Crear un Componente Livewire de Ejemplo

### 16.1 CRUD de ejemplo con MaryUI

```bash
sail artisan make:livewire ListaItems
```

```php
// app/Livewire/ListaItems.php
<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;

class ListaItems extends Component
{
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public string $nombre = '';

    public function render()
    {
        // Ejemplo — reemplazar con tu modelo real
        $items = collect([
            (object)['id' => 1, 'nombre' => 'Item 1'],
            (object)['id' => 2, 'nombre' => 'Item 2'],
        ]);

        return view('livewire.lista-items', [
            'items' => $items,
        ]);
    }

    public function crear()
    {
        $this->validate(['nombre' => 'required|min:3']);
        // Lógica de creación...
        $this->showModal = false;
        $this->reset('nombre');
        $this->success('Item creado correctamente.');  // Toast de MaryUI
    }
}
```

```blade
{{-- resources/views/livewire/lista-items.blade.php --}}
<div>
    <x-mary-header title="Items" subtitle="Gestión de items" separator>
        <x-slot:actions>
            <x-mary-button label="Nuevo" icon="o-plus" class="btn-primary"
                @click="$wire.showModal = true" />
        </x-slot:actions>
    </x-mary-header>

    {{-- Búsqueda --}}
    <x-mary-input icon="o-magnifying-glass" placeholder="Buscar..."
        wire:model.live.debounce.300ms="search" clearable />

    {{-- Tabla --}}
    <x-mary-card class="mt-4" shadow>
        <x-mary-table :headers="[
            ['key' => 'id', 'label' => '#'],
            ['key' => 'nombre', 'label' => 'Nombre'],
        ]" :rows="$items">
            @scope('actions', $item)
                <x-mary-button icon="o-pencil" class="btn-ghost btn-sm" />
                <x-mary-button icon="o-trash" class="btn-ghost btn-sm text-error" />
            @endscope
        </x-mary-table>
    </x-mary-card>

    {{-- Modal de creación --}}
    <x-mary-modal wire:model="showModal" title="Nuevo Item">
        <x-mary-input label="Nombre" wire:model="nombre" />
        <x-slot:actions>
            <x-mary-button label="Cancelar" @click="$wire.showModal = false" />
            <x-mary-button label="Guardar" class="btn-primary" wire:click="crear" />
        </x-slot:actions>
    </x-mary-modal>
</div>
```

### 16.2 Registrar ruta

```php
// routes/web.php
Route::middleware('auth')->group(function () {
    Route::get('/items', \App\Livewire\ListaItems::class)->name('items');
});
```

---

## 17. Tema Personalizado DaisyUI 5

### La forma correcta en DaisyUI 5 + TailwindCSS 4

En DaisyUI 5, los temas se definen usando **CSS custom properties**. Hay dos abordajes:

#### Opción A: Inline en `app.css` (recomendado)

Ya lo mostramos en la sección 8.1 — todo va dentro del archivo CSS.

#### Opción B: Usar temas predefinidos de DaisyUI

```css
/* resources/css/app.css */
@import "tailwindcss";

@plugin "daisyui" {
    themes: light, dark, corporate;  /* Temas built-in de DaisyUI 5 */
}
```

#### Opción C: Múltiples temas personalizados

```css
@import "tailwindcss";

@plugin "daisyui" {
    themes: uneTheme --default, uneThemeDark;
}

@layer base {
    [data-theme="uneTheme"] {
        color-scheme: light;
        --color-primary: #6A9149;
        /* ... resto de colores */
    }

    [data-theme="uneThemeDark"] {
        color-scheme: dark;
        --color-primary: #8BBF66;
        --color-base-100: #1d232a;
        --color-base-200: #191e24;
        --color-base-300: #15191e;
        --color-base-content: #d6dbe3;
        /* ... */
    }
}
```

### Selector de tema (toggle day/night)

```blade
<x-mary-toggle
    label="Modo oscuro"
    x-data
    @change="document.documentElement.dataset.theme =
        document.documentElement.dataset.theme === 'uneTheme' ? 'uneThemeDark' : 'uneTheme'"
/>
```

---

## 18. Estructura de Archivos Final

Después de seguir toda esta guía, tu proyecto debería verse así:

```
mi-proyecto/
├── app/
│   ├── Http/Controllers/
│   ├── Livewire/
│   │   └── ListaItems.php          # Componente de ejemplo
│   ├── Mail/
│   ├── Models/
│   │   └── User.php                # Con HasRoles de Spatie
│   ├── Providers/
│   │   └── AppServiceProvider.php
│   └── View/Components/
├── bootstrap/
│   └── app.php                     # Middleware de Spatie registrado
├── compose.yaml                    # Docker: pgsql, redis, mailpit
├── config/
│   ├── permission.php              # Spatie Permission config
│   └── ...
├── database/
│   ├── migrations/
│   │   ├── xxxx_create_users_table.php
│   │   ├── xxxx_create_permission_tables.php
│   │   └── ...
│   └── seeders/
│       ├── DatabaseSeeder.php
│       └── RoleSeeder.php
├── public/
├── resources/
│   ├── css/
│   │   └── app.css                 # TW4 + DaisyUI 5 config aquí
│   ├── js/
│   │   └── app.js
│   └── views/
│       ├── components/
│       │   └── layouts/
│       │       └── app.blade.php   # Layout con sidebar MaryUI
│       ├── livewire/
│       │   └── lista-items.blade.php
│       └── pdf/
│           └── reporte.blade.php
├── routes/
│   ├── web.php
│   ├── auth.php                    # (de Breeze)
│   └── console.php
├── .env
├── composer.json
├── package.json
├── vite.config.js                  # Con @tailwindcss/vite
└── README.md
```

**Archivos que ya NO existen:**
```
❌ tailwind.config.js     → Configuración ahora en app.css
❌ postcss.config.js      → No necesario con @tailwindcss/vite
```

---

## 19. Comandos de Referencia Rápida

### Desarrollo diario

```bash
# Levantar todo
sail up -d

# Compilar frontend (dev con hot-reload)
sail npm run dev

# Compilar frontend (producción)
sail npm run build

# Correr migraciones
sail artisan migrate

# Resetear DB completa con seeders
sail artisan migrate:fresh --seed

# Entrar al contenedor
sail shell

# Ejecutar tinker (REPL de PHP)
sail artisan tinker

# Ver logs en tiempo real
sail artisan pail
```

### Crear cosas

```bash
# Modelo + migración + seeder + factory
sail artisan make:model MiModelo -mfs

# Componente Livewire (clase + vista)
sail artisan make:livewire Admin/MiComponente

# Componente Volt (single-file)
sail artisan make:volt mi-pagina

# Controlador invocable (para PDF, etc.)
sail artisan make:controller MiPdfController --invokable

# Mail
sail artisan make:mail MiNotificacion --markdown=mail.mi-notificacion

# Seeder
sail artisan make:seeder MiSeeder
```

### Testing

```bash
# Ejecutar todos los tests
sail artisan test

# Test específico
sail artisan test --filter=MiTest

# Con Pest (si lo elegiste)
sail pest

# Con cobertura
sail artisan test --coverage
```

### Limpiar caches

```bash
sail artisan optimize:clear
# Equivale a:
# sail artisan config:clear
# sail artisan route:clear
# sail artisan view:clear
# sail artisan cache:clear
```

---

## 20. Troubleshooting Común

### Error: "Vite manifest not found"

```bash
# Compilá los assets:
sail npm run build
# O para desarrollo:
sail npm run dev
```

### Error: Puerto 5432 ya en uso

```bash
# Cambiar el puerto PostgreSQL forward en .env:
FORWARD_DB_PORT=5433
# Y reiniciar:
sail down && sail up -d
```

### Error: "403 Forbidden" al cargar Vite desde Docker

Asegurate de tener en `vite.config.js`:
```javascript
server: {
    host: '0.0.0.0',
    allowedHosts: 'all',
}
```

### MaryUI no muestra estilos / componentes rotos

Verificá que en `app.css` tengas el `@source` de MaryUI:

```css
@source "../../vendor/robsontenorio/mary/src/View/Components/**/*.php";
```

### DaisyUI 5 no aplica colores del tema

1. Verificá que el HTML tenga `data-theme="uneTheme"` en el elemento `<html>`.
2. Verificá que el tema use `--color-*` (con prefijo `--color-`, no `--`).
3. Asegurate de que el tema esté declarado en `@plugin "daisyui" { themes: uneTheme --default; }`.

### Spatie Permission: "There is no role named X"

```bash
# Correr el seeder de roles:
sail artisan db:seed --class=RoleSeeder
```

### Livewire componente no se encuentra (404)

1. Verificá que la clase exista en `app/Livewire/`.
2. Ejecutá `sail artisan livewire:discover`.
3. Limpiá cache: `sail artisan optimize:clear`.

### Error: "Class 'Livewire\Volt\Volt' not found"

```bash
sail composer require livewire/volt
sail artisan volt:install
```

### pgAdmin no carga o muestra error al iniciar

```bash
# Verificar que el contenedor está corriendo:
sail ps | grep pgadmin

# Si no aparece, reiniciar:
sail down && sail up -d

# Verificar logs del contenedor:
docker compose logs pgadmin
```

Si pgAdmin se cuelga en el primer inicio, puede ser por permisos del volumen. Solucionarlo:

```bash
# Eliminar el volumen y recrear:
sail down -v  # ⚠️ Esto elimina TODOS los volúmenes (incluida la DB local)
sail up -d

# O solo el volumen de pgAdmin:
docker volume rm sistema-evaluacion_sail-pgadmin
sail up -d
```

### pgAdmin no puede conectar al servidor PostgreSQL

1. Verificá que el **Host** sea `pgsql` (nombre del servicio Docker), no `localhost`.
2. Verificá que el contenedor `pgsql` esté corriendo: `sail ps`.
3. Verificá las credenciales: deben coincidir con las de `.env` (`DB_USERNAME`, `DB_PASSWORD`).

### Error de conexión a BD externa: "could not connect to server"

1. Verificá que el host/IP sea accesible desde el contenedor Docker:
   ```bash
   sail shell
   ping 192.168.1.100  # Probar conectividad
   ```
2. Si la BD está en tu máquina host, usá `host.docker.internal` como host.
3. Verificá que el puerto 5432 esté abierto en el firewall del servidor externo.
4. Verificá que `pg_hba.conf` del servidor externo permita conexiones desde tu IP.

### Error: "FATAL: no pg_hba.conf entry for host"

El servidor PostgreSQL externo no permite conexiones desde tu IP. Hay que agregar una entrada en `pg_hba.conf` del servidor:

```
# Permitir conexión desde la IP del desarrollador
host    nombre_base    usuario    tu.ip.x.x/32    md5
```

Y reiniciar PostgreSQL en el servidor externo.

### OAuth: "redirect_uri_mismatch" o callback rechazada

1. Verificá que la URL configurada en Google, GitHub o el proveedor coincida exactamente con `GOOGLE_REDIRECT_URI` o su equivalente.
2. Asegurate de que `APP_URL` coincida con el dominio y puerto reales desde donde abrís la app.
3. En Sail local, la URL suele ser `http://localhost/auth/google/callback` o `http://localhost:PUERTO/auth/google/callback` si cambiaste `APP_PORT`.
4. Si aparece un error de `Invalid state`, revisá que no estés entrando por `localhost` y volviendo por IP, o viceversa, y limpiá cookies/sesión antes de reintentar.

---

## 21. Diferencias Clave vs. Versiones Anteriores

### TailwindCSS 3 → 4

| Cambio | Impacto |
|--------|---------|
| Ya no existe `tailwind.config.js` | Toda la config va en CSS |
| `@tailwind base;` → `@import "tailwindcss";` | Nueva directiva CSS |
| Plugins JS → `@plugin` en CSS | Cambio de sintaxis |
| `content: [...]` → `@source "..."` | Auto-detección + directiva source |
| Se eliminan `postcss.config.js`, `autoprefixer` | Integrado en TW4 |
| Colores arbitrarios cambian | `bg-[#xxx]` sigue funcionando |
| Nuevos utilities CSS nativos | `@starting-style`, container queries, etc. |

### DaisyUI 4 → 5

| Cambio | Impacto |
|--------|---------|
| Requiere TailwindCSS 4 | No es compatible con TW3 |
| Temas vía CSS custom properties | `--color-primary` en vez de config JS |
| Declaración en CSS: `@plugin "daisyui"` | Ya no va en `tailwind.config.js` |
| Nuevos componentes | Timeline, Diff, StatusIndicator, etc. |
| Mejor accesibilidad | ARIA roles mejorados |

### Laravel 11 → 12

| Cambio | Impacto |
|--------|---------|
| PHP 8.4+ requerido (8.5 soportado) | Verificar versión de PHP |
| Nuevo `bootstrap/app.php` simplificado | Configuración más limpia |
| `config/app.php` sin providers array | Auto-discovery mejorado |
| Nuevos defaults para sesiones/cache | DB por defecto en vez de file |

### MaryUI 1 → 2

| Cambio | Impacto |
|--------|---------|
| Soporte TailwindCSS 4 | Nuevo sistema de escaneo con `@source` |
| Nuevos componentes | Consultar [docs MaryUI](https://mary-ui.com) |
| Breaking changes en props | Revisar changelog al migrar |

---

## Checklist Final de Verificación

Antes de empezar a desarrollar, verificá que todo funcione:

- [ ] `sail up -d` levanta sin errores
- [ ] `sail artisan migrate` corre correctamente
- [ ] `sail npm run dev` compila sin errores
- [ ] Abrir `http://localhost` (o el puerto configurado) muestra la app
- [ ] Los estilos de DaisyUI se aplican (colores del tema visible)
- [ ] MaryUI componentes renderizan correctamente
- [ ] Login/Registro funciona (si instalaste Breeze)
- [ ] Login OAuth funciona y vuelve correctamente al callback configurado (si aplica)
- [ ] Mailpit captura emails en `http://localhost:8025`
- [ ] pgAdmin accesible en `http://localhost:5050`
- [ ] pgAdmin conecta al PostgreSQL local (`pgsql`)
- [ ] Conexión a BD externa funciona (si aplica): `sail artisan tinker` → `DB::connection('pgsql_externa')->getPdo()`
- [ ] `sail artisan test` pasa todos los tests

---

## Resumen de Versiones del Stack (Abril 2026)

| Componente | Versión | Instalación |
|------------|---------|-------------|
| **PHP** | 8.5 | Viene con Sail |
| **Laravel** | 12.x | `laravel new` o `composer create-project` |
| **Livewire** | 3.x | `composer require livewire/livewire` |
| **Volt** | 1.7+ | `composer require livewire/volt` |
| **MaryUI** | 2.x | `composer require robsontenorio/mary` |
| **TailwindCSS** | 4.x | `npm install -D tailwindcss@4` |
| **DaisyUI** | 5.x | `npm install -D daisyui@5` |
| **Vite** | 7.x | `npm install -D vite@7` |
| **@tailwindcss/vite** | 4.x | `npm install -D @tailwindcss/vite@4` |
| **PostgreSQL** | 18 | Imagen Docker `postgres:18-alpine` |
| **pgAdmin 4** | latest | Imagen Docker `dpage/pgadmin4:latest` |
| **Spatie Permission** | 6.x | `composer require spatie/laravel-permission` |
| **DomPDF** | 3.x | `composer require barryvdh/laravel-dompdf` |
| **Laravel Sail** | 1.x | `composer require laravel/sail --dev` |
| **Laravel Breeze** | 2.x | `composer require laravel/breeze --dev` |

---

*Guía generada el 7 de abril de 2026 para el stack del Sistema de Evaluación — Universidad Nacional del Este*

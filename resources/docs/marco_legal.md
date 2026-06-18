Te recomiendo hacerlo así: **página Blade estática + PDFs públicos + lista de normativas en `config/normativas.php` + enlace en el sidebar MaryUI**. No necesitas base de datos para esta primera versión.

Laravel permite crear vistas Blade dentro de `resources/views` y devolverlas desde rutas con `view()`. Además, MaryUI permite usar `x-collapse` como componente desplegable y agrupar varios dentro de `x-accordion`; para el menú lateral, `x-menu` permite activar automáticamente el ítem actual con `activate-by-route`. ([Laravel][1])

---

## 1. Crear la carpeta para los PDFs

Como los PDFs serán públicos y estáticos, guardalos dentro de `public/`.

```bash
mkdir -p public/docs/normativas
```

Ejemplo de estructura:

```txt
public/
└── docs/
    └── normativas/
        ├── estatuto-une.pdf
        ├── reglamento-academico.pdf
        ├── reglamento-becas.pdf
        └── resolucion-aranceles.pdf
```

Usá nombres simples, sin espacios ni acentos:

```txt
estatuto-une.pdf
reglamento-academico.pdf
resolucion-consejo-superior-2024.pdf
```

Después vas a acceder a esos archivos con:

```php
asset('docs/normativas/estatuto-une.pdf')
```

El helper `asset()` genera una URL pública para archivos estáticos según el esquema actual de la petición, HTTP o HTTPS. ([Laravel][2])

---

## 2. Crear un archivo de configuración para las normativas

Esto evita que tu Blade quede lleno de HTML repetido. Creá:

```bash
touch config/normativas.php
```

Contenido sugerido:

```php
<?php

return [
    [
        'titulo' => 'Normativa institucional de la UNE',
        'descripcion' => 'Documentos generales que regulan el funcionamiento institucional de la Universidad Nacional del Este.',
        'items' => [
            [
                'titulo' => 'Estatuto de la Universidad Nacional del Este',
                'archivo' => 'docs/normativas/estatuto-une.pdf',
                'descripcion' => 'Documento base de organización institucional de la UNE.',
            ],
            [
                'titulo' => 'Reglamento Académico',
                'archivo' => 'docs/normativas/reglamento-academico.pdf',
                'descripcion' => 'Normas académicas aplicables a estudiantes y carreras.',
            ],
        ],
    ],
    [
        'titulo' => 'Resoluciones y reglamentos estudiantiles',
        'descripcion' => 'Documentos relacionados con trámites, derechos, deberes y procedimientos estudiantiles.',
        'items' => [
            [
                'titulo' => 'Reglamento de Becas',
                'archivo' => 'docs/normativas/reglamento-becas.pdf',
                'descripcion' => 'Condiciones y procedimientos para acceder a becas.',
            ],
            [
                'titulo' => 'Resolución de Aranceles',
                'archivo' => 'docs/normativas/resolucion-aranceles.pdf',
                'descripcion' => 'Documento relacionado con aranceles, pagos o exoneraciones.',
            ],
        ],
    ],
];
```

Cada vez que agregues un nuevo PDF, solo tenés que:

1. Copiar el archivo a `public/docs/normativas/`.
2. Agregar un nuevo item en `config/normativas.php`.

---

## 3. Crear la ruta de la página

En `routes/web.php`, agregá:

```php
use Illuminate\Support\Facades\Route;

Route::get('/normativas', function () {
    return view('pages.normativas.index', [
        'secciones' => config('normativas'),
    ]);
})
    ->middleware(['auth'])
    ->name('normativas.index');
```

Si tu portal todavía no tiene autenticación o querés que sea pública, quitá esta línea:

```php
->middleware(['auth'])
```

Laravel permite devolver vistas Blade desde rutas y pasarles datos como array. ([Laravel][1])

---

## 4. Crear la vista Blade

Creá la carpeta y el archivo:

```bash
mkdir -p resources/views/pages/normativas
touch resources/views/pages/normativas/index.blade.php
```

Contenido base:

```blade
<x-app-layout>
    <x-slot:title>
        Normativas
    </x-slot:title>

    <div class="space-y-6">
        <x-header
            title="Normativas"
            subtitle="Documentos legales, académicos e institucionales de la Universidad Nacional del Este."
            separator
        />

        <x-card>
            <div class="mb-4">
                <h2 class="text-lg font-semibold">
                    Normativa legal de la UNE
                </h2>

                <p class="text-sm text-base-content/70">
                    En esta sección se disponibilizan documentos oficiales en formato PDF para consulta de los estudiantes.
                </p>
            </div>

            <div class="space-y-3">
                @foreach ($secciones as $index => $seccion)
                    <x-collapse separator collapse-plus-minus :open="$index === 0">
                        <x-slot:heading>
                            <div>
                                <div class="font-semibold">
                                    {{ $seccion['titulo'] }}
                                </div>

                                @isset($seccion['descripcion'])
                                    <div class="text-xs text-base-content/60">
                                        {{ $seccion['descripcion'] }}
                                    </div>
                                @endisset
                            </div>
                        </x-slot:heading>

                        <x-slot:content>
                            <div class="space-y-3">
                                @foreach ($seccion['items'] as $item)
                                    <div class="flex flex-col gap-3 rounded-lg border border-base-300 p-4 md:flex-row md:items-center md:justify-between">
                                        <div>
                                            <h3 class="font-medium">
                                                {{ $item['titulo'] }}
                                            </h3>

                                            @isset($item['descripcion'])
                                                <p class="text-sm text-base-content/70">
                                                    {{ $item['descripcion'] }}
                                                </p>
                                            @endisset
                                        </div>

                                        <div class="flex gap-2">
                                            <x-button
                                                label="Ver PDF"
                                                icon="o-eye"
                                                link="{{ asset($item['archivo']) }}"
                                                target="_blank"
                                                class="btn-primary btn-sm"
                                            />

                                            <x-button
                                                label="Descargar"
                                                icon="o-arrow-down-tray"
                                                link="{{ asset($item['archivo']) }}"
                                                download
                                                class="btn-outline btn-sm"
                                            />
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </x-slot:content>
                    </x-collapse>
                @endforeach
            </div>
        </x-card>
    </div>
</x-app-layout>
```

MaryUI documenta `x-collapse` para mostrar/ocultar contenido, con `heading` y `content`; también permite usar variantes como `separator`, `collapse-plus-minus` y `open`. ([Mary UI][3])

---

## 5. Variante con `x-accordion`

Si tu vista está dentro de un componente Livewire o Volt y querés comportamiento de acordeón real —por ejemplo, que solo una sección quede abierta a la vez—, podés usar `x-accordion`.

MaryUI muestra que varios `x-collapse` pueden agruparse dentro de `x-accordion`, usando un `wire:model` y una propiedad pública para controlar el grupo activo. ([Mary UI][3])

Ejemplo conceptual dentro de un componente Livewire:

```blade
<x-accordion wire:model="grupoActivo">
    @foreach ($secciones as $index => $seccion)
        <x-collapse name="seccion-{{ $index }}">
            <x-slot:heading>
                {{ $seccion['titulo'] }}
            </x-slot:heading>

            <x-slot:content>
                @foreach ($seccion['items'] as $item)
                    <a
                        href="{{ asset($item['archivo']) }}"
                        target="_blank"
                        class="link link-primary block"
                    >
                        {{ $item['titulo'] }}
                    </a>
                @endforeach
            </x-slot:content>
        </x-collapse>
    @endforeach
</x-accordion>
```

Y en el componente Livewire:

```php
public string $grupoActivo = 'seccion-0';
```

Para una página estática simple, la versión con varios `x-collapse` es suficiente y menos problemática.

---

## 6. Agregar la sección al sidebar

Buscá el archivo donde tengas tu layout principal. Según cómo esté armado tu proyecto, puede estar en alguno de estos lugares:

```txt
resources/views/layouts/app.blade.php
resources/views/components/layouts/app.blade.php
resources/views/components/app-layout.blade.php
resources/views/livewire/layout/navigation.blade.php
```

Dentro del menú MaryUI, agregá una sección como esta:

```blade
<x-menu activate-by-route>
    {{-- Otros ítems del sistema --}}

    <x-menu-sub title="Normativas" icon="o-scale">
        <x-menu-item
            title="Normativa legal UNE"
            icon="o-document-text"
            link="{{ route('normativas.index') }}"
            route="normativas.index"
        />
    </x-menu-sub>
</x-menu>
```

MaryUI permite `x-menu-sub` para agrupar ítems y `activate-by-route` para activar automáticamente el ítem cuando coincide con la ruta actual. También permite usar rutas nombradas con el parámetro `route`. ([Mary UI][4])

Si no querés submenú porque solo habrá una página, usá directamente:

```blade
<x-menu-item
    title="Normativas"
    icon="o-scale"
    link="{{ route('normativas.index') }}"
    route="normativas.index"
/>
```

---

## 7. Revisar el layout con sidebar

Si tu layout usa MaryUI, probablemente tengas algo parecido a esto:

```blade
<x-main>
    <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100">
        {{-- Menú lateral --}}
    </x-slot:sidebar>

    <x-slot:content>
        {{ $slot }}
    </x-slot:content>
</x-main>
```

MaryUI maneja el sidebar como un slot dentro de `x-main`, con atributos como `drawer`, `collapsible` y clases Tailwind. ([Mary UI][5])

La sección de normativas debe ir dentro del menú que está dentro de ese `x-slot:sidebar`.

---

## 8. Verificar Vite

Para esta funcionalidad no necesitás tocar Vite, salvo que quieras agregar estilos o JS personalizados. Laravel usa Vite para empaquetar CSS y JavaScript de la aplicación, pero los PDFs estáticos en `public/docs/normativas` se sirven como archivos públicos normales. ([Laravel][6])

En desarrollo, corré normalmente:

```bash
npm run dev
php artisan serve
```

En producción:

```bash
npm run build
```

---

## 9. Limpiar caché si no aparecen cambios

Si modificaste `config/normativas.php` y no ves los cambios:

```bash
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

Si estás en producción y usás caché de configuración:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 10. Checklist final

Probá esto:

```txt
/normativas
```

Y verificá:

1. El ítem aparece en el sidebar.
2. El ítem se marca activo al entrar a la página.
3. La página carga sin error.
4. El acordeón abre correctamente.
5. Cada botón “Ver PDF” abre el archivo en otra pestaña.
6. Cada botón “Descargar” descarga el PDF.
7. Los nombres de archivo coinciden exactamente con lo escrito en `config/normativas.php`.

La estructura final debería quedar así:

```txt
config/
└── normativas.php

public/
└── docs/
    └── normativas/
        ├── estatuto-une.pdf
        ├── reglamento-academico.pdf
        └── reglamento-becas.pdf

resources/
└── views/
    └── pages/
        └── normativas/
            └── index.blade.php

routes/
└── web.php
```

Mi recomendación concreta: **empezá con `x-collapse` simple, sin Livewire específico para el acordeón**. Es más estable para una página estática. Más adelante, si querés buscador, filtros por categoría o carga dinámica, ahí sí conviene pasar esta sección a Livewire.

[1]: https://laravel.com/docs/13.x/views "Views | Laravel 13.x - The clean stack for Artisans and agents"
[2]: https://laravel.com/docs/13.x/helpers "Helpers | Laravel 13.x - The clean stack for Artisans and agents"
[3]: https://mary-ui.com/docs/components/collapse "Collapse and Accordion"
[4]: https://mary-ui.com/docs/components/menu "Menu"
[5]: https://mary-ui.com/docs/sidebar "Sidebar"
[6]: https://laravel.com/docs/12.x/vite "Asset Bundling (Vite) | Laravel 12.x - The clean stack for Artisans and agents"

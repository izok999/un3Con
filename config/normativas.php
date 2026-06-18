<?php

/*
|--------------------------------------------------------------------------
| Normativas / Marco Legal de la UNE
|--------------------------------------------------------------------------
|
| Listado de documentos legales e institucionales disponibles para los
| estudiantes. Para agregar un nuevo PDF:
|
|   1. Copiar el archivo PDF a:  public/docs/normativas/
|      (usar nombres en minúsculas, sin espacios ni acentos)
|
|   2. Agregar una entrada nueva en el array 'items' de la sección
|      correspondiente, completando el campo 'archivo' con la ruta
|      relativa al directorio public/ (ej: 'docs/normativas/mi-archivo.pdf').
|
| Los items marcados como TODO son placeholders: reemplazar 'archivo'
| por el nombre real del PDF una vez que esté subido.
|
*/

return [
    [
        'titulo' => 'Normativa institucional de la UNE',
        'descripcion' => 'Documentos generales que regulan el funcionamiento institucional de la Universidad Nacional del Este.',
        'items' => [
            [
                'titulo' => 'Estatuto de la Universidad Nacional del Este',
                // TODO: reemplazar por el nombre real del PDF en public/docs/normativas/
                'archivo' => 'docs/normativas/PLACEHOLDER-estatuto-une.pdf',
                'descripcion' => 'Documento base de organización institucional de la UNE.',
            ],
            [
                'titulo' => 'Reglamento Académico',
                // TODO: reemplazar por el nombre real del PDF en public/docs/normativas/
                'archivo' => 'docs/normativas/PLACEHOLDER-reglamento-academico.pdf',
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
                // TODO: reemplazar por el nombre real del PDF en public/docs/normativas/
                'archivo' => 'docs/normativas/PLACEHOLDER-reglamento-becas.pdf',
                'descripcion' => 'Condiciones y procedimientos para acceder a becas.',
            ],
            [
                'titulo' => 'Resolución de Aranceles',
                // TODO: reemplazar por el nombre real del PDF en public/docs/normativas/
                'archivo' => 'docs/normativas/PLACEHOLDER-resolucion-aranceles.pdf',
                'descripcion' => 'Documento relacionado con aranceles, pagos o exoneraciones.',
            ],
        ],
    ],
];

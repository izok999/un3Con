<?php

/*
|--------------------------------------------------------------------------
| Enlaces institucionales de la UNE
|--------------------------------------------------------------------------
|
| URLs de sitios institucionales externos que se muestran en el sidebar.
|
| La opción "Revista Científica" queda oculta en el menú mientras
| 'revista_cientifica' esté vacío: cargar aquí la URL cuando esté
| disponible y el enlace aparece automáticamente debajo de Repositorio.
|
*/

return [
    'links' => [
        'repositorio' => 'https://repositorio.une.edu.py/',
        // TODO: cargar la URL de la revista científica cuando esté disponible
        'revista_cientifica' => '',
    ],
];

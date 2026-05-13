# Finanzas Fase 1: Lectura Legacy y Próximos Pasos

Fecha: 2026-05-11

## Estado

Sí: podemos considerar cumplida la fase 1 del plan.

Resultado alcanzado:

- El alumno ya ve un estado financiero separado y consistente con legacy.
- No se agregaron tablas nuevas ni se cambió el esquema existente.
- La UI financiera quedó desacoplada del futuro checkout online.
- La fuente de verdad sigue siendo legacy para deudas y pagos visibles.

## Qué se implementó

### 1. Historial de pagos read-only desde legacy

Se agregó `pagosAlumno(int $aluId)` en `App\Services\AlumnoExternoService`.

Implementación actual:

- Llama a `sh_movimientos.fn_consultor_alumnos_pagos(alu_id, 'A', null, null)`.
- Normaliza la respuesta a `Collection<stdClass>` como el resto de los adapters legacy.
- Mantiene el contrato read-only: no escribe nada en legacy ni en la base local.

Archivo principal:

- `app/Services/AlumnoExternoService.php`

### 2. Vista financiera por carrera

Se actualizó la tarjeta financiera de detalle de carrera para mostrar un timeline cronológico combinado:

- deudas filtradas por carrera/habilitación
- pagos legacy del alumno

Comportamiento actual:

- Las deudas sí se filtran por `rsc_id` y `periodo_id`.
- Los pagos vienen del historial general del alumno porque la función legacy de pagos no expone identificadores de carrera o periodo suficientes para un filtro exacto por habilitación.
- Esta limitación se deja explícita en la UI.

Archivos principales:

- `resources/views/livewire/alumno/detalle-carrera/deudas.blade.php`
- `resources/views/livewire/alumno/detalle-carrera.blade.php`

### 3. Vista global financiera en la pestaña existente del sidebar

La ruta existente `alumno.deudas` se reutilizó como vista financiera global.

Ahora muestra:

- stats globales
- tab `Timeline`
- tab `Deudas`
- tab `Pagos legacy`

Esto mantiene el acceso actual desde sidebar y mobile nav, pero la pantalla dejó de ser solo “deudas” y pasó a ser el módulo financiero global del alumno.

Archivo principal:

- `resources/views/livewire/alumno/mis-deudas.blade.php`

## Decisiones técnicas importantes

### Legacy sigue siendo la verdad

La fase 1 no introduce una segunda fuente de verdad para pagos.

Se decidió:

- seguir leyendo deudas desde legacy
- seguir leyendo pagos desde legacy
- no crear persistencia local todavía
- no marcar nada como pagado fuera de legacy

Esto evita que el portal nuevo muestre un estado financiero distinto al sistema legacy.

### No usar estado público con `Collection<stdClass>` para pantallas interactivas

Durante la implementación apareció un problema de rehidratación de Livewire:

- si una pantalla interactiva guarda `Collection` de objetos legacy (`stdClass`) en propiedades públicas
- al hacer re-render con tabs o `set()`
- el contenido puede vaciarse o no rehidratar correctamente

Por eso la vista global `mis-deudas` terminó usando propiedades computadas en lugar de persistir esas colecciones como estado público.

Esto es importante para cualquier fase futura que vuelva a trabajar con resultados legacy sin mapear a arrays o DTOs serializables.

### La vista de pagos legacy no permite todavía un filtro exacto por carrera

La función `fn_consultor_alumnos_pagos(...)` devuelve un `SETOF vw_detalle_pagos_12`.

Columnas relevantes inspeccionadas:

- `uac_descri`
- `ciu_descri`
- `cob_numero`
- `cob_fecha`
- `cob_monto`
- `cob_arancel`
- `cob_perceptor`
- `mat_descri`
- `cob_idrol`
- `cob_tprol`

No devuelve `rsc_id`, `ple_id`, `hal_id` ni equivalentes que permitan una asignación robusta a una carrera/habilitación concreta.

Consecuencia actual:

- en detalle de carrera se muestran deudas de esa carrera
- pero los pagos se muestran como historial general del alumno
- esto está documentado visualmente en la tarjeta financiera

## Archivos impactados en fase 1

- `app/Services/AlumnoExternoService.php`
- `resources/views/livewire/alumno/detalle-carrera/deudas.blade.php`
- `resources/views/livewire/alumno/mis-deudas.blade.php`
- `tests/Feature/Alumno/AlumnoViewsTest.php`

## Validación realizada

Se validó con tests focalizados del módulo de alumno:

```bash
vendor/bin/sail artisan test --compact tests/Feature/Alumno/AlumnoViewsTest.php --filter='mis_deudas|detalle_carrera|pagos_alumno|alumno_routes_render_with_mocked_external_service|global_financial_view'
```

Resultado al cerrar esta fase:

- 5 tests
- 75 assertions
- todo en verde

También se ejecutó formato:

```bash
vendor/bin/sail bin pint --dirty --format agent
```

## Por qué esta fase sí cumple el objetivo

El objetivo de la fase 1 era:

- historial de pagos read-only
- timeline financiero real
- refactor para separar consulta financiera de checkout
- vista por carrera
- vista global en la pestaña financiera existente

Eso quedó cumplido.

Lo que no hace esta fase, de forma deliberada:

- cobrar online
- registrar pagos nuevos
- notificar a legacy
- conciliar callbacks

## Fase 2 propuesta: scaffold técnico de pagos sin persistencia propia

Objetivo:

- dejar preparada la integración técnica con Bancard
- pero todavía apagada o limitada a pruebas controladas
- sin crear tablas nuevas
- sin activar cobro real hasta confirmar write-path en legacy

### Componentes a implementar

#### `PaymentGateway`

Interfaz de alto nivel para cualquier pasarela.

Responsabilidades sugeridas:

- crear checkout
- consultar estado remoto si hace falta
- normalizar respuestas del provider

#### `BancardClient`

Implementación concreta del provider Bancard.

Responsabilidades sugeridas:

- encapsular HTTP client
- timeout, retry y manejo de errores
- mapear request/response del provider a estructuras internas

#### `LegacyPaymentRegistrarInterface`

Contrato que abstrae la comunicación de escritura hacia legacy.

Importante:

- en fase 2 puede quedar implementado como stub o fake desactivado
- no debe asumir todavía tablas nuevas ni escritura directa insegura

#### `BancardWebhookController`

Controlador para recibir callbacks del provider.

En fase 2 debería:

- validar payload básico
- loguear técnicamente el evento
- quedar detrás de feature flag o modo controlado
- no marcar deudas como pagadas por sí mismo

#### `PaymentReturnController`

Controlador para retorno del navegador después del checkout.

En fase 2 debería:

- mostrar resultado técnico de la operación
- no alterar el estado financiero visible salvo que legacy ya lo refleje

#### Feature flag

Agregar:

- `payments.bancard.enabled=false`

Objetivo:

- desplegar el scaffold sin activar cobro real
- habilitar pruebas controladas por ambiente

## Fase 3 propuesta: activación real solo si legacy tiene write-path

La fase 3 solo debe arrancar si legacy ya expone un mecanismo real para registrar el cobro.

Opciones válidas, en orden de preferencia:

1. API oficial legacy
2. función o procedure oficial en la BD legacy
3. tabla de integración ya acordada con el equipo legacy

Si existe write-path:

- el webhook o el flujo de confirmación llama ese contrato
- legacy registra el pago real
- la UI sigue leyendo legacy
- no hace falta crear tablas locales para arrancar

Si no existe write-path:

- se frena la activación real
- no se habilita cobro productivo
- no se promete reflejo en legacy

## Qué sí se puede hacer ya sin tocar BD

- historial de pagos read-only
- timeline financiero real
- refactor para separar consulta financiera de checkout
- cliente Bancard
- webhook/controller/config
- interfaz de integración con legacy
- logs técnicos
- feature flags

## Qué no conviene hacer sin BD propia ni contrato legacy

- órdenes de pago reales con trazabilidad fuerte
- conciliación durable
- reintentos serios de procesamiento
- soporte operativo formal
- auditoría completa de pagos
- reanudar sesiones de checkout con seguridad

## Recomendación para el siguiente paso

La ruta más segura sin tocar tablas sigue siendo:

1. consolidar esta fase 1 como base financiera read-only
2. implementar el scaffold técnico de Bancard detrás de feature flag
3. definir el contrato `LegacyPaymentRegistrarInterface`
4. recién activar cobro real si el equipo legacy confirma un write-path soportado

Esto evita el peor escenario:

- checkout funcionando en el portal nuevo
- pero sin reflejo real en legacy
- y con estados financieros inconsistentes entre ambos sistemas

## Nota para futuro yo

Si retomás este tema después:

- no mezcles todavía checkout con `AlumnoExternoService`
- mantené `AlumnoExternoService` como adapter read-only a legacy
- tratá pagos online como una capa separada
- antes de activar Bancard, pedí confirmación explícita del write-path legacy
- si la UI vuelve a necesitar estado interactivo con objetos legacy, preferí computadas o arrays serializables antes que `Collection<stdClass>` pública
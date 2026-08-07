// =============================================================================
// load.js — generador de carga para el espejo de produccion.
//
//   docker compose -f compose.prod-mirror.yaml --env-file .env.prod-mirror \
//     --profile loadtest run --rm k6 run /scripts/load.js
//
// ###########################################################################
// # LEER ANTES DE SUBIR LA INTENSIDAD                                       #
// #                                                                          #
// # El espejo apunta a la base legacy PRODUCTIVA (10.10.254.252). Esa        #
// # conexion NO pasa por PgBouncer.                                          #
// #                                                                          #
// # Lo que NO es un riesgo: agotar conexiones. Laravel mantiene un PDO por   #
// # worker de Octane, asi que la cantidad de conexiones contra la legacy es  #
// # igual a la cantidad de workers (4-8), sin importar la concurrencia. Y    #
// # usr_alu_web es de solo lectura.                                          #
// #                                                                          #
// # Lo que SI es un riesgo: saturarle CPU/IO por volumen de queries. Por eso #
// # este script se controla por RPS (constant-arrival-rate) y no por VUs:    #
// # LOAD_RPS es literalmente cuantas queries por segundo le mandas a la      #
// # legacy. Arranca en `smoke` y coordina la ventana antes de `sustained`.   #
// ###########################################################################
// =============================================================================

import http from 'k6/http';
import { check, fail, sleep } from 'k6';
import { Trend, Rate, Counter } from 'k6/metrics';

const TARGET   = __ENV.TARGET   || 'http://127.0.0.1:80';
const VHOST    = __ENV.VHOST    || 'www.une.edu.py';
const STAGE    = __ENV.LOAD_STAGE || 'smoke';
const RPS      = parseInt(__ENV.LOAD_RPS || '5', 10);
const DURATION = __ENV.LOAD_DURATION || '2m';
const MAX_VUS  = parseInt(__ENV.LOAD_MAX_VUS || '20', 10);

const USER_EMAIL = __ENV.LOAD_USER_EMAIL || '';
const USER_PASS  = __ENV.LOAD_USER_PASSWORD || '';

// bootstrap/app.php restringe los hosts via trustHosts; sin este header
// Laravel rechaza el request con 403 antes de tocar nada.
const HDR = { Host: VHOST };

const legacyLatency = new Trend('legacy_page_duration', true);
const rateLimited   = new Rate('rate_limited_429_503');
const authFailures  = new Counter('auth_failures');

// -----------------------------------------------------------------------------
// Escenarios
// -----------------------------------------------------------------------------
const SCENARIOS = {
  // Valida que el espejo responde lo mismo que produccion. Un solo usuario.
  // Es el unico que deberias correr sin avisarle a nadie.
  smoke: {
    executor: 'shared-iterations',
    vus: 1,
    iterations: 10,
    maxDuration: '2m',
  },

  // Sube el RPS por escalones para encontrar el codo. Frena solo si se pasa de
  // los thresholds.
  ramp: {
    executor: 'ramping-arrival-rate',
    startRate: 1,
    timeUnit: '1s',
    preAllocatedVUs: Math.min(MAX_VUS, 10),
    maxVUs: MAX_VUS,
    stages: [
      { target: Math.max(1, Math.round(RPS * 0.25)), duration: '30s' },
      { target: Math.max(1, Math.round(RPS * 0.5)),  duration: '30s' },
      { target: Math.max(1, Math.round(RPS * 0.75)), duration: '30s' },
      { target: RPS,                                  duration: '60s' },
    ],
  },

  // RPS fijo por LOAD_DURATION. Este es el que necesita ventana coordinada.
  sustained: {
    executor: 'constant-arrival-rate',
    rate: RPS,
    timeUnit: '1s',
    duration: DURATION,
    preAllocatedVUs: Math.min(MAX_VUS, 10),
    maxVUs: MAX_VUS,
  },
};

if (!SCENARIOS[STAGE]) {
  fail(`LOAD_STAGE invalido: "${STAGE}". Usa smoke | ramp | sustained.`);
}

export const options = {
  scenarios: { [STAGE]: SCENARIOS[STAGE] },
  thresholds: {
    // Corta la corrida si la legacy empieza a sufrir, en vez de seguir
    // golpeandola durante toda la duracion.
    'legacy_page_duration': [{ threshold: 'p(95)<3000', abortOnFail: true, delayAbortEval: '20s' }],
    'http_req_failed':      [{ threshold: 'rate<0.10',  abortOnFail: true, delayAbortEval: '20s' }],
  },
};

// -----------------------------------------------------------------------------
// Login
//
// /login es un componente Volt, o sea que el submit va por el protocolo de
// Livewire y no por un POST de formulario clasico. Hay que leer el snapshot del
// HTML y mandarlo a /livewire/update.
//
// Esta es la parte fragil del script: si cambia el componente de login, se
// rompe. La alternativa robusta es una ruta de autenticacion solo-para-tests
// detras de un flag de entorno; ver docker/prod/README.md.
// -----------------------------------------------------------------------------
function htmlUnescape(s) {
  return s
    .replace(/&quot;/g, '"')
    .replace(/&#039;/g, "'")
    .replace(/&#39;/g, "'")
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&amp;/g, '&');
}

function login() {
  if (!USER_EMAIL || !USER_PASS) {
    fail('Faltan LOAD_USER_EMAIL / LOAD_USER_PASSWORD — sin sesion no se llega a ninguna pagina que consulte la legacy.');
  }

  const page = http.get(`${TARGET}/login`, { headers: HDR, tags: { name: 'GET /login' } });
  if (page.status !== 200) {
    authFailures.add(1);
    fail(`GET /login devolvio ${page.status}. Si es 503, estas comiendo el rate limit de Nginx (ver README).`);
  }

  const csrf = (page.body.match(/name="csrf-token"\s+content="([^"]+)"/) || [])[1];
  const snapshotRaw = (page.body.match(/wire:snapshot="([^"]+)"/) || [])[1];

  if (!csrf || !snapshotRaw) {
    authFailures.add(1);
    fail('No se pudo extraer csrf-token o wire:snapshot de /login — cambio el componente Volt.');
  }

  const payload = {
    _token: csrf,
    components: [{
      snapshot: htmlUnescape(snapshotRaw),
      updates: {
        'form.email': USER_EMAIL,
        'form.password': USER_PASS,
        'form.remember': false,
      },
      calls: [{ path: '', method: 'login', params: [] }],
    }],
  };

  const res = http.post(`${TARGET}/livewire/update`, JSON.stringify(payload), {
    headers: {
      ...HDR,
      'Content-Type': 'application/json',
      'X-Livewire': 'true',
      'X-CSRF-TOKEN': csrf,
    },
    tags: { name: 'POST /livewire/update (login)' },
  });

  const ok = check(res, {
    'login aceptado': (r) => r.status === 200 && !r.body.includes('"errors":{"form.email"'),
  });

  if (!ok) {
    authFailures.add(1);
    if (res.status === 503 || res.status === 429) {
      rateLimited.add(1);
      fail(`Login rechazado con ${res.status}: rate limit. En migrate/consultor.conf el zone login_limit es GLOBAL (10r/m para todos juntos), no por usuario. Ver README.`);
    }
    fail(`Login fallo con ${res.status}: ${res.body.slice(0, 300)}`);
  }
}

// -----------------------------------------------------------------------------
// Paginas que efectivamente consultan la legacy.
// El peso refleja mas o menos como las usa un alumno.
// -----------------------------------------------------------------------------
const PAGES = [
  { path: '/mis-carreras', weight: 4 },
  { path: '/mis-materias', weight: 3 },
  { path: '/mis-deudas',   weight: 2 },
  { path: '/dashboard',    weight: 1 },
];
const WEIGHTED = PAGES.flatMap((p) => Array(p.weight).fill(p.path));

export function setup() {
  const health = http.get(`${TARGET}/up`, { headers: HDR });
  if (health.status !== 200) {
    fail(`El espejo no responde en ${TARGET}/up (status ${health.status}).`);
  }
  console.log(`[espejo] ${TARGET} vhost=${VHOST} stage=${STAGE} rps=${RPS}`);
  console.log('[espejo] La legacy 10.10.254.252 es PRODUCTIVA. Cortá con Ctrl+C si ves latencias raras.');
}

export default function () {
  // Una sesion por VU, reusada en todas las iteraciones: si cada iteracion se
  // logueara, el rate limit de /login (10r/m global) cortaria la prueba de
  // entrada y no medirias las paginas.
  if (!__ITER || __ITER === 0) {
    login();
  }

  const path = WEIGHTED[Math.floor(Math.random() * WEIGHTED.length)];
  const res = http.get(`${TARGET}${path}`, { headers: HDR, tags: { name: `GET ${path}` } });

  legacyLatency.add(res.timings.duration, { page: path });
  rateLimited.add(res.status === 429 || res.status === 503);

  check(res, {
    'status 200': (r) => r.status === 200,
    'no redirigio al login (sesion viva)': (r) => !r.url.endsWith('/login'),
  });

  sleep(Math.random() * 2 + 0.5);   // think time
}

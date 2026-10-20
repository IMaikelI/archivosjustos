/*
 * Pruebas de interfaz de MaxRep con jsdom: carga las páginas reales servidas por PHP,
 * ejecuta los scripts del proyecto y simula interacciones (clics, tipeo, fallas de red).
 * No verifica aspecto visual (jsdom no dibuja ni calcula layout).
 *
 *   npm i jsdom@24            (en cualquier carpeta; luego NODE_PATH=<esa carpeta>/node_modules)
 *   MAXREP_URL=http://127.0.0.1:8080/Tesina MAXREP_URL_SHORT=http://127.0.0.1:8081/Tesina node tests/ui_test.js
 */
'use strict';
const { JSDOM, VirtualConsole, ResourceLoader } = require('jsdom');

const BASE = (process.env.MAXREP_URL || 'http://127.0.0.1:8080/Tesina').replace(/\/$/, '');
const SHORT = (process.env.MAXREP_URL_SHORT || '').replace(/\/$/, '');
const PASS = 'Prueba123';
const results = [];
const check = (name, cond, detail) => {
  results.push([name, !!cond]);
  console.log((cond ? '  OK  ' : '  FALLA ') + name + (cond ? '' : '  → ' + (detail === undefined ? '' : JSON.stringify(detail))));
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
async function waitFor(fn, ms = 4000) {
  const t0 = Date.now();
  for (;;) {
    try { const v = fn(); if (v) return v; } catch (e) { /* reintentar */ }
    if (Date.now() - t0 > ms) return null;
    await sleep(25);
  }
}

class Session {
  constructor(base) { this.base = base; this.cookies = new Map(); this.csrf = null; }
  async request(url, init = {}) {
    const headers = Object.assign({}, init.headers || {});
    if (this.cookies.size) headers.cookie = [...this.cookies].map(([k, v]) => k + '=' + v).join('; ');
    const res = await fetch(url, Object.assign({}, init, { headers, redirect: 'manual' }));
    for (const c of res.headers.getSetCookie ? res.headers.getSetCookie() : []) {
      const [kv] = c.split(';');
      const i = kv.indexOf('=');
      this.cookies.set(kv.slice(0, i).trim(), kv.slice(i + 1));
    }
    return res;
  }
  async init() {
    const html = await (await this.request(this.base + '/Cuenta/login.php')).text();
    this.csrf = (html.match(/name="csrf-token" content="([a-f0-9]+)"/) || [])[1];
    return this;
  }
  async api(path, body) {
    const res = await this.request(this.base + '/' + path, {
      method: body ? 'POST' : 'GET',
      headers: body ? { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf } : {},
      body: body ? JSON.stringify(body) : undefined,
    });
    return { status: res.status, json: await res.json().catch(() => null) };
  }
  async register(role, name) {
    const email = 'ui' + Math.random().toString(36).slice(2, 10) + '@test.local';
    const r = await this.api('Api/auth.php?action=register', { role, name, email, password: PASS, password2: PASS });
    return Object.assign(r, { email });
  }
}

class SameOriginLoader extends ResourceLoader {
  fetch(url, options) {
    const own = [BASE, SHORT].filter(Boolean).map((b) => new URL(b).origin);
    if (!own.includes(new URL(url).origin)) return Promise.resolve(Buffer.from('')); // sin fuentes externas
    return super.fetch(url, options);
  }
}

/** Abre una página como la vería el navegador. opts.fetch permite simular fallas de red. */
async function openPage(sess, path, opts = {}) {
  const res = await sess.request(sess.base + '/' + path);
  const html = await res.text();
  const errors = [];
  const vc = new VirtualConsole();
  vc.on('jsdomError', (e) => { if (!/navigation|Could not load link/.test(e.message)) errors.push(e.message); });
  const navigations = [];
  const dom = new JSDOM(html, {
    url: sess.base + '/' + path,
    runScripts: 'dangerously',
    resources: new SameOriginLoader(),
    pretendToBeVisual: true,
    virtualConsole: vc,
    beforeParse(w) {
      w.HTMLElement.prototype.scrollIntoView = function () {};
      w.scrollBy = () => {};
      w.print = () => {};
      Object.defineProperty(w.navigator, 'clipboard', { value: { writeText: async () => {} } });
      w.URL.createObjectURL = () => 'blob:fake';
      w.fetch = async (input, init = {}) => {
        if (opts.fetch) { const o = await opts.fetch(input, init); if (o !== undefined) return o; }
        const url = new w.URL(String(input), w.location.href).toString();
        let body = init.body;
        if (body && typeof body === 'object' && typeof body.entries === 'function' && !(body instanceof URLSearchParams)) {
          const fd = new FormData();
          for (const [k, v] of body.entries()) if (typeof v === 'string') fd.append(k, v);
          body = fd;
        }
        return sess.request(url, Object.assign({}, init, { body }));
      };
    },
  });
  const w = dom.window;
  await new Promise((r) => (w.document.readyState === 'complete' ? r() : w.addEventListener('load', r)));
  await sleep(30);
  return { w, d: w.document, errors, navigations };
}
const type = (w, el, v) => { el.value = v; el.dispatchEvent(new w.Event('input', { bubbles: true })); };
const toastText = (d) => [...d.querySelectorAll('.toast')].map((t) => t.textContent).join(' | ');

(async function main() {
  console.log('MaxRep UI tests contra ' + BASE + '\n');
  const alumno = await new Session(BASE).init();
  const reg = await alumno.register('usuario', 'Alumna UI');
  const profe = await new Session(BASE).init();
  await profe.register('profesor', 'Profe UI');
  const codigo = (await profe.api('Api/perfil.php')).json.profile.codigo;

  /* ---------------- CU-01 ---------------- */
  console.log('CU-01 Registrarse');
  {
    const s = await new Session(BASE).init();
    let p = await openPage(s, 'Cuenta/registro.php');
    const { d, w } = p;
    check('Muestra las tarjetas USUARIO y PROFESOR', [...d.querySelectorAll('.role-card__title')].map((x) => x.textContent).join() === 'USUARIO,PROFESOR');
    check('El formulario está oculto hasta elegir rol', d.getElementById('registerForm').hidden);
    d.querySelector('[data-role="usuario"]').click();
    check('Al elegir rol: tarjeta activa y formulario visible con animación', d.querySelector('[data-role="usuario"]').getAttribute('aria-checked') === 'true'
      && !d.getElementById('registerForm').hidden && d.getElementById('registerForm').classList.contains('reveal'));
    d.getElementById('registerBtn').click();
    await sleep(300);
    check('1.3 Vacíos: asterisco rojo y agitación en el primer campo', d.querySelector('#name').closest('.field').classList.contains('is-missing')
      && d.getElementById('name').classList.contains('shake'));
    type(w, d.getElementById('name'), 'Nuevo');
    type(w, d.getElementById('email'), reg.email);
    type(w, d.getElementById('password'), 'abcdefgh');
    type(w, d.getElementById('password2'), 'abcdefgh');
    d.getElementById('registerBtn').click();
    check('1.2 Contraseña débil: lista animada de requisitos faltantes', !d.getElementById('pwRules').hidden
      && d.querySelectorAll('#pwRules li.missing').length === 2 && d.getElementById('password').classList.contains('is-invalid'));
    type(w, d.getElementById('password'), PASS);
    type(w, d.getElementById('password2'), PASS);
    d.getElementById('registerBtn').click();
    check('Al enviar: botón deshabilitado con spinner', d.getElementById('registerBtn').disabled && d.getElementById('registerBtn').classList.contains('is-loading'));
    const tip = await waitFor(() => d.querySelector('.tooltip-error'));
    check('1.1 Correo existente: tooltip y borde rojo', tip && tip.textContent === 'Este correo ya está en uso' && d.getElementById('email').classList.contains('is-invalid'));
    check('1.1 El botón vuelve a su estado', !d.getElementById('registerBtn').disabled);

    p = await openPage(s, 'Cuenta/registro.php', { fetch: async () => { throw new TypeError('Failed to fetch'); } });
    p.d.querySelector('[data-role="profesor"]').click();
    type(p.w, p.d.getElementById('name'), 'X');
    type(p.w, p.d.getElementById('email'), 'x' + Date.now() + '@test.local');
    type(p.w, p.d.getElementById('password'), PASS);
    type(p.w, p.d.getElementById('password2'), PASS);
    p.d.getElementById('registerBtn').click();
    await waitFor(() => p.d.querySelector('.toast'));
    check('1.5 Sin red: botón gris y toast "Error de red"', p.d.getElementById('registerBtn').classList.contains('is-failed') && toastText(p.d).includes('Error de red'));
    check('Sin errores de JavaScript en registro', !p.errors.length, p.errors);
  }

  /* ---------------- CU-03 ---------------- */
  console.log('\nCU-03 Inicio');
  {
    let p = await openPage(alumno, 'index.php', { fetch: async () => { await sleep(150); } });
    check('Muestra skeleton loader mientras carga', p.d.querySelectorAll('#dashboard .skeleton').length > 0);
    const empty = await waitFor(() => p.d.querySelector('#dashboard .empty a.btn'));
    check('3.2 Usuario nuevo: estado vacío con "Crear tu primera rutina"', empty && empty.textContent === 'Crear tu primera rutina');
    p = await openPage(alumno, 'index.php', { fetch: async (u) => { if (String(u).includes('dashboard')) throw new TypeError('offline'); } });
    const retry = await waitFor(() => [...p.d.querySelectorAll('#dashboard button')].find((b) => b.textContent.includes('Reintentar conexión')));
    check('3.1 Error de conexión: ícono y botón "Reintentar conexión"', retry && p.d.querySelector('#dashboard [role="alert"] svg'));
    let calls = 0;
    p = await openPage(alumno, 'index.php', { fetch: async (u) => { if (String(u).includes('dashboard') && calls++ === 0) throw new TypeError('offline'); } });
    (await waitFor(() => [...p.d.querySelectorAll('#dashboard button')].find((b) => b.textContent.includes('Reintentar')))).click();
    check('3.1 Reintentar vuelve a cargar el dashboard', await waitFor(() => p.d.querySelector('#dashboard .card--hover, #dashboard .empty a')));
    check('Sin errores de JavaScript en inicio', !p.errors.length, p.errors);
  }

  /* ---------------- CU-04 ---------------- */
  console.log('\nCU-04 Calculadora RM');
  {
    const { d, w, errors } = await openPage(alumno, 'Ejercicios/Ejercicios.php');
    const $ = (id) => d.getElementById(id);
    $('rmToggle').click();
    check('La calculadora está disponible en otras pestañas', !$('rmPanel').hidden);
    check('"Calcular" deshabilitado sin datos', $('rmCalc').disabled);
    type(w, $('rmKg'), '100');
    type(w, $('rmReps'), '5');
    $('rmCalc').click();
    check('4.3 Sin método: "ELEGIR MÉTODO" parpadea', $('rmMethodBtn').classList.contains('blink') && $('rmResult').hidden);
    $('rmMethodBtn').click();
    check('Paso 1: se despliega el menú con los métodos', !$('rmMenu').hidden && $('rmMenu').querySelectorAll('[role="option"]').length === 4);
    d.querySelector('[data-method="brzycki"]').click();
    check('Paso 2: elegir método cierra el menú', $('rmMenu').hidden && $('rmMethodText').textContent === 'Brzycki');
    $('rmCalc').click();
    check('Paso 3: Brzycki 100 kg × 5 = 112,5 kg', !$('rmResult').hidden && $('rmOne').textContent === '112,5', $('rmOne').textContent);
    check('Tabla de porcentajes (12 RM = 72%)', d.querySelector('#rmTable tr:last-child').textContent.includes('81'), d.querySelector('#rmTable tr:last-child').textContent);
    $('rmMethodBtn').click();
    d.querySelector('[data-method="epley"]').click();
    check('Cambiar a Epley recalcula (100 × 5 = 116,7)', $('rmOne').textContent === '116,7', $('rmOne').textContent);
    type(w, $('rmReps'), '45');
    check('4.1 Más de 40 reps: alerta amarilla de precisión', !$('rmWarn').hidden && $('rmWarnText').textContent === 'Las fórmulas son menos precisas con altas repeticiones' && !$('rmWarn').classList.contains('is-error'));
    $('rmMethodBtn').click();
    d.querySelector('[data-method="brzycki"]').click();
    check('Brzycki con 45 reps avisa que no aplica', $('rmWarn').classList.contains('is-error'));
    type(w, $('rmKg'), '-5');
    $('rmKg').dispatchEvent(new w.Event('blur'));
    check('4.2 Peso negativo: se limpia al perder el foco y "Calcular" se desactiva', $('rmKg').value === '' && $('rmCalc').disabled);
    type(w, $('rmKg'), '0');
    check('4.2 Peso cero desactiva "Calcular"', $('rmCalc').disabled);
    check('Sin errores de JavaScript en la calculadora', !errors.length, errors);
  }

  /* ---------------- CU-02 ---------------- */
  console.log('\nCU-02 Perfil');
  {
    const { d, w, errors } = await openPage(alumno, 'Cuenta/Perfil.php');
    const $ = (id) => d.getElementById(id);
    check('Modo lectura: campos deshabilitados y botón "EDITAR PERFIL"', $('peso').disabled && $('editBtn').textContent.includes('EDITAR PERFIL') && $('saveBtn').hidden);
    $('editBtn').click();
    check('Modo edición: campos editables y "Guardar Cambios" visible', !$('peso').disabled && !$('saveBtn').hidden && $('email').disabled);
    type(w, $('peso'), 'setenta');
    $('saveBtn').click();
    await sleep(30);
    check('2.1 Letras en el peso: agitación y borde rojo, no se guarda', $('peso').classList.contains('shake') && $('peso').classList.contains('is-invalid') && !$('saveBtn').hidden);
    const big = new w.File([new Uint8Array(10)], 'f.gif', { type: 'image/gif' });
    Object.defineProperty($('photo'), 'files', { value: [big], configurable: true });
    $('photo').dispatchEvent(new w.Event('change'));
    check('2.2 Formato no admitido: previsualización oscurecida y globo de error', $('avatarBox').classList.contains('is-rejected')
      && d.querySelector('.avatar-box__bubble').textContent === 'Formato inválido o excede 5MB');
    Object.defineProperty($('photo'), 'files', { value: [], configurable: true });
    $('photo').value = '';
    $('photo').dispatchEvent(new w.Event('change'));
    type(w, $('peso'), '72.5');
    type(w, $('altura'), '175');
    type(w, $('objetivos'), 'Correr 10K');
    $('saveBtn').click();
    const t = await waitFor(() => d.querySelector('.toast--success'));
    check('Guardar: toast verde "Perfil actualizado" y vuelve a lectura', t && t.textContent === 'Perfil actualizado' && $('peso').disabled && $('peso').value === '72.5');
    const prof = (await alumno.api('Api/perfil.php')).json.profile;
    check('Los datos persisten en la base', prof.peso === 72.5 && prof.altura === 175 && prof.objetivos === 'Correr 10K', prof);
    check('Sin errores de JavaScript en perfil', !errors.length, errors);
  }
  if (SHORT) {
    const s = await new Session(SHORT).init();
    await s.register('usuario', 'Corta UI');
    const { d, w } = await openPage(s, 'Cuenta/Perfil.php');
    d.getElementById('editBtn').click();
    type(w, d.getElementById('name'), 'Renombrado');
    await sleep(3200);
    d.getElementById('saveBtn').click();
    const modal = await waitFor(() => d.querySelector('.backdrop .modal'));
    check('2.3 Sesión vencida: fondo oscurecido y modal de contraseña', modal && modal.textContent.includes('Sesión expirada') && d.getElementById('reauth-pass'));
    type(w, d.getElementById('reauth-pass'), PASS);
    d.getElementById('reauth-ok').click();
    const ok = await waitFor(() => d.querySelector('.toast--success'));
    check('2.3 Tras reautenticar se guarda el cambio', ok && !d.querySelector('.backdrop') && d.querySelector('[data-user-name]').textContent === 'Renombrado');
  }

  /* ---------------- CU-05 ---------------- */
  console.log('\nCU-05 Rutinas');
  {
    let p = await openPage(alumno, 'Rutinas/Rutinas.php?nueva=1');
    let { d, w } = p;
    const $ = (id) => d.getElementById(id);
    check('5.2 Lienzo vacío: botón "Añade al menos un ejercicio" deshabilitado', $('saveBtn').disabled && $('saveBtn').textContent === 'Añade al menos un ejercicio');
    $('addBtn').click();
    const items = await waitFor(() => { const l = d.querySelectorAll('.picker-item'); return l.length > 50 && l; });
    check('"+ Agregar Ejercicio" abre la biblioteca', !!items);
    items[0].click();
    items[1].click();
    items[2].click();
    check('Ejercicio agregado queda deshabilitado en el selector', items[0].disabled && items[0].textContent.includes('Agregado'));
    [...d.querySelectorAll('.modal__actions .btn')].find((b) => b.textContent === 'Listo').click();
    check('Lienzo con 3 tarjetas y guardado habilitado', d.querySelectorAll('.ex-item').length === 3 && !$('saveBtn').disabled);
    const firstName = d.querySelector('.ex-item .ex-item__name').firstChild.textContent;
    d.querySelectorAll('.ex-item')[0].querySelector('[aria-label^="Bajar"]').click();
    check('Reordenar con ↓ (alternativa al arrastre)', d.querySelectorAll('.ex-item')[1].querySelector('.ex-item__name').firstChild.textContent === firstName);
    const grip = d.querySelectorAll('.ex-item')[2].querySelector('.ex-item__grip');
    grip.dispatchEvent(new w.MouseEvent('pointerdown', { bubbles: true, button: 0, clientY: 0 }));
    check('Arrastrar: la tarjeta pasa a estado flotante', d.querySelectorAll('.ex-item')[2].classList.contains('is-dragging'));
    w.dispatchEvent(new w.MouseEvent('pointermove', { clientY: -10 }));
    w.dispatchEvent(new w.MouseEvent('pointerup', {}));
    check('Soltar: se reordena y quita el estado flotante', !d.querySelector('.is-dragging') && d.querySelectorAll('.ex-item').length === 3);
    const setsIn = d.querySelector('.ex-item input');
    type(w, setsIn, '0');
    check('Series inválidas bloquean el guardado', $('saveBtn').disabled && setsIn.classList.contains('is-invalid'));
    type(w, setsIn, '4');
    $('saveBtn').click();
    await sleep(50);
    check('Sin nombre: el campo se marca y no se guarda', $('wName').classList.contains('is-invalid'));
    type(w, $('wName'), 'UI Rutina');
    $('wDia').value = String((await alumno.api('Api/dashboard.php')).json.today.dia); // día según el servidor
    $('wDia').dispatchEvent(new w.Event('change'));
    $('saveBtn').click();
    const t = await waitFor(() => d.querySelector('.toast--success'));
    check('CREAR RUTINA: se guarda y confirma', t && t.textContent === 'Rutina creada');
    const id = +new w.URL(w.location.href).searchParams.get('id');
    check('La URL pasa a la rutina creada', id > 0);
    const saved = (await alumno.api('Api/rutinas.php?action=get&id=' + id)).json.work;
    check('Orden, series y nombre persistidos', saved.name === 'UI Rutina' && saved.items.length === 3 && saved.items[0].sets === 4, saved);

    // Colisión: otro guardado cambia la versión mientras este editor sigue abierto
    p = await openPage(alumno, 'Rutinas/Rutinas.php?id=' + id);
    ({ d, w } = p);
    await waitFor(() => d.querySelectorAll('.ex-item').length === 3);
    await alumno.api('Api/rutinas.php?action=save', { id, version: saved.version, name: 'Cambio externo', dia: saved.dia, items: saved.items.map((i) => ({ ej: i.ej, sets: i.sets, reps: i.reps })) });
    type(w, d.getElementById('wName'), 'Mi cambio');
    d.getElementById('saveBtn').click();
    const cm = await waitFor(() => d.querySelector('.backdrop .modal'));
    check('5.3 Colisión: modal "modificada por otro usuario… recarga la página"', cm && cm.textContent.includes('Esta rutina fue modificada por otro usuario recientemente. Por favor, recarga la página'));
    check('Sin errores de JavaScript en el editor', !p.errors.length, p.errors);

    // Lista, rutina de hoy y conexión con profesor
    p = await openPage(alumno, 'Rutinas/ListaRutinas.php');
    ({ d, w } = p);
    const today = await waitFor(() => d.querySelector('#todayBox a.btn'));
    check('"ENTRAR A RUTINA HECHA (EDITAR)" para la rutina del día', today && today.textContent === 'ENTRAR A RUTINA HECHA (EDITAR)');
    d.getElementById('connectBtn').click();
    const inp = await waitFor(() => d.getElementById('codeInput'));
    check('"CONECTARSE CON PROFESOR" abre modal con fondo desenfocado', inp && d.querySelector('.backdrop') && d.querySelector('.backdrop label').textContent === 'INTRODUCIR CÓDIGO DE PROFESOR');
    type(w, inp, 'NOEXISTE');
    d.getElementById('codeSubmit').click();
    const err = await waitFor(() => d.querySelector('.backdrop .field-error'));
    check('5.1 Código inválido: agitación y "Código no encontrado" sin cerrar', err && err.textContent === 'Código no encontrado' && inp.classList.contains('shake') && d.querySelector('.backdrop'));
    type(w, inp, codigo);
    d.getElementById('codeSubmit').click();
    const okm = await waitFor(() => d.querySelector('.check-anim'));
    check('Vinculación exitosa: check verde animado', okm && !d.getElementById('codeInput'));
    check('Sin errores de JavaScript en la lista', !p.errors.length, p.errors);

    // Vista del profesor
    p = await openPage(profe, 'Rutinas/ListaRutinas.php?tab=alumnos');
    const row = await waitFor(() => p.d.querySelector('#panel-alumnos tbody tr'));
    check('Profesor: "RUTINAS ALUMNOS" con tabla de alumnos', row && row.textContent.includes('Alumna UI'));
    row.click();
    check('Al tocar la fila se ven las rutinas del alumno', !row.nextElementSibling.hidden && row.nextElementSibling.textContent.includes('Cambio externo'));
  }

  /* ---------------- CU-06 ---------------- */
  console.log('\nCU-06 Ejercicios');
  {
    const { d, w, errors } = await openPage(alumno, 'Ejercicios/Ejercicios.php');
    const cards = await waitFor(() => { const c = d.querySelectorAll('.ex-card'); return c.length > 50 && c; });
    check('Grilla inicial de tarjetas', !!cards);
    const q = d.getElementById('q');
    q.dispatchEvent(new w.Event('focus'));
    type(w, q, 'sentadilla');
    await waitFor(() => d.querySelectorAll('.ex-card').length < 20 && d.querySelectorAll('.ex-card').length > 0, 3000);
    const names = [...d.querySelectorAll('.ex-card strong')].map((s) => s.textContent);
    check('Filtra en tiempo real (debounce)', names.length && names.every((n) => /entadilla/.test(n)), names);
    type(w, q, 'zzzzqqq');
    const empty = await waitFor(() => d.querySelector('#results .empty'));
    check('6.1 Sin resultados: estado vacío "No encontramos ejercicios con ese nombre"', empty && empty.textContent.includes('No encontramos ejercicios con ese nombre'));
    type(w, q, '');
    q.dispatchEvent(new w.Event('focus'));
    check('Sugerencias de búsquedas recientes', !d.getElementById('recent').hidden && d.getElementById('recent').textContent.includes('sentadilla'));
    await waitFor(() => d.querySelectorAll('.ex-card').length > 50);
    check('Tarjeta con superposición "Ver Info"', d.querySelector('.ex-card .ex-card__overlay').textContent === 'Ver Info');
    d.querySelector('.ex-card').click();
    const ph = await waitFor(() => d.querySelector('.modal .media-ph'));
    check('Detalle en modal con músculos implicados', d.querySelector('.modal .kv') && d.querySelector('.modal .kv').textContent.includes('Músculos implicados'));
    check('6.2 Sin multimedia: silueta "Contenido multimedia no disponible temporalmente"', ph && ph.textContent.includes('Contenido multimedia no disponible temporalmente'));
    type(w, d.getElementById('recPeso'), '50');
    d.querySelector('.modal form button[type="submit"]').click();
    check('Registrar una marca desde el detalle', await waitFor(() => toastText(d).includes('Marca registrada')));
    check('Sin errores de JavaScript en ejercicios', !errors.length, errors);
  }

  /* ---------------- CU-07 ---------------- */
  console.log('\nCU-07 Ajustes');
  {
    let p = await openPage(alumno, 'Ajustes/Ajustes.php');
    let { d, w } = p;
    const $ = (id) => d.getElementById(id);
    $('darkToggle').click();
    check('Modo oscuro: se aplica con transición', d.documentElement.dataset.theme === 'oscuro' && d.documentElement.classList.contains('theme-anim'));
    await sleep(200);
    check('El tema se guarda en la cuenta', (await (await alumno.request(BASE + '/index.php')).text()).includes('data-theme="oscuro"'));
    type(w, $('current'), 'Equivocada1');
    type(w, $('newPass'), 'OtraClave9');
    type(w, $('newPass2'), 'OtraClave9');
    check('Botón de contraseña habilitado con todos los datos', !$('passBtn').disabled);
    $('passBtn').click();
    await waitFor(() => $('current').classList.contains('is-invalid'));
    check('7.2 Clave actual errónea: input rojo y botón vuelve a inactivo', $('current').classList.contains('is-invalid') && $('passBtn').disabled);

    const buy = await waitFor(() => [...d.querySelectorAll('#subsBody button')].find((b) => b.textContent === 'Comprar'));
    buy.click();
    check('"Comprar" despliega el formulario "PONER DATOS" en modo prueba', $('cNumber') && d.querySelector('#subsBody').textContent.includes('Modo de prueba'));
    type(w, $('cNumber'), '4000000000000002');
    check('El número de tarjeta se formatea con espacios', $('cNumber').value === '4000 0000 0000 0002', $('cNumber').value);
    type(w, $('cHolder'), 'Alumna UI');
    type(w, $('cExp'), '1230');
    check('El vencimiento se formatea MM/AA', $('cExp').value === '12/30', $('cExp').value);
    type(w, $('cCvc'), '123');
    d.querySelector('#subsBody form button[type="submit"]').click();
    check('Barra de progreso durante el pago', !d.querySelector('#subsBody .progress').hidden);
    const ban = await waitFor(() => d.querySelector('#subsBody .banner--error'));
    check('7.1 Tarjeta rechazada: banner "Pago rechazado. Verifique los datos"', ban && ban.textContent === 'Pago rechazado. Verifique los datos');
    type(w, $('cNumber'), '4242424242424242');
    d.querySelector('#subsBody form button[type="submit"]').click();
    const rec = await waitFor(() => d.querySelector('#subsBody .receipt'));
    check('Pago aprobado: recibo digital', rec && rec.textContent.includes('MR-TEST-') && rec.textContent.includes('4242'));
    [...d.querySelectorAll('#subsBody button')].find((b) => b.textContent === 'Cancelar').click();
    const dm = await waitFor(() => d.querySelector('.modal--danger'));
    check('Cancelar: modal rojo "¿Estás seguro?"', dm && dm.querySelector('h2').textContent === '¿Estás seguro?');
    Object.defineProperty(w.navigator, 'onLine', { value: false, configurable: true });
    d.getElementById('confirmCancel').click();
    check('7.3 Sin conexión: loader "Esperando reconexión para procesar baja" y confirmar bloqueado',
      dm.textContent.includes('Esperando reconexión para procesar baja') && d.getElementById('confirmCancel').disabled);
    Object.defineProperty(w.navigator, 'onLine', { value: true, configurable: true });
    w.dispatchEvent(new w.Event('online'));
    check('Al reconectar se procesa la baja', await waitFor(() => toastText(d).includes('Suscripción cancelada')));
    $('helpBtn').click();
    const dr = await waitFor(() => d.querySelector('.drawer'));
    check('"Ayuda y soporte" abre panel lateral con FAQ y contacto', dr && dr.querySelectorAll('details').length >= 3 && d.getElementById('sAsunto'));
    type(w, d.getElementById('sAsunto'), 'Consulta UI');
    type(w, d.getElementById('sMensaje'), 'Mensaje de prueba desde la interfaz');
    dr.querySelector('form button[type="submit"]').click();
    check('Consulta de soporte enviada', await waitFor(() => dr.textContent.includes('Recibimos tu consulta')));
    check('Sin errores de JavaScript en ajustes', !p.errors.length, p.errors);
  }

  const ok = results.filter((r) => r[1]).length;
  console.log('\n' + ok + '/' + results.length + ' pruebas OK');
  process.exit(ok === results.length ? 0 : 1);
})().catch((e) => { console.error(e); process.exit(2); });

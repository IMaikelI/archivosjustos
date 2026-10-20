/* CU-04 — Calculadora de Repetición Máxima */
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const toggle = $('rmToggle');
  const panel = $('rmPanel');
  if (!toggle || !panel) return;
  const methodBtn = $('rmMethodBtn');
  const menu = $('rmMenu');
  const kg = $('rmKg');
  const reps = $('rmReps');
  const calc = $('rmCalc');
  const warn = $('rmWarn');
  const result = $('rmResult');
  const options = [...menu.querySelectorAll('[role="option"]')];

  const METHODS = {
    brzycki: { name: 'Brzycki', maxReps: 36, f: (w, r) => w * (36 / (37 - r)) },
    epley: { name: 'Epley', maxReps: Infinity, f: (w, r) => w * (1 + r / 30) },
    lander: { name: 'Lander', maxReps: 37, f: (w, r) => (100 * w) / (101.3 - 2.67123 * r) },
    lombardi: { name: 'Lombardi', maxReps: Infinity, f: (w, r) => w * Math.pow(r, 0.1) },
  };
  // Mismas equivalencias que la versión anterior de la calculadora
  const TABLE = [[1, 100], [2, 95], [4, 90], [6, 85], [8, 80], [10, 75], [12, 72]];
  let method = null;

  /* Panel */
  function setOpen(open) {
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    if (open) setTimeout(() => methodBtn.focus(), 30);
  }
  toggle.addEventListener('click', () => setOpen(panel.hidden));
  $('rmClose').addEventListener('click', () => { setOpen(false); toggle.focus(); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !panel.hidden && !document.querySelector('.backdrop')) {
      if (!menu.hidden) closeMenu(); else setOpen(false);
    }
  });

  /* Dropdown de métodos (paso 1 y 2) */
  function openMenu() {
    menu.hidden = false;
    methodBtn.setAttribute('aria-expanded', 'true');
    const cur = options.find((o) => o.dataset.method === method) || options[0];
    focusOption(cur);
  }
  function closeMenu() {
    menu.hidden = true;
    methodBtn.setAttribute('aria-expanded', 'false');
  }
  function focusOption(o) {
    options.forEach((x) => x.classList.toggle('is-active', x === o));
    menu.setAttribute('aria-activedescendant', o.id || (o.id = 'rm-opt-' + o.dataset.method));
    menu.focus();
  }
  function choose(o) {
    method = o.dataset.method;
    options.forEach((x) => x.setAttribute('aria-selected', String(x === o)));
    $('rmMethodText').textContent = METHODS[method].name;
    methodBtn.classList.add('has-value');
    closeMenu();
    methodBtn.focus();
    validate();
    if (!result.hidden) compute();
  }
  methodBtn.addEventListener('click', () => (menu.hidden ? openMenu() : closeMenu()));
  options.forEach((o) => {
    o.addEventListener('click', () => choose(o));
    o.addEventListener('mousemove', () => options.forEach((x) => x.classList.toggle('is-active', x === o)));
  });
  menu.addEventListener('keydown', (e) => {
    const i = options.findIndex((o) => o.classList.contains('is-active'));
    if (e.key === 'ArrowDown') { e.preventDefault(); focusOption(options[(i + 1) % options.length]); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); focusOption(options[(i - 1 + options.length) % options.length]); }
    else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); choose(options[Math.max(0, i)]); }
    else if (e.key === 'Escape') { e.stopPropagation(); closeMenu(); methodBtn.focus(); }
    else if (e.key === 'Tab') closeMenu();
  });
  document.addEventListener('click', (e) => {
    if (!menu.hidden && !$('rmMethod').contains(e.target)) closeMenu();
  });

  /* Validaciones */
  const weight = () => parseFloat(String(kg.value).replace(',', '.'));
  const repCount = () => parseInt(reps.value, 10);

  function showWarn(text, isError) {
    warn.hidden = !text;
    warn.classList.toggle('is-error', !!isError);
    $('rmWarnText').textContent = text || '';
  }
  function validate() {
    const w = weight();
    const r = repCount();
    // 4.2 — peso negativo o cero desactiva "Calcular"
    const okW = Number.isFinite(w) && w > 0 && w <= 1000;
    const okR = Number.isInteger(r) && r >= 1 && r <= 100;
    calc.disabled = !(okW && okR);
    // 4.1 — más de 40 repeticiones
    if (okR && method && r > METHODS[method].maxReps) {
      showWarn(METHODS[method].name + ' no admite ' + r + ' repeticiones. Elegí Epley o Lombardi.', true);
    } else if (okR && r > 40) {
      showWarn('Las fórmulas son menos precisas con altas repeticiones', false);
    } else if (reps.value !== '' && !okR) {
      showWarn('Ingresá entre 1 y 100 repeticiones.', true);
    } else {
      showWarn('');
    }
    return okW && okR;
  }
  kg.addEventListener('input', validate);
  reps.addEventListener('input', validate);
  kg.addEventListener('blur', () => {
    if (kg.value !== '' && !(weight() > 0)) { kg.value = ''; validate(); }
  });

  /* Cálculo (paso 3) */
  function compute() {
    const w = weight();
    const r = repCount();
    const m = METHODS[method];
    if (r > m.maxReps) return false;
    const one = r === 1 ? w : m.f(w, r);
    $('rmOne').textContent = MR.fmtNum(Math.round(one * 10) / 10);
    $('rmResultMethod').textContent = 'Método ' + m.name + ' · ' + MR.fmtNum(w) + ' kg × ' + r;
    const tb = $('rmTable');
    tb.replaceChildren(...TABLE.map(([n, pct]) => MR.el('tr', {},
      MR.el('td', { text: n + ' RM' }), MR.el('td', { text: pct + '%' }),
      MR.el('td', { text: MR.fmtNum(Math.round(one * pct / 10) / 10) + ' kg' }))));
    return true;
  }
  $('rmForm').addEventListener('submit', (e) => {
    e.preventDefault();
    if (!validate()) return;
    if (!method) { MR.blink(methodBtn); methodBtn.focus(); return; } // 4.3
    if (!compute()) return;
    result.hidden = false;
    result.style.animation = 'none';
    void result.offsetWidth;
    result.style.animation = '';
  });
})();

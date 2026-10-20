/* CU-05 — Lista de rutinas, conexión con profesor, rutinas de alumnos y códigos para compartir */
(function () {
  'use strict';
  const el = MR.el;
  const page = JSON.parse(document.getElementById('pageData').textContent);
  const isProf = page.role === 'profesor';
  const editUrl = (id) => MR.url('Rutinas/Rutinas.php?id=' + id);
  let data = null;

  /* ---------------- Pestañas ---------------- */
  const tabs = [...document.querySelectorAll('[role="tab"]')];
  function selectTab(tab) {
    tabs.forEach((t) => {
      const on = t === tab;
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
    });
    if (tab.id === 'tab-alumnos') loadAlumnos();
  }
  tabs.forEach((t, i) => {
    t.tabIndex = t.getAttribute('aria-selected') === 'true' ? 0 : -1;
    t.addEventListener('click', () => selectTab(t));
    t.addEventListener('keydown', (e) => {
      if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
      const n = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
      n.focus();
      selectTab(n);
    });
  });

  /* ---------------- Tarjetas ---------------- */
  function routineCard(w, opts) {
    const o = opts || {};
    const actions = el('div', { class: 'routine-card__actions' });
    if (w.canEdit) {
      actions.append(el('a', { class: 'btn btn-dark btn-sm', href: editUrl(w.id), text: 'ENTRAR A RUTINA (EDITAR)' }));
      if (!o.compact) {
        actions.append(
          el('button', { type: 'button', class: 'btn btn-sm', text: 'Compartir', onclick: () => share(w) }),
          el('button', { type: 'button', class: 'btn btn-ghost btn-sm', text: 'Borrar', onclick: (e) => confirmDelete(w, e.target.closest('.card')) }));
      }
    } else {
      actions.append(
        el('a', { class: 'btn btn-sm', href: editUrl(w.id), text: 'Ver rutina' }),
        el('button', { type: 'button', class: 'btn btn-ghost btn-sm', text: 'Quitar', onclick: (e) => unshare(w, e.target.closest('.card')) }));
    }
    return el('article', { class: 'card card--hover routine-card' },
      el('div', { class: 'routine-card__head' },
        el('h3', { text: w.name }),
        w.diaLabel ? el('span', { class: 'badge badge--accent', text: w.diaLabel }) : null),
      el('p', { class: 'muted small', style: 'margin:0', text: w.n + (w.n === 1 ? ' ejercicio' : ' ejercicios') + (o.showOwner ? ' · de ' + w.owner.name : '') }),
      actions);
  }

  function renderMine() {
    const panel = document.getElementById('panel-mias');
    if (!data.mine.length) {
      panel.replaceChildren(el('div', { class: 'card' }, MR.empty({
        title: 'Todavía no tenés rutinas',
        text: isProf ? 'Creá una rutina para vos o para un alumno.' : 'Creá tu primera rutina o pedile a tu profesor que te arme una.',
        action: { label: 'Crear tu primera rutina', href: MR.url('Rutinas/Rutinas.php?nueva=1') },
      })));
      return;
    }
    panel.replaceChildren(el('div', { class: 'routine-list' }, data.mine.map((w) => routineCard(w))));
  }
  function renderShared() {
    const panel = document.getElementById('panel-compartidas');
    panel.replaceChildren(data.shared.length
      ? el('div', { class: 'routine-list' }, data.shared.map((w) => routineCard(w, { showOwner: true })))
      : el('div', { class: 'card' }, MR.empty({ title: 'Nada compartido todavía', text: 'Si alguien te pasa un código de rutina, usalo con “Usar código de rutina”.' })));
  }
  function renderToday() {
    const box = document.getElementById('todayBox');
    const w = data.mine.find((x) => x.dia === data.today);
    box.replaceChildren(w ? el('div', { class: 'card card--hover', style: 'margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap' },
      el('div', {}, el('p', { class: 'eyebrow', text: 'Rutina de hoy · ' + w.diaLabel }), el('h3', { style: 'margin:0', text: w.name })),
      el('a', { class: 'btn btn-primary', href: editUrl(w.id), text: 'ENTRAR A RUTINA HECHA (EDITAR)' })) : '');
  }
  function renderProf() {
    const box = document.getElementById('profBox');
    if (isProf) {
      box.replaceChildren(el('div', { class: 'code-box', style: 'margin-bottom:20px;flex-wrap:wrap' },
        el('div', {}, el('div', { class: 'xs', style: 'color:#BDBDBD', text: 'Tu código de profesor — compartilo con tus alumnos' }), el('code', { text: page.codigo })),
        el('button', { type: 'button', class: 'btn btn-sm', text: 'Copiar', onclick: () => copy(page.codigo, 'Código copiado') })));
      return;
    }
    box.replaceChildren(data.profesor ? el('div', { class: 'banner banner--info', style: 'justify-content:space-between;align-items:center;flex-wrap:wrap' },
      el('span', {}, 'Profesor vinculado: ', el('strong', { text: data.profesor.name })),
      el('button', { type: 'button', class: 'btn btn-ghost btn-sm', text: 'Desvincular', onclick: unlink })) : '');
    const btn = document.getElementById('connectBtn');
    if (btn) btn.lastChild.textContent = data.profesor ? 'CAMBIAR DE PROFESOR' : 'CONECTARSE CON PROFESOR';
  }

  async function load() {
    const panel = document.getElementById('panel-mias');
    panel.replaceChildren(el('div', { class: 'routine-list' }, [1, 2, 3].map(() => el('div', { class: 'skeleton skeleton-card' }))));
    try {
      data = await MR.api('Api/rutinas.php?action=list');
      renderToday();
      renderProf();
      renderMine();
      renderShared();
    } catch (err) {
      panel.replaceChildren(el('div', { class: 'card' }, MR.errorState('No pudimos cargar tus rutinas', load)));
    }
  }

  /* ---------------- Rutinas de alumnos (profesor) ---------------- */
  let alumnosLoaded = false;
  async function loadAlumnos(force) {
    const panel = document.getElementById('panel-alumnos');
    if (!panel || (alumnosLoaded && !force)) return;
    panel.replaceChildren(el('div', { class: 'skeleton skeleton-card' }));
    try {
      const res = await MR.api('Api/rutinas.php?action=alumnos');
      alumnosLoaded = true;
      if (!res.alumnos.length) {
        panel.replaceChildren(el('div', { class: 'card' }, MR.empty({
          title: 'Sin alumnos vinculados',
          text: 'Pasales tu código ' + page.codigo + ' para que se conecten desde su sección Rutinas.',
        })));
        return;
      }
      const tbody = el('tbody');
      res.alumnos.forEach((a) => {
        const detail = el('tr', { hidden: true }, el('td', { colspan: '4', style: 'background:var(--bg-soft)' },
          a.works.length
            ? el('div', { class: 'routine-list', style: 'padding:8px 0' }, a.works.map((w) => routineCard(w, { compact: true })))
            : el('p', { class: 'muted', style: 'margin:8px 0', text: a.name + ' todavía no tiene rutinas.' }),
          el('a', { class: 'btn btn-primary btn-sm', style: 'margin-top:8px', href: MR.url('Rutinas/Rutinas.php?nueva=1&para=' + a.id), text: 'Crear rutina para ' + a.name })));
        const row = el('tr', { tabindex: '0', style: 'cursor:pointer', 'aria-expanded': 'false' },
          el('td', {}, el('strong', { text: a.name }), el('div', { class: 'xs muted', text: a.email })),
          el('td', { text: String(a.works.length) }),
          el('td', { text: a.lastUpdate ? MR.fmtDate(a.lastUpdate) : '—' }),
          el('td', { class: 'muted small', text: a.objetivos || '—' }));
        const toggle = () => {
          detail.hidden = !detail.hidden;
          row.setAttribute('aria-expanded', String(!detail.hidden));
        };
        row.addEventListener('click', toggle);
        row.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });
        tbody.append(row, detail);
      });
      panel.replaceChildren(el('div', { class: 'table-wrap' }, el('table', { class: 'table' },
        el('thead', {}, el('tr', {}, el('th', { text: 'Alumno' }), el('th', { text: 'Rutinas' }), el('th', { text: 'Última actualización' }), el('th', { text: 'Objetivos' }))),
        tbody)));
    } catch (err) {
      panel.replaceChildren(el('div', { class: 'card' }, MR.errorState('No pudimos cargar a tus alumnos', () => loadAlumnos(true))));
    }
  }

  /* ---------------- Acciones ---------------- */
  async function copy(text, okMsg) {
    try { await navigator.clipboard.writeText(text); MR.toast(okMsg, 'success'); }
    catch (e) { MR.toast('Copialo manualmente: ' + text); }
  }

  function codeModal({ title, text, label, placeholder, submitLabel, onSubmit }) {
    const input = el('input', { class: 'input', id: 'codeInput', autocomplete: 'off', placeholder, maxlength: '10', style: 'text-transform:uppercase;letter-spacing:.12em;font-weight:600' });
    const form = el('form', { novalidate: true },
      text ? el('p', { class: 'muted', text }) : null,
      el('div', { class: 'field' }, el('label', { for: 'codeInput', text: label }), input));
    const m = MR.modal({
      title, body: form,
      actions: [
        { label: 'Cancelar', class: 'btn-ghost', onClick: (mm) => mm.close() },
        { label: submitLabel, class: 'btn-primary', id: 'codeSubmit', onClick: () => submit() },
      ],
    });
    input.addEventListener('input', () => MR.clearError(input));
    form.addEventListener('submit', (e) => { e.preventDefault(); submit(); });
    async function submit() {
      const btn = m.el.querySelector('#codeSubmit');
      if (!input.value.trim()) { MR.fieldError(input, 'Ingresá el código'); return MR.shake(input); }
      MR.loading(btn, true);
      try {
        await onSubmit(input.value.trim(), m);
      } catch (err) {
        MR.loading(btn, false);
        if (err.code === 'reauth_cancelled') return;
        MR.fieldError(input, err.code === 'network' ? 'Error de red. Intentá de nuevo.' : err.message);
        MR.shake(input); // 5.1 — el modal no se cierra
      }
    }
    return m;
  }

  function successCheck(message) {
    const body = el('div', { style: 'text-align:center' });
    body.insertAdjacentHTML('beforeend', '<svg class="check-anim" viewBox="0 0 80 80" aria-hidden="true"><circle cx="40" cy="40" r="34"/><path d="M25 41l10 10 20-22"/></svg>');
    body.append(el('p', { style: 'font-weight:600;margin:0', text: message }));
    const m = MR.modal({ title: '¡Listo!', body, dismissible: true });
    setTimeout(() => m.close(), 1800);
  }

  function connect() {
    codeModal({
      title: 'Conectarse con profesor',
      text: 'Pedile a tu profesor su código de 8 caracteres.',
      label: 'INTRODUCIR CÓDIGO DE PROFESOR',
      placeholder: 'Ej. PROF2026',
      submitLabel: 'Vincular',
      async onSubmit(code, m) {
        const res = await MR.api('Api/rutinas.php?action=link', { method: 'POST', body: { code } });
        m.close();
        data.profesor = res.profesor;
        renderProf();
        successCheck('Te vinculaste con ' + res.profesor.name);
      },
    });
  }
  async function unlink() {
    const m = MR.modal({
      title: '¿Desvincular profesor?', body: 'Tu profesor dejará de ver y editar tus rutinas.', danger: true,
      actions: [
        { label: 'Cancelar', class: 'btn-ghost', onClick: (mm) => mm.close() },
        { label: 'Desvincular', class: 'btn-danger', onClick: async (mm, b) => {
          MR.loading(b, true);
          try { await MR.api('Api/rutinas.php?action=unlink', { method: 'POST', body: {} }); mm.close(); data.profesor = null; renderProf(); MR.toast('Profesor desvinculado', 'success'); }
          catch (err) { MR.loading(b, false); MR.toast(err.message, 'error'); }
        } },
      ],
    });
    return m;
  }

  function redeem() {
    codeModal({
      title: 'Usar código de rutina',
      text: 'Alguien te compartió una rutina: ingresá el código de 6 caracteres. Vence a los 30 minutos y sirve una sola vez.',
      label: 'Código de rutina',
      placeholder: 'Ej. K7P2QX',
      submitLabel: 'Agregar',
      async onSubmit(code, m) {
        const res = await MR.api('Api/rutinas.php?action=redeem', { method: 'POST', body: { code } });
        m.close();
        MR.toast('“' + res.work.name + '” se agregó a Compartidas conmigo', 'success');
        await load();
        selectTab(document.getElementById('tab-compartidas'));
      },
    });
  }

  async function share(w) {
    try {
      const res = await MR.api('Api/rutinas.php?action=share', { method: 'POST', body: { id: w.id } });
      MR.modal({
        title: 'Compartir “' + w.name + '”',
        body: el('div', {},
          el('p', { class: 'muted', text: 'Pasale este código a quien quieras. Vence en ' + res.minutes + ' minutos y se puede usar una vez.' }),
          el('div', { class: 'code-box' }, el('code', { text: res.code }),
            el('button', { type: 'button', class: 'btn btn-sm', text: 'Copiar', onclick: () => copy(res.code, 'Código copiado') }))),
        actions: [{ label: 'Listo', class: 'btn-dark', onClick: (m) => m.close() }],
      });
    } catch (err) {
      if (err.code !== 'reauth_cancelled') MR.toast(err.message, 'error');
    }
  }

  function confirmDelete(w, cardEl) {
    MR.modal({
      title: '¿Borrar “' + w.name + '”?', danger: true,
      body: 'Esta acción no se puede deshacer.',
      actions: [
        { label: 'Cancelar', class: 'btn-ghost', onClick: (m) => m.close() },
        { label: 'Borrar', class: 'btn-danger', onClick: async (m, b) => {
          MR.loading(b, true);
          try {
            await MR.api('Api/rutinas.php?action=delete', { method: 'POST', body: { id: w.id } });
            m.close();
            data.mine = data.mine.filter((x) => x.id !== w.id);
            renderMine();
            renderToday();
            MR.toast('Rutina borrada', 'success');
          } catch (err) { MR.loading(b, false); MR.toast(err.message, 'error'); }
        } },
      ],
    });
  }

  async function unshare(w) {
    try {
      await MR.api('Api/rutinas.php?action=unshare', { method: 'POST', body: { id: w.id } });
      data.shared = data.shared.filter((x) => x.id !== w.id);
      renderShared();
      MR.toast('Rutina quitada', 'success');
    } catch (err) { MR.toast(err.message, 'error'); }
  }

  const connectBtn = document.getElementById('connectBtn');
  if (connectBtn) connectBtn.addEventListener('click', connect);
  document.getElementById('redeemBtn').addEventListener('click', redeem);

  load().then(() => {
    if (connectBtn && connectBtn.hasAttribute('data-autoopen')) connect();
    const sel = tabs.find((t) => t.getAttribute('aria-selected') === 'true');
    if (sel && sel.id === 'tab-alumnos') loadAlumnos();
  });
})();

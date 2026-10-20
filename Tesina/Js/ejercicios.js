/* CU-06 — Buscador, grilla de tarjetas, detalle en modal y marcas personales */
(function () {
  'use strict';
  const el = MR.el;
  const $ = (id) => document.getElementById(id);
  const q = $('q');
  const recentBox = $('recent');
  const results = $('results');
  const KEY = 'mr-recent-search';
  const filters = { mu: '', type: '' };
  let reqId = 0;

  /* ---------------- Búsquedas recientes (por navegador) ---------------- */
  const getRecent = () => { try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch (e) { return []; } };
  function pushRecent(term) {
    term = term.trim();
    if (term.length < 2) return;
    const list = [term, ...getRecent().filter((t) => t.toLowerCase() !== term.toLowerCase())].slice(0, 6);
    try { localStorage.setItem(KEY, JSON.stringify(list)); } catch (e) { /* sin almacenamiento */ }
  }
  function showRecent() {
    const term = q.value.trim().toLowerCase();
    const list = getRecent().filter((t) => !term || (t.toLowerCase().includes(term) && t.toLowerCase() !== term));
    if (!list.length) return hideRecent();
    recentBox.replaceChildren(...list.map((t) => {
      const b = el('button', { type: 'button', role: 'option' });
      b.append(MR.iconEl('clock'), document.createTextNode(t));
      b.addEventListener('mousedown', (e) => { e.preventDefault(); q.value = t; hideRecent(); search(true); });
      return el('li', {}, b);
    }));
    recentBox.hidden = false;
    q.setAttribute('aria-expanded', 'true');
  }
  function hideRecent() { recentBox.hidden = true; q.setAttribute('aria-expanded', 'false'); }
  q.addEventListener('focus', showRecent);
  q.addEventListener('blur', () => setTimeout(hideRecent, 120));
  q.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') hideRecent();
    if (e.key === 'Enter') { e.preventDefault(); hideRecent(); search(true); }
    if (e.key === 'ArrowDown' && !recentBox.hidden) { e.preventDefault(); recentBox.querySelector('button').focus(); }
  });
  recentBox.addEventListener('keydown', (e) => {
    const items = [...recentBox.querySelectorAll('button')];
    const i = items.indexOf(document.activeElement);
    if (e.key === 'ArrowDown') { e.preventDefault(); (items[i + 1] || items[0]).focus(); }
    if (e.key === 'ArrowUp') { e.preventDefault(); i <= 0 ? q.focus() : items[i - 1].focus(); }
    if (e.key === 'Enter') { e.preventDefault(); q.value = document.activeElement.textContent; hideRecent(); q.focus(); search(true); }
    if (e.key === 'Escape') { hideRecent(); q.focus(); }
  });

  /* ---------------- Filtros ---------------- */
  document.querySelectorAll('.chip').forEach((chip) => chip.addEventListener('click', () => {
    const k = chip.dataset.mu !== undefined ? 'mu' : 'type';
    const v = chip.dataset.mu !== undefined ? chip.dataset.mu : chip.dataset.type;
    filters[k] = filters[k] === v ? '' : v;
    document.querySelectorAll(k === 'mu' ? '.chip[data-mu]' : '.chip[data-type]').forEach((c) => {
      c.setAttribute('aria-pressed', String((k === 'mu' ? c.dataset.mu : c.dataset.type) === filters[k]));
    });
    search();
  }));

  /* ---------------- Resultados ---------------- */
  function initials(name) {
    return (name || '').slice(0, 3).toUpperCase();
  }
  function placeholder(ex) {
    return el('div', { class: 'ex-card__ph', 'aria-hidden': 'true', text: initials(ex.muName) });
  }
  function exCard(ex) {
    const media = el('div', { class: 'ex-card__media' });
    if (ex.img) {
      const img = el('img', { src: ex.img, alt: '', loading: 'lazy', decoding: 'async' });
      img.addEventListener('error', () => img.replaceWith(placeholder(ex)), { once: true });
      media.append(img);
    } else {
      media.append(placeholder(ex));
    }
    media.append(el('div', { class: 'ex-card__overlay', 'aria-hidden': 'true' }, el('span', { text: 'Ver Info' })));
    const card = el('button', { type: 'button', class: 'ex-card', 'aria-label': ex.name + ', ver información' },
      media,
      el('div', { class: 'ex-card__body' },
        el('strong', { text: ex.name }),
        el('span', {}, el('span', { class: 'badge', text: ex.muName }), ' ', el('span', { class: 'badge', text: ex.type }))));
    card.addEventListener('click', () => openDetail(ex));
    return card;
  }

  async function search(remember) {
    const my = ++reqId;
    const term = q.value.trim();
    results.setAttribute('aria-busy', 'true');
    if (!results.firstChild || results.querySelector('.empty')) {
      results.replaceChildren(el('div', { class: 'ex-grid' }, Array.from({ length: 8 }, () => el('div', { class: 'skeleton', style: 'height:190px;border-radius:10px' }))));
    }
    try {
      const res = await MR.api('Api/ejercicios.php', { params: { action: 'list', q: term, mu: filters.mu, type: filters.type } });
      if (my !== reqId) return;
      results.setAttribute('aria-busy', 'false');
      if (remember && res.items.length) pushRecent(term);
      $('resultCount').textContent = res.items.length ? res.items.length + (res.items.length === 1 ? ' ejercicio' : ' ejercicios') : '';
      if (!res.items.length) {   // 6.1
        results.replaceChildren(MR.empty({ title: '', text: 'No encontramos ejercicios con ese nombre' }));
        return;
      }
      results.replaceChildren(el('div', { class: 'ex-grid' }, res.items.map(exCard)));
    } catch (err) {
      if (my !== reqId) return;
      results.setAttribute('aria-busy', 'false');
      results.replaceChildren(MR.errorState(err.code === 'network' ? 'Sin conexión con el servidor' : 'No pudimos cargar los ejercicios', () => search()));
    }
  }
  const debounced = MR.debounce(() => search(true), 300);
  q.addEventListener('input', () => { showRecent(); debounced(); });

  /* ---------------- Detalle ---------------- */
  function mediaUnavailable() {   // 6.2
    const ph = el('div', { class: 'media-ph', role: 'img', 'aria-label': 'Contenido multimedia no disponible temporalmente' });
    ph.append(MR.iconEl('image'), el('span', { text: 'Contenido multimedia no disponible temporalmente' }));
    return ph;
  }
  function mediaFor(ex) {
    if (ex.video && ex.video.kind === 'youtube') {
      return el('iframe', { class: 'media-frame', src: ex.video.src, title: 'Video de ' + ex.name, loading: 'lazy',
        allow: 'accelerometer; encrypted-media; gyroscope; picture-in-picture', allowfullscreen: true, referrerpolicy: 'strict-origin-when-cross-origin' });
    }
    if (ex.video) {
      const v = el('video', { class: 'media-frame', src: ex.video.src, controls: true, preload: 'metadata', playsinline: true });
      v.addEventListener('error', () => v.replaceWith(mediaUnavailable()), { once: true });
      return v;
    }
    if (ex.img) {
      const img = el('img', { class: 'media-img', src: ex.img, alt: 'Demostración de ' + ex.name });
      img.addEventListener('error', () => img.replaceWith(mediaUnavailable()), { once: true });
      return img;
    }
    return mediaUnavailable();
  }

  function recordsBlock(ex, onChange) {
    const wrap = el('div', { class: 'section', style: 'margin-top:20px' });
    const today = new Date();
    const iso = new Date(today.getTime() - today.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    const peso = el('input', { class: 'input', id: 'recPeso', type: 'number', min: '0.5', max: '1000', step: '0.5', inputmode: 'decimal', placeholder: 'kg' });
    const fecha = el('input', { class: 'input', id: 'recFecha', type: 'date', value: iso, max: iso });
    const add = el('button', { type: 'submit', class: 'btn btn-primary', text: 'Registrar' });
    const form = el('form', { novalidate: true, class: 'row', style: 'align-items:end' },
      el('div', { class: 'field', style: 'margin:0' }, el('label', { for: 'recPeso', text: 'Peso levantado' }), peso),
      el('div', { class: 'field', style: 'margin:0' }, el('label', { for: 'recFecha', text: 'Fecha' }), fecha),
      add);
    const list = el('ul', { class: 'record-list' });
    function paint() {
      list.replaceChildren(...(ex.records.length ? ex.records.map((r) => {
        const del = el('button', { type: 'button', class: 'btn btn-ghost btn-icon', 'aria-label': 'Borrar marca del ' + MR.fmtDate(r.fecha) });
        del.append(MR.iconEl('trash'));
        del.addEventListener('click', async () => {
          try {
            await MR.api('Api/ejercicios.php?action=record_delete', { method: 'POST', body: { id: r.id } });
            onChange();
          } catch (err) { MR.toast(err.message, 'error'); }
        });
        return el('li', { style: 'align-items:center' },
          el('span', { text: MR.fmtDate(r.fecha) }),
          el('span', { style: 'display:flex;align-items:center;gap:8px' },
            el('strong', { text: MR.fmtNum(r.peso) + ' kg' }),
            r.delta ? el('span', { class: r.delta > 0 ? 'delta-up' : 'delta-down', text: (r.delta > 0 ? '+' : '') + MR.fmtNum(r.delta) }) : null,
            del));
      }) : [el('li', { class: 'muted', text: 'Todavía no registraste marcas en este ejercicio.' })]));
    }
    paint();
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      [peso, fecha].forEach((i) => MR.clearError(i));
      if (!(parseFloat(peso.value) > 0)) { MR.fieldError(peso, 'Ingresá un peso mayor a 0'); return MR.shake(peso); }
      if (!fecha.value || fecha.value > iso) { MR.fieldError(fecha, 'La fecha no puede ser futura'); return MR.shake(fecha); }
      MR.loading(add, true);
      try {
        await MR.api('Api/ejercicios.php?action=record', { method: 'POST', body: { ejercicioId: ex.id, peso: peso.value, fecha: fecha.value } });
        MR.toast('Marca registrada', 'success');
        onChange();
      } catch (err) {
        MR.loading(add, false);
        if (err.data && err.data.fields) Object.entries(err.data.fields).forEach(([k, m]) => MR.fieldError(k === 'peso' ? peso : fecha, m));
        else if (err.code !== 'reauth_cancelled') MR.toast(err.message, 'error');
      }
    });
    wrap.append(el('h3', { text: 'Mis marcas' + (ex.best ? ' · mejor: ' + MR.fmtNum(ex.best) + ' kg' : '') }), form, list);
    return wrap;
  }

  async function openDetail(summary) {
    const body = el('div', {}, el('div', { class: 'spinner', style: 'margin:24px auto' }));
    const m = MR.modal({ title: summary.name, body, wide: true });
    async function fill() {
      try {
        const { ejercicio: ex } = await MR.api('Api/ejercicios.php', { params: { action: 'get', id: summary.id } });
        if (m.closed) return;
        body.replaceChildren(
          mediaFor(ex),
          el('dl', { class: 'kv' },
            el('dt', { text: 'Músculos implicados' }), el('dd', { text: ex.muName || '—' }),
            el('dt', { text: 'Tipo' }), el('dd', { text: ex.type === 'Cable' ? 'Cable / polea' : 'Peso libre o máquina' })),
          el('h3', { text: 'Técnica' }),
          ex.info ? el('p', { style: 'white-space:pre-line', text: ex.info })
            : el('p', { class: 'muted', text: 'La descripción técnica de este ejercicio todavía no está cargada.' }),
          recordsBlock(ex, fill));
      } catch (err) {
        if (!m.closed) body.replaceChildren(MR.errorState('No pudimos cargar el ejercicio', fill));
      }
    }
    fill();
  }

  search();
})();

/* CU-05 — Lienzo de rutina: agregar ejercicios, arrastrar y soltar, guardar con control de concurrencia */
(function () {
  'use strict';
  const el = MR.el;
  const $ = (id) => document.getElementById(id);
  const root = $('editor');
  const canvas = $('canvas');
  const saveBtn = $('saveBtn');
  const addBtn = $('addBtn');
  const nameIn = $('wName');
  const diaIn = $('wDia');
  const ownerIn = $('wOwner');

  const state = { id: +root.dataset.id || 0, version: 0, canEdit: true, items: [], owner: null };
  let saved = '';
  let saving = false;

  const snapshot = () => JSON.stringify({ n: nameIn.value.trim(), d: diaIn.value, i: state.items.map((i) => [i.ej, i.sets, i.reps]) });
  const isDirty = () => snapshot() !== saved;

  /* ---------------- Render ---------------- */
  function render() {
    canvas.replaceChildren();
    canvas.classList.toggle('is-empty', !state.items.length);
    if (!state.items.length) {
      canvas.append(el('div', {},
        el('p', { style: 'font-weight:600;margin:0', text: state.canEdit ? 'El lienzo está vacío' : 'Esta rutina no tiene ejercicios' }),
        state.canEdit ? el('p', { class: 'small', style: 'margin:4px 0 0', text: 'Usá “+ Agregar Ejercicio” para empezar.' }) : null));
    }
    state.items.forEach((it, idx) => canvas.append(itemEl(it, idx)));
    $('countLabel').textContent = state.items.length ? state.items.length + (state.items.length === 1 ? ' ejercicio' : ' ejercicios') : '';
    updateSave();
  }

  function numInput(it, key, max, label) {
    it['bad_' + key] = false; // al re-renderizar se muestra el último valor válido
    const inp = el('input', {
      class: 'input', type: 'number', min: '1', max: String(max), step: '1', inputmode: 'numeric',
      value: String(it[key]), 'aria-label': label + ' de ' + it.name, disabled: !state.canEdit,
    });
    inp.addEventListener('input', () => {
      const v = parseInt(inp.value, 10);
      const ok = Number.isInteger(v) && v >= 1 && v <= max;
      inp.classList.toggle('is-invalid', !ok);
      if (ok) it[key] = v;
      it['bad_' + key] = !ok;
      updateSave();
    });
    return inp;
  }

  function itemEl(it, idx) {
    const node = el('div', { class: 'ex-item', dataset: { ej: it.ej } });
    const grip = el('button', { type: 'button', class: 'ex-item__grip', 'aria-label': 'Arrastrar ' + it.name, title: 'Arrastrar para reordenar', hidden: !state.canEdit });
    grip.append(MR.iconEl('grip'));
    grip.addEventListener('pointerdown', (e) => startDrag(e, node));
    const nums = el('div', { class: 'ex-item__nums', style: 'display:flex;gap:8px' },
      el('label', { class: 'ex-item__num' }, 'Series', numInput(it, 'sets', 50, 'Series')),
      el('label', { class: 'ex-item__num' }, 'Reps', numInput(it, 'reps', 100, 'Repeticiones')));
    const move = el('div', { class: 'ex-item__move', hidden: !state.canEdit });
    const up = el('button', { type: 'button', class: 'btn btn-ghost btn-icon', 'aria-label': 'Subir ' + it.name, disabled: idx === 0 });
    up.append(MR.iconEl('up'));
    up.addEventListener('click', () => moveItem(idx, -1));
    const down = el('button', { type: 'button', class: 'btn btn-ghost btn-icon', 'aria-label': 'Bajar ' + it.name, disabled: idx === state.items.length - 1 });
    down.append(MR.iconEl('down'));
    down.addEventListener('click', () => moveItem(idx, 1));
    move.append(up, down);
    const del = el('button', { type: 'button', class: 'btn btn-ghost btn-icon', 'aria-label': 'Quitar ' + it.name, hidden: !state.canEdit });
    del.append(MR.iconEl('trash'));
    del.addEventListener('click', () => {
      state.items.splice(idx, 1);
      render();
      MR.toast(it.name + ' quitado de la rutina');
    });
    node.append(grip,
      el('div', { class: 'ex-item__name' }, it.name, el('small', { text: [it.mu, it.type].filter(Boolean).join(' · ') })),
      nums, move, del);
    if (!state.canEdit) node.style.gridTemplateColumns = '1fr auto';
    return node;
  }

  function moveItem(idx, dir) {
    const to = idx + dir;
    if (to < 0 || to >= state.items.length) return;
    const [it] = state.items.splice(idx, 1);
    state.items.splice(to, 0, it);
    render();
    const btn = canvas.children[to].querySelector(dir < 0 ? '[aria-label^="Subir"]' : '[aria-label^="Bajar"]');
    (btn && !btn.disabled ? btn : canvas.children[to].querySelector('input')).focus();
  }

  /* ---------------- Arrastrar y soltar (mouse y táctil) ---------------- */
  function startDrag(e, node) {
    if (!state.canEdit || (e.button !== undefined && e.button !== 0)) return;
    e.preventDefault();
    node.classList.add('is-dragging');
    document.body.style.userSelect = 'none';
    const onMove = (ev) => {
      const y = ev.clientY;
      const others = [...canvas.querySelectorAll('.ex-item:not(.is-dragging)')];
      let before = null;
      for (const o of others) {
        const r = o.getBoundingClientRect();
        if (y < r.top + r.height / 2) { before = o; break; }
      }
      if (before) { if (node.nextSibling !== before) canvas.insertBefore(node, before); }
      else if (canvas.lastElementChild !== node) canvas.append(node);
      if (y < 60) window.scrollBy(0, -12);
      else if (y > window.innerHeight - 60) window.scrollBy(0, 12);
    };
    const onUp = () => {
      window.removeEventListener('pointermove', onMove);
      window.removeEventListener('pointerup', onUp);
      window.removeEventListener('pointercancel', onUp);
      document.body.style.userSelect = '';
      node.classList.remove('is-dragging');
      const order = [...canvas.querySelectorAll('.ex-item')].map((n) => +n.dataset.ej);
      state.items.sort((a, b) => order.indexOf(a.ej) - order.indexOf(b.ej));
      render();
    };
    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onUp);
    window.addEventListener('pointercancel', onUp);
  }

  /* ---------------- Guardado ---------------- */
  function updateSave() {
    const dirty = isDirty();
    $('dirtyBadge').hidden = !dirty || !state.canEdit;
    if (!state.canEdit) { saveBtn.hidden = true; return; }
    if (saving) return;
    const bad = state.items.some((i) => i.bad_sets || i.bad_reps);
    if (!state.items.length) {        // 5.2
      saveBtn.disabled = true;
      saveBtn.textContent = 'Añade al menos un ejercicio';
    } else if (bad) {
      saveBtn.disabled = true;
      saveBtn.textContent = 'Revisá series y repeticiones';
    } else {
      saveBtn.disabled = !dirty && !!state.id;
      saveBtn.textContent = state.id ? (dirty ? 'Guardar cambios' : 'Guardado') : 'Guardar rutina';
    }
  }
  nameIn.addEventListener('input', () => { MR.clearError(nameIn); updateSave(); });
  diaIn.addEventListener('change', updateSave);

  async function save() {
    if (!nameIn.value.trim()) {
      MR.fieldError(nameIn, 'Poné un nombre a la rutina');
      MR.shake(nameIn);
      nameIn.focus();
      return;
    }
    saving = true;
    MR.loading(saveBtn, true);
    const payload = {
      id: state.id || undefined,
      version: state.version,
      name: nameIn.value.trim(),
      dia: diaIn.value || null,
      ownerId: ownerIn && ownerIn.value ? +ownerIn.value : undefined,
      items: state.items.map((i) => ({ ej: i.ej, sets: i.sets, reps: i.reps })),
    };
    try {
      const res = await MR.api('Api/rutinas.php?action=save', { method: 'POST', body: payload });
      const wasNew = !state.id;
      state.id = res.id;
      state.version = res.version;
      saved = snapshot();
      if (wasNew) {
        history.replaceState(null, '', MR.url('Rutinas/Rutinas.php?id=' + res.id));
        $('editorTitle').textContent = payload.name;
        if (ownerIn) ownerIn.closest('.field').remove();
      }
      MR.toast(wasNew ? 'Rutina creada' : 'Cambios guardados', 'success');
    } catch (err) {
      if (err.code === 'conflict') {  // 5.3
        MR.modal({
          title: 'No se pudo guardar',
          body: 'Esta rutina fue modificada por otro usuario recientemente. Por favor, recarga la página.' + (err.data.updatedBy ? ' (Último cambio: ' + err.data.updatedBy + ')' : ''),
          dismissible: false,
          actions: [{ label: 'Recargar página', class: 'btn-primary', onClick: () => { saved = snapshot(); location.reload(); } }],
        });
      } else if (err.data && err.data.fields && err.data.fields.name) {
        MR.fieldError(nameIn, err.data.fields.name);
        MR.shake(nameIn);
      } else if (err.code !== 'reauth_cancelled') {
        MR.toast(err.code === 'network' ? 'Error de red. Los cambios no se guardaron.' : err.message, 'error');
      }
    } finally {
      saving = false;
      MR.loading(saveBtn, false);
      updateSave();
    }
  }
  saveBtn.addEventListener('click', save);
  window.addEventListener('beforeunload', (e) => {
    if (state.canEdit && isDirty() && (state.items.length || state.id)) { e.preventDefault(); e.returnValue = ''; }
  });

  /* ---------------- Selector de ejercicios ---------------- */
  function openPicker() {
    const search = el('input', { class: 'input', type: 'search', placeholder: 'Buscar ejercicio…', 'aria-label': 'Buscar ejercicio' });
    const typeSel = el('select', { class: 'select', 'aria-label': 'Tipo' },
      el('option', { value: '', text: 'Todos los tipos' }), el('option', { value: 'Libre', text: 'Libre' }), el('option', { value: 'Cable', text: 'Cable' }));
    const muSel = el('select', { class: 'select', 'aria-label': 'Músculo' }, el('option', { value: '', text: 'Todos los músculos' }));
    const list = el('div', { class: 'picker-list', role: 'list' });
    const body = el('div', {}, el('div', { class: 'row' }, search, muSel, typeSel), list);
    const m = MR.modal({ title: 'Agregar ejercicio', body, wide: true, actions: [{ label: 'Listo', class: 'btn-dark', onClick: (mm) => mm.close() }] });

    MR.api('Api/ejercicios.php?action=musculos').then((r) => {
      r.items.forEach((mu) => muSel.append(el('option', { value: String(mu.id), text: mu.name })));
    }).catch(() => {});

    let reqId = 0;
    async function refresh() {
      const my = ++reqId;
      list.replaceChildren(el('div', { class: 'spinner', style: 'margin:16px auto' }));
      try {
        const res = await MR.api('Api/ejercicios.php', { params: { action: 'list', q: search.value.trim(), mu: muSel.value, type: typeSel.value } });
        if (my !== reqId) return;
        if (!res.items.length) {
          list.replaceChildren(el('p', { class: 'muted', style: 'text-align:center;padding:16px', text: 'No encontramos ejercicios con ese nombre' }));
          return;
        }
        list.replaceChildren(...res.items.map((ex) => {
          const added = state.items.some((i) => i.ej === ex.id);
          const b = el('button', { type: 'button', class: 'picker-item', role: 'listitem', disabled: added },
            el('span', {}, el('strong', { style: 'font-weight:600', text: ex.name }), el('span', { class: 'xs muted', text: ' · ' + ex.muName + ' · ' + ex.type })),
            el('span', { class: 'badge' + (added ? '' : ' badge--accent'), text: added ? 'Ya está en la rutina' : '+ Agregar' }));
          b.addEventListener('click', () => {
            if (state.items.length >= 40) return MR.toast('Una rutina puede tener hasta 40 ejercicios.', 'warning');
            state.items.push({ ej: ex.id, name: ex.name, mu: ex.muName, type: ex.type, sets: 3, reps: 10 });
            render();
            b.disabled = true;
            b.lastChild.className = 'badge';
            b.lastChild.textContent = 'Agregado';
          });
          return b;
        }));
      } catch (err) {
        if (my === reqId) list.replaceChildren(MR.errorState('No pudimos cargar los ejercicios', refresh));
      }
    }
    search.addEventListener('input', MR.debounce(refresh, 250));
    muSel.addEventListener('change', refresh);
    typeSel.addEventListener('change', refresh);
    refresh();
    return m;
  }
  addBtn.addEventListener('click', openPicker);

  /* ---------------- Carga inicial ---------------- */
  async function load() {
    if (!state.id) {
      root.setAttribute('aria-busy', 'false');
      saved = snapshot();
      render();
      nameIn.focus();
      return;
    }
    canvas.replaceChildren(el('div', { class: 'skeleton skeleton-card', style: 'width:100%' }));
    try {
      const { work } = await MR.api('Api/rutinas.php?action=get&id=' + state.id);
      Object.assign(state, { version: work.version, canEdit: work.canEdit, owner: work.owner });
      state.items = work.items;
      nameIn.value = work.name;
      diaIn.value = work.dia ? String(work.dia) : '';
      $('editorTitle').textContent = work.name;
      document.title = work.name + ' · MaxRep';
      const meta = [];
      if (work.owner) meta.push('Rutina de ' + work.owner.name);
      if (work.updatedBy) meta.push('última edición: ' + work.updatedBy);
      $('editorMeta').textContent = meta.join(' · ');
      if (!work.canEdit) {
        nameIn.disabled = diaIn.disabled = true;
        addBtn.hidden = true;
        $('editorMeta').prepend(el('span', { class: 'badge', text: 'Solo lectura' }), ' ');
      }
      saved = snapshot();
      root.setAttribute('aria-busy', 'false');
      render();
    } catch (err) {
      root.setAttribute('aria-busy', 'false');
      canvas.classList.add('is-empty');
      canvas.replaceChildren(MR.errorState(err.status === 404 ? err.message : 'No pudimos cargar la rutina', err.status === 404 ? null : load));
      addBtn.hidden = true;
      saveBtn.hidden = true;
    }
  }
  load();
})();

/* CU-03 — Inicio: skeleton, widgets, estados vacíos y reintento */
(function () {
  'use strict';
  const root = document.getElementById('dashboard');
  const el = MR.el;

  function skeleton() {
    root.setAttribute('aria-busy', 'true');
    root.replaceChildren(el('div', { class: 'grid' },
      [1, 2, 3, 4].map(() => el('div', { class: 'card' },
        el('div', { class: 'skeleton skeleton-line', style: 'width:40%' }),
        el('div', { class: 'skeleton skeleton-line', style: 'width:70%;height:28px' }),
        el('div', { class: 'skeleton skeleton-line' }),
        el('div', { class: 'skeleton skeleton-line', style: 'width:85%' })))));
  }

  function card(title, body, href, extra) {
    const c = el(href ? 'a' : 'div', { class: 'card card--hover', href, style: 'text-decoration:none;display:block' + (extra || '') },
      el('p', { class: 'eyebrow', text: title }), body);
    return c;
  }

  function render(d) {
    root.setAttribute('aria-busy', 'false');
    const grid = el('div', { class: 'grid' });

    if (d.isNew) {
      // 3.2 — usuario nuevo sin métricas ni rutinas
      root.replaceChildren(
        el('div', { class: 'card' }, MR.empty({
          title: 'Todavía no tenés rutinas',
          text: 'Armá tu primera rutina para ver acá tu entrenamiento del día y tu progreso.',
          action: { label: 'Crear tu primera rutina', href: MR.url('Rutinas/ListaRutinas.php?nueva=1') },
        })),
        el('div', { class: 'grid section' },
          card('Marcas personales', el('div', {},
            el('p', { class: 'muted', text: 'Registrá el peso que levantás en cada ejercicio para seguir tu progreso.' }),
            el('span', { class: 'btn btn-sm', text: 'Registrar mi primera marca' })), MR.url('Ejercicios/Ejercicios.php')),
          peopleCard(d)));
      return;
    }

    // Rutina de hoy
    const tw = d.todayWork;
    grid.append(card('Rutina de hoy · ' + d.today.label, tw
      ? el('div', {},
          el('h3', { text: tw.name }),
          tw.items.length
            ? el('ul', { class: 'record-list' }, tw.items.slice(0, 5).map((i) => el('li', {}, el('span', { text: i.name }), el('span', { class: 'muted', text: i.sets + ' × ' + i.reps }))))
            : el('p', { class: 'muted', text: 'Esta rutina todavía no tiene ejercicios.' }),
          tw.items.length > 5 ? el('p', { class: 'xs muted', text: '+' + (tw.items.length - 5) + ' ejercicios más' }) : null)
      : el('div', {},
          el('h3', { text: 'Día libre' }),
          el('p', { class: 'muted', text: 'No tenés una rutina asignada para hoy. Podés asignarle un día a cualquier rutina.' })),
      tw ? MR.url('Rutinas/Rutinas.php?id=' + tw.id) : MR.url('Rutinas/ListaRutinas.php')));

    // Métricas
    grid.append(card('Actividad', el('div', { style: 'display:flex;gap:28px;flex-wrap:wrap' },
      el('div', {}, el('div', { class: 'stat', text: d.stats.rutinas }), el('span', { class: 'muted small', text: 'rutinas' })),
      el('div', {}, el('div', { class: 'stat', text: d.stats.marcas }), el('span', { class: 'muted small', text: 'marcas registradas' })),
      d.stats.compartidas ? el('div', {}, el('div', { class: 'stat', text: d.stats.compartidas }), el('span', { class: 'muted small', text: 'compartidas conmigo' })) : null)));

    // Resumen de rutinas
    grid.append(card('Mis rutinas', d.works.length
      ? el('ul', { class: 'record-list' }, d.works.map((w) => el('li', {},
          el('span', { text: w.name }), el('span', { class: 'muted', text: (w.dia ? w.dia + ' · ' : '') + w.n + ' ej.' }))))
      : MR.empty({ title: 'Sin rutinas propias', action: { label: 'Crear tu primera rutina', href: MR.url('Rutinas/ListaRutinas.php?nueva=1') } }),
      d.works.length ? MR.url('Rutinas/ListaRutinas.php') : null));

    // Últimas marcas
    grid.append(card('Últimas marcas', d.records.length
      ? el('ul', { class: 'record-list' }, d.records.map((r) => el('li', {},
          el('span', { text: r.name }),
          el('span', {},
            el('strong', { text: MR.fmtNum(r.peso) + ' kg' }),
            r.delta ? el('span', { class: r.delta > 0 ? 'delta-up' : 'delta-down', text: ' ' + (r.delta > 0 ? '+' : '') + MR.fmtNum(r.delta) }) : null,
            el('span', { class: 'muted xs', text: ' · ' + MR.fmtDate(r.fecha) })))))
      : el('p', { class: 'muted', text: 'Todavía no registraste marcas. Hacelo desde cada ejercicio.' }),
      MR.url('Ejercicios/Ejercicios.php')));

    grid.append(peopleCard(d));
    root.replaceChildren(grid);
  }

  function peopleCard(d) {
    if (d.user.role === 'profesor') {
      const a = d.alumnos;
      return card('Mis alumnos', a.total
        ? el('div', {}, el('div', { class: 'stat', text: a.total }),
            el('ul', { class: 'record-list' }, a.list.map((s) => el('li', {}, el('span', { text: s.name }), el('span', { class: 'muted', text: s.rutinas + ' rutinas' })))))
        : el('p', { class: 'muted', text: 'Aún no tenés alumnos vinculados. Compartiles tu código de profesor (lo ves en tu Perfil).' }),
        MR.url('Rutinas/ListaRutinas.php?tab=alumnos'));
    }
    return card('Profesor', d.profesor
      ? el('div', {}, el('h3', { text: d.profesor }), el('p', { class: 'muted small', text: 'Tu profesor puede ver y ajustar tus rutinas.' }))
      : el('div', {}, el('p', { class: 'muted', text: 'Conectate con tu profesor para que siga tus rutinas.' }), el('span', { class: 'btn btn-sm', text: 'Conectarse con profesor' })),
      d.profesor ? null : MR.url('Rutinas/ListaRutinas.php?conectar=1'));
  }

  async function load() {
    skeleton();
    try {
      render((await MR.api('Api/dashboard.php')));
    } catch (err) {
      // 3.1 — problemas de conexión
      root.setAttribute('aria-busy', 'false');
      root.replaceChildren(el('div', { class: 'card' },
        MR.errorState(err.code === 'network' ? 'Sin conexión con el servidor' : 'No pudimos cargar tu resumen', load)));
    }
  }
  load();
})();

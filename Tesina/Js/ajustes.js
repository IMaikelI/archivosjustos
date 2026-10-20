/* CU-07 — Ajustes: modo oscuro, contraseña, suscripción y soporte */
(function () {
  'use strict';
  const el = MR.el;
  const $ = (id) => document.getElementById(id);

  /* ---------------- Modo oscuro (paso 1) ---------------- */
  const dark = $('darkToggle');
  dark.addEventListener('change', async () => {
    const theme = dark.checked ? 'oscuro' : 'claro';
    MR.applyTheme(theme, true);
    try {
      await MR.api('Api/ajustes.php?action=theme', { method: 'POST', body: { theme } });
    } catch (err) {
      MR.toast('Se aplicó el tema en este navegador, pero no se pudo guardar en tu cuenta.', 'warning');
    }
  });

  /* ---------------- Cambiar contraseña (7.2) ---------------- */
  const passForm = $('passForm');
  const passBtn = $('passBtn');
  const pIn = { current: $('current'), password: $('newPass'), password2: $('newPass2') };
  const fillCheck = () => { passBtn.disabled = !Object.values(pIn).every((i) => i.value); };
  Object.values(pIn).forEach((i) => i.addEventListener('input', () => { MR.clearError(i); fillCheck(); }));
  const RULES = { length: '8 caracteres', upper: 'una mayúscula', lower: 'una minúscula', number: 'un número' };
  passForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (passBtn.disabled) return;
    MR.loading(passBtn, true);
    try {
      await MR.api('Api/ajustes.php?action=password', {
        method: 'POST', body: { current: pIn.current.value, password: pIn.password.value, password2: pIn.password2.value },
      });
      passForm.reset();
      MR.toast('Contraseña actualizada', 'success');
    } catch (err) {
      const f = pIn[err.data.field] || null;
      if (f) {
        const msg = err.code === 'weak_password' ? 'Falta: ' + (err.data.missing || []).map((k) => RULES[k]).join(', ') : err.message;
        MR.fieldError(f, msg);
        MR.shake(f);
        if (err.code === 'bad_current') f.value = '';
      } else if (err.code !== 'reauth_cancelled') {
        MR.toast(err.code === 'network' ? 'Error de red' : err.message, 'error');
      }
    } finally {
      MR.loading(passBtn, false);
      fillCheck(); // el botón vuelve a su estado inactivo si falta un dato
    }
  });

  /* ---------------- Suscripciones (pasos 2–4, 7.1, 7.3) ---------------- */
  const subsBody = $('subsBody');
  let subData = null;
  const money = (n, c) => Number(n).toLocaleString('es-AR', { style: 'currency', currency: c || 'ARS' });

  async function loadSubs() {
    try {
      subData = await MR.api('Api/ajustes.php?action=subscription');
      renderSubs();
    } catch (err) {
      subsBody.replaceChildren(MR.errorState('No pudimos cargar tu suscripción', loadSubs));
    }
  }

  function receiptEl(r) {
    return el('div', { class: 'receipt', role: 'status' },
      el('strong', {}, '✓ Pago aprobado — recibo digital'),
      el('dl', {},
        el('dt', { text: 'Referencia' }), el('dd', { text: r.reference }),
        el('dt', { text: 'Plan' }), el('dd', { text: r.plan }),
        el('dt', { text: 'Importe' }), el('dd', { text: money(r.amount, r.currency) }),
        el('dt', { text: 'Tarjeta' }), el('dd', { text: r.card }),
        el('dt', { text: 'Titular' }), el('dd', { text: r.holder }),
        el('dt', { text: 'Fecha' }), el('dd', { text: r.date })),
      r.mode === 'test' ? el('p', { class: 'xs muted', style: 'margin:8px 0 0', text: 'Modo de prueba: no se realizó ningún cobro real.' }) : null,
      el('button', { type: 'button', class: 'btn btn-sm', style: 'margin-top:10px', text: 'Imprimir recibo', onclick: () => window.print() }));
  }

  function renderSubs(receipt) {
    const s = subData;
    const nodes = [];
    if (s.subscription) {
      nodes.push(el('div', { class: 'setting-row' },
        el('div', {},
          el('p', { style: 'margin:0' }, el('span', { class: 'badge badge--success', text: 'Activa' }), ' ', el('strong', { text: s.subscription.plan })),
          el('p', { text: 'Desde ' + MR.fmtDate(s.subscription.startedAt) + ' · ' + money(s.plan.precio, s.plan.moneda) + ' por mes' })),
        el('button', { type: 'button', class: 'btn btn-ghost', text: 'Cancelar', onclick: confirmCancel })));
      if (receipt) nodes.push(receiptEl(receipt));
    } else {
      const buy = el('button', { type: 'button', class: 'btn btn-primary', text: 'Comprar' });
      nodes.push(el('div', { class: 'setting-row' },
        el('div', {}, el('strong', { text: s.plan.nombre }), el('p', { text: money(s.plan.precio, s.plan.moneda) + ' por mes. Sin suscripción activa.' })),
        buy));
      const slot = el('div');
      nodes.push(slot);
      buy.addEventListener('click', () => {
        buy.hidden = true;
        slot.replaceChildren(payForm(() => { buy.hidden = false; slot.replaceChildren(); }));
      });
    }
    if (s.payments.length) {
      nodes.push(el('details', { style: 'margin-top:16px' }, el('summary', { class: 'small', text: 'Historial de pagos' }),
        el('ul', { class: 'record-list' }, s.payments.map((p) => el('li', {},
          el('span', { text: MR.fmtDate(p.date) + ' · ' + p.card }),
          el('span', { class: p.status === 'aprobado' ? 'delta-up' : 'delta-down', text: p.status + ' · ' + money(p.amount, p.currency) }))))));
    }
    subsBody.replaceChildren(...nodes);
  }

  function payForm(onCancel) {
    const f = (id, label, attrs) => {
      const input = el('input', Object.assign({ class: 'input', id }, attrs));
      return { input, field: el('div', { class: 'field' }, el('label', { for: id, text: label }), input) };
    };
    const holder = f('cHolder', 'Titular de la tarjeta', { autocomplete: 'cc-name', maxlength: '80' });
    const number = f('cNumber', 'Número de tarjeta', { autocomplete: 'cc-number', inputmode: 'numeric', maxlength: '23', placeholder: '0000 0000 0000 0000' });
    const exp = f('cExp', 'Vencimiento', { autocomplete: 'cc-exp', inputmode: 'numeric', maxlength: '5', placeholder: 'MM/AA' });
    const cvc = f('cCvc', 'Código de seguridad', { autocomplete: 'cc-csc', inputmode: 'numeric', maxlength: '4', placeholder: '123' });
    const banner = el('div');
    const progress = el('div', { class: 'progress', hidden: true, role: 'progressbar', 'aria-label': 'Procesando pago', 'aria-valuemin': '0', 'aria-valuemax': '100' }, el('i'));
    const pay = el('button', { type: 'submit', class: 'btn btn-primary btn-press', text: 'Pagar ' + money(subData.plan.precio, subData.plan.moneda) });
    const form = el('form', { novalidate: true, class: 'reveal', style: 'margin-top:16px' },
      banner,
      subData.mode === 'test' ? el('div', { class: 'banner banner--info', html: MR.icon('info') + '<span><strong>Modo de prueba:</strong> no se realizan cobros reales. Usá 4242 4242 4242 4242 (aprobada) o 4000 0000 0000 0002 (rechazada), con cualquier fecha futura y CVC.</span>' }) : null,
      el('h4', { style: 'margin:0 0 12px', text: 'PONER DATOS' }),
      holder.field, number.field,
      el('div', { class: 'row' }, exp.field, cvc.field),
      progress,
      el('div', { class: 'actions-bar', style: 'margin-top:12px' }, pay, el('button', { type: 'button', class: 'btn btn-ghost', text: 'Cancelar', onclick: onCancel })));

    // Formato automático: espacios cada 4 dígitos y MM/AA
    number.input.addEventListener('input', () => {
      const pos = number.input.selectionStart;
      const before = number.input.value.slice(0, pos).replace(/\D/g, '').length;
      const digits = number.input.value.replace(/\D/g, '').slice(0, 19);
      number.input.value = digits.replace(/(\d{4})(?=\d)/g, '$1 ');
      let p = 0, seen = 0;
      while (p < number.input.value.length && seen < before) { if (/\d/.test(number.input.value[p])) seen++; p++; }
      number.input.setSelectionRange(p, p);
    });
    exp.input.addEventListener('input', (e) => {
      let d = exp.input.value.replace(/\D/g, '').slice(0, 4);
      if (d.length === 1 && d > '1') d = '0' + d;
      exp.input.value = d.length > 2 || (d.length === 2 && e.inputType !== 'deleteContentBackward') ? d.slice(0, 2) + '/' + d.slice(2) : d;
    });
    cvc.input.addEventListener('input', () => { cvc.input.value = cvc.input.value.replace(/\D/g, '').slice(0, 4); });
    [holder, number, exp, cvc].forEach((x) => x.input.addEventListener('input', () => MR.clearError(x.input)));

    let timer;
    function runProgress() {
      const bar = progress.firstChild;
      let v = 0;
      progress.hidden = false;
      bar.style.width = '0';
      timer = setInterval(() => {
        v = Math.min(90, v + 8 + Math.random() * 10);
        bar.style.width = v + '%';
        progress.setAttribute('aria-valuenow', String(Math.round(v)));
      }, 120);
      return () => { clearInterval(timer); bar.style.width = '100%'; setTimeout(() => { progress.hidden = true; }, 250); };
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      banner.replaceChildren();
      const map = { holder: holder.input, number: number.input, exp: exp.input, cvc: cvc.input };
      const empty = Object.values(map).filter((i) => !i.value.trim());
      if (empty.length) { empty.forEach((i) => MR.fieldError(i, 'Campo obligatorio')); MR.shake(form); return; }
      MR.loading(pay, true);
      const stop = runProgress();
      try {
        const res = await MR.api('Api/ajustes.php?action=subscribe', {
          method: 'POST', body: { holder: map.holder.value, number: map.number.value, exp: map.exp.value, cvc: map.cvc.value },
        });
        stop();
        await loadSubsSilent();
        renderSubs(res.receipt);
        MR.toast('¡Suscripción activada!', 'success');
      } catch (err) {
        stop();
        MR.loading(pay, false);
        if (err.code === 'declined') {        // 7.1
          banner.replaceChildren(el('div', { class: 'banner banner--error', role: 'alert', html: MR.icon('alert') + '<span>Pago rechazado. Verifique los datos</span>' }));
          MR.shake(form);
          loadSubsSilent().then(() => {});
        } else if (err.data && err.data.fields) {
          Object.entries(err.data.fields).forEach(([k, m]) => map[k] && MR.fieldError(map[k], m));
          MR.shake(form);
        } else if (err.code !== 'reauth_cancelled') {
          banner.replaceChildren(el('div', { class: 'banner banner--error', role: 'alert', html: MR.icon('alert') + '<span></span>' }));
          banner.querySelector('span').textContent = err.code === 'network' ? 'Error de red: el pago no se procesó.' : err.message;
          MR.shake(form);
        }
      }
    });
    setTimeout(() => holder.input.focus(), 50);
    return form;
  }

  async function loadSubsSilent() {
    try { subData = await MR.api('Api/ajustes.php?action=subscription'); } catch (e) { /* se mantiene el estado previo */ }
  }

  function confirmCancel() {
    const status = el('div', { class: 'banner banner--warning', hidden: true, style: 'align-items:center' }, el('div', { class: 'spinner', style: 'width:20px;height:20px' }), el('span', { text: 'Esperando reconexión para procesar baja' }));
    let waiting = false;
    const m = MR.modal({
      title: '¿Estás seguro?', danger: true,
      body: el('div', {}, el('p', { text: 'Vas a cancelar tu suscripción a ' + subData.plan.nombre + '. Perderás los beneficios al finalizar el período.' }), status),
      onClose: () => window.removeEventListener('online', onOnline),
      actions: [
        { label: 'Volver', class: 'btn-ghost', onClick: (mm) => mm.close() },
        { label: 'Sí, cancelar suscripción', class: 'btn-danger', id: 'confirmCancel', onClick: () => doCancel() },
      ],
    });
    const btn = m.el.querySelector('#confirmCancel');
    function setOffline() {   // 7.3
      waiting = true;
      status.hidden = false;
      btn.disabled = true;
    }
    function onOnline() { if (waiting) { waiting = false; status.hidden = true; doCancel(); } }
    window.addEventListener('online', onOnline);
    async function doCancel() {
      if (navigator.onLine === false) return setOffline();
      MR.loading(btn, true);
      try {
        await MR.api('Api/ajustes.php?action=cancel', { method: 'POST', body: {} });
        m.close();
        await loadSubsSilent();
        renderSubs();
        MR.toast('Suscripción cancelada', 'success');
      } catch (err) {
        MR.loading(btn, false);
        if (err.code === 'network') setOffline();
        else if (err.code !== 'reauth_cancelled') MR.toast(err.message, 'error');
      }
    }
  }

  /* ---------------- Ayuda y soporte (paso 5) ---------------- */
  const FAQ = [
    ['¿Cómo me vinculo con mi profesor?', 'En Rutinas, tocá “Conectarse con profesor” e ingresá el código que te pasó. Tu profesor lo ve en su Perfil.'],
    ['¿Cómo comparto una rutina?', 'En la tarjeta de la rutina tocá “Compartir”. Se genera un código que vence en 30 minutos y sirve una sola vez.'],
    ['¿Qué fórmula usa la calculadora RM?', 'Podés elegir entre Brzycki, Epley, Lander y Lombardi. Todas son estimaciones; con muchas repeticiones pierden precisión.'],
    ['¿Cómo registro una marca personal?', 'Abrí un ejercicio en la sección Ejercicios y completá peso y fecha en “Mis marcas”.'],
    ['¿Puedo usar MaxRep en el celular?', 'Sí. La interfaz se adapta a pantallas chicas; el menú se abre con el botón de la barra superior.'],
  ];
  $('helpBtn').addEventListener('click', () => {
    const asunto = el('input', { class: 'input', id: 'sAsunto', maxlength: '120' });
    const mensaje = el('textarea', { class: 'textarea', id: 'sMensaje', maxlength: '4000' });
    const send = el('button', { type: 'submit', class: 'btn btn-primary btn-block', text: 'Enviar consulta' });
    const form = el('form', { novalidate: true },
      el('h3', { style: 'margin-top:20px', text: 'Contacto' }),
      el('div', { class: 'field' }, el('label', { for: 'sAsunto', text: 'Asunto' }), asunto),
      el('div', { class: 'field' }, el('label', { for: 'sMensaje', text: 'Mensaje' }), mensaje),
      send);
    [asunto, mensaje].forEach((i) => i.addEventListener('input', () => MR.clearError(i)));
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      MR.loading(send, true);
      try {
        const res = await MR.api('Api/ajustes.php?action=support', { method: 'POST', body: { asunto: asunto.value, mensaje: mensaje.value } });
        form.replaceChildren(el('div', { class: 'banner banner--info', role: 'status', style: 'margin-top:20px' },
          el('span', { text: 'Recibimos tu consulta (n.º ' + res.ticket + '). Te vamos a responder por correo.' })));
      } catch (err) {
        MR.loading(send, false);
        if (err.data && err.data.fields) {
          Object.entries(err.data.fields).forEach(([k, msg]) => { const i = k === 'asunto' ? asunto : mensaje; MR.fieldError(i, msg); MR.shake(i); });
        } else if (err.code !== 'reauth_cancelled') MR.toast(err.code === 'network' ? 'Error de red' : err.message, 'error');
      }
    });
    MR.modal({
      title: 'Ayuda y soporte', drawer: true, focus: 'summary',
      body: el('div', {}, el('div', { class: 'faq' }, FAQ.map(([qq, a]) => el('details', {}, el('summary', { text: qq }), el('p', { text: a })))), form),
    });
  });

  loadSubs();
})();

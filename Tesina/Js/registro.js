/* CU-01 — Registro de Usuario o Profesor */
(function () {
  'use strict';
  const cards = document.querySelectorAll('.role-card');
  const form = document.getElementById('registerForm');
  const btn = document.getElementById('registerBtn');
  const rules = document.getElementById('pwRules');
  const pass = document.getElementById('password');
  const badge = document.getElementById('roleBadge');
  const LABEL = { usuario: 'Usuario', profesor: 'Profesor' };
  let role = '';

  function selectRole(card) {
    role = card.dataset.role;
    cards.forEach((c) => c.setAttribute('aria-checked', String(c === card)));
    badge.textContent = LABEL[role];
    if (form.hidden) {
      form.hidden = false;
      form.classList.remove('reveal');
      void form.offsetWidth;
      form.classList.add('reveal');
      setTimeout(() => document.getElementById('name').focus(), 50);
    }
  }
  cards.forEach((card) => {
    card.addEventListener('click', () => selectRole(card));
    card.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight' || e.key === 'ArrowDown' || e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
        e.preventDefault();
        const list = [...cards];
        const next = list[(list.indexOf(card) + (e.key === 'ArrowRight' || e.key === 'ArrowDown' ? 1 : list.length - 1)) % list.length];
        next.focus();
        selectRole(next);
      }
    });
  });

  function checkPassword(value) {
    const missing = [];
    if (value.length < 8) missing.push('length');
    if (!/\p{Lu}/u.test(value)) missing.push('upper');
    if (!/\p{Ll}/u.test(value)) missing.push('lower');
    if (!/\d/.test(value)) missing.push('number');
    return missing;
  }
  function renderRules(missing) {
    rules.hidden = false;
    rules.querySelectorAll('li').forEach((li, i) => {
      const bad = missing.includes(li.dataset.rule);
      li.className = bad ? 'missing' : 'ok';
      li.style.animationDelay = i * 60 + 'ms';
    });
  }
  pass.addEventListener('input', () => {
    if (!rules.hidden) {
      const missing = checkPassword(pass.value);
      renderRules(missing);
      if (!missing.length) MR.clearError(pass);
    }
  });
  form.querySelectorAll('.input').forEach((i) => i.addEventListener('input', () => {
    if (i !== pass) MR.clearError(i);
    i.closest('.field').classList.remove('is-missing');
  }));

  function flagMissing(inputs) {
    inputs.forEach((i) => {
      i.closest('.field').classList.add('is-missing');
      i.classList.add('is-invalid');
    });
    const first = inputs[0];
    first.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => { MR.shake(first); first.focus({ preventScroll: true }); }, 250);
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    btn.classList.remove('is-failed');
    const data = Object.fromEntries(new FormData(form).entries());
    data.role = role;

    // 1.3 — campos obligatorios vacíos
    const empty = [...form.querySelectorAll('.input[required]')].filter((i) => !i.value.trim());
    if (empty.length) return flagMissing(empty);

    const email = document.getElementById('email');
    if (!email.checkValidity()) { MR.fieldError(email, 'Ingresá un correo válido', { tooltip: true }); return MR.shake(email); }

    // 1.2 — contraseña sin requisitos mínimos
    const missing = checkPassword(pass.value);
    if (missing.length) {
      MR.fieldError(pass, '');
      renderRules(missing);
      return MR.shake(pass);
    }
    const p2 = document.getElementById('password2');
    if (p2.value !== pass.value) { MR.fieldError(p2, 'Las contraseñas no coinciden'); return MR.shake(p2); }

    MR.loading(btn, true);
    try {
      const res = await MR.api('Api/auth.php?action=register', { method: 'POST', body: data });
      location.href = res.redirect;
    } catch (err) {
      MR.loading(btn, false);
      if (err.code === 'network') {               // 1.5 — sin conexión
        btn.classList.add('is-failed');
        MR.toast('Error de red', 'error');
      } else if (err.code === 'email_taken') {    // 1.1 — correo existente
        MR.fieldError(email, 'Este correo ya está en uso', { tooltip: true });
        email.focus();
      } else if (err.code === 'missing') {
        flagMissing((err.data.fields || []).map((f) => document.getElementById(f)).filter(Boolean));
      } else if (err.code === 'weak_password') {
        MR.fieldError(pass, '');
        renderRules(err.data.missing || []);
        MR.shake(pass);
      } else if (err.data.field && document.getElementById(err.data.field)) {
        const f = document.getElementById(err.data.field);
        MR.fieldError(f, err.message);
        MR.shake(f);
      } else {
        MR.toast(err.message, 'error');
      }
    }
  });
})();

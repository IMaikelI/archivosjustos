/* Inicio de sesión */
(function () {
  'use strict';
  const form = document.getElementById('loginForm');
  const btn = document.getElementById('loginBtn');
  const box = document.getElementById('loginError');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    box.hidden = true;
    const user = document.getElementById('user');
    const pass = document.getElementById('password');
    const empty = [user, pass].filter((i) => !i.value.trim());
    if (empty.length) { empty.forEach((i) => MR.fieldError(i, 'Campo obligatorio')); return MR.shake(empty[0]); }
    MR.loading(btn, true);
    try {
      const res = await MR.api('Api/auth.php?action=login', {
        method: 'POST', body: { user: user.value, password: pass.value, next: form.dataset.next },
      });
      location.href = res.redirect;
    } catch (err) {
      MR.loading(btn, false);
      box.textContent = err.code === 'network' ? 'Error de red. Revisá tu conexión.' : err.message;
      box.hidden = false;
      MR.shake(form);
    }
  });
  form.querySelectorAll('.input').forEach((i) => i.addEventListener('input', () => MR.clearError(i)));
})();

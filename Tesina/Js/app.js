/* MaxRep — utilidades compartidas por todas las páginas (window.MR). */
(function () {
  'use strict';

  const meta = (n) => (document.querySelector('meta[name="' + n + '"]') || {}).content || '';
  const BASE = meta('base-url');
  const MR = (window.MR = {});

  MR.url = (p) => BASE + '/' + String(p).replace(/^\//, '');
  MR.csrf = () => meta('csrf-token');

  /* ------------------------------------------------------------------ *
   * Creación segura de elementos (nunca innerHTML con datos)
   * ------------------------------------------------------------------ */
  MR.el = function (tag, attrs, ...children) {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs || {})) {
      if (v === null || v === undefined || v === false) continue;
      if (k === 'class') node.className = v;
      else if (k === 'text') node.textContent = v;
      else if (k === 'html') node.innerHTML = v; // solo para SVG/markup estático propio
      else if (k.startsWith('on') && typeof v === 'function') node.addEventListener(k.slice(2), v);
      else if (k === 'dataset') Object.assign(node.dataset, v);
      else node.setAttribute(k, v === true ? '' : v);
    }
    for (const c of children.flat()) {
      if (c === null || c === undefined || c === false) continue;
      node.append(c instanceof Node ? c : document.createTextNode(String(c)));
    }
    return node;
  };

  /* Íconos (mismo set que layout.php) */
  const ICONS = {
    check: '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    alert: '<path d="M12 3 2 20h20L12 3z"/><path d="M12 10v4M12 17.5v.5"/>',
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5v.5"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    grip: '<circle cx="9" cy="6" r="1.3"/><circle cx="15" cy="6" r="1.3"/><circle cx="9" cy="12" r="1.3"/><circle cx="15" cy="12" r="1.3"/><circle cx="9" cy="18" r="1.3"/><circle cx="15" cy="18" r="1.3"/>',
    up: '<path d="m6 15 6-6 6 6"/>',
    down: '<path d="m6 9 6 6 6-6"/>',
    trash: '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
    clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    refresh: '<path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/>',
    image: '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>',
    lock: '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/>',
    edit: '<path d="M4 20h4L19 9l-4-4L4 16v4z"/><path d="m13.5 6.5 4 4"/>',
    share: '<circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="m8.2 10.8 7.6-4.4M8.2 13.2l7.6 4.4"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
  };
  MR.icon = function (name, cls) {
    return '<svg' + (cls ? ' class="' + cls + '"' : '') + ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICONS[name] || '') + '</svg>';
  };
  MR.iconEl = (name, cls) => {
    const t = document.createElement('template');
    t.innerHTML = MR.icon(name, cls);
    return t.content.firstChild;
  };

  /* ------------------------------------------------------------------ *
   * API
   * ------------------------------------------------------------------ */
  class ApiError extends Error {
    constructor(message, status, data) {
      super(message);
      this.status = status;
      this.data = data || {};
      this.code = this.data.code || (status === 0 ? 'network' : '');
    }
  }
  MR.ApiError = ApiError;

  MR.api = async function (path, options) {
    const { method = 'GET', body, form, params, reauth = true } = options || {};
    let url = MR.url(path);
    if (params) url += (url.includes('?') ? '&' : '?') + new URLSearchParams(params).toString();
    const init = { method, headers: { Accept: 'application/json' }, credentials: 'same-origin' };
    if (method !== 'GET') init.headers['X-CSRF-Token'] = MR.csrf();
    if (form) init.body = form;
    else if (body !== undefined) {
      init.headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(body);
    }
    if (navigator.onLine === false) throw new ApiError('Sin conexión a internet', 0, { code: 'network' });

    let res;
    try {
      res = await fetch(url, init);
    } catch (e) {
      throw new ApiError('Error de red', 0, { code: 'network' });
    }
    let data = null;
    try {
      data = await res.json();
    } catch (e) {
      data = null;
    }
    if (res.status === 401 && data && data.code === 'session_expired' && reauth) {
      await MR.reauth();
      return MR.api(path, Object.assign({}, options, { reauth: false }));
    }
    if (res.status === 401 && data && data.code === 'unauthenticated') {
      location.href = MR.url('Cuenta/login.php') + '?next=' + encodeURIComponent(location.pathname + location.search);
      throw new ApiError(data.error, 401, data);
    }
    if (!res.ok || !data || data.ok === false) {
      throw new ApiError((data && data.error) || 'Error del servidor (' + res.status + ')', res.status, data);
    }
    return data;
  };

  /* ------------------------------------------------------------------ *
   * Feedback visual
   * ------------------------------------------------------------------ */
  MR.toast = function (message, type, ms) {
    const box = document.getElementById('toasts');
    if (!box) return;
    const ic = { success: 'check', error: 'alert', warning: 'alert' }[type] || 'info';
    const t = MR.el('div', { class: 'toast toast--' + (type || 'info'), role: type === 'error' ? 'alert' : 'status' });
    t.append(MR.iconEl(ic), MR.el('span', { text: message }));
    box.append(t);
    setTimeout(() => {
      t.classList.add('is-leaving');
      t.addEventListener('animationend', () => t.remove(), { once: true });
    }, ms || 3800);
  };

  MR.shake = function (el) {
    if (!el) return;
    el.classList.remove('shake');
    void el.offsetWidth; // reinicia la animación
    el.classList.add('shake');
    el.addEventListener('animationend', () => el.classList.remove('shake'), { once: true });
  };

  MR.blink = function (el) {
    if (!el) return;
    el.classList.remove('blink');
    void el.offsetWidth;
    el.classList.add('blink');
    el.addEventListener('animationend', () => el.classList.remove('blink'), { once: true });
  };

  /** Marca un campo con error. opts.tooltip = globo debajo del campo. */
  MR.fieldError = function (input, message, opts) {
    MR.clearError(input);
    input.classList.add('is-invalid');
    input.setAttribute('aria-invalid', 'true');
    if (message) {
      const id = (input.id || 'f' + Math.random().toString(36).slice(2)) + '-err';
      const msg = MR.el('div', { class: opts && opts.tooltip ? 'tooltip-error' : 'field-error', id, role: 'alert', text: message });
      input.setAttribute('aria-describedby', id);
      (input.closest('.field') || input.parentNode).append(msg);
    }
  };
  MR.clearError = function (input) {
    input.classList.remove('is-invalid');
    input.removeAttribute('aria-invalid');
    const f = input.closest('.field') || input.parentNode;
    f.querySelectorAll('.field-error, .tooltip-error').forEach((n) => n.remove());
    f.classList && f.classList.remove('is-missing');
  };

  MR.loading = function (btn, on) {
    if (!btn) return;
    btn.classList.toggle('is-loading', !!on);
    btn.disabled = !!on;
    btn.setAttribute('aria-busy', on ? 'true' : 'false');
  };

  MR.debounce = function (fn, ms) {
    let t;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), ms);
    };
  };

  /* ------------------------------------------------------------------ *
   * Modales y drawer
   * ------------------------------------------------------------------ */
  MR.modal = function (opts) {
    const o = Object.assign({ dismissible: true }, opts);
    const prevFocus = document.activeElement;
    const backdrop = MR.el('div', { class: 'backdrop' + (o.drawer ? ' drawer-backdrop' : '') });
    const titleId = 'mt-' + Math.random().toString(36).slice(2);
    const box = MR.el('div', {
      class: (o.drawer ? 'drawer' : 'modal') + (o.danger ? ' modal--danger' : '') + (o.wide ? ' modal--wide' : ''),
      role: o.danger ? 'alertdialog' : 'dialog', 'aria-modal': 'true', 'aria-labelledby': titleId,
    });
    const head = MR.el('div', { class: 'modal__head' }, MR.el('h2', { id: titleId, text: o.title || '' }));
    let closeBtn = null;
    if (o.dismissible) {
      closeBtn = MR.el('button', { type: 'button', class: 'btn btn-ghost btn-icon', 'aria-label': 'Cerrar' });
      closeBtn.append(MR.iconEl('x'));
      head.append(closeBtn);
    }
    const body = MR.el('div', { class: 'modal__body' });
    if (o.body instanceof Node) body.append(o.body);
    else if (o.body) body.append(MR.el('p', { text: o.body }));
    box.append(head, body);
    if (o.actions && o.actions.length) {
      const bar = MR.el('div', { class: 'modal__actions' });
      o.actions.forEach((a) => {
        const b = MR.el('button', { type: 'button', class: 'btn ' + (a.class || ''), text: a.label });
        b.addEventListener('click', () => a.onClick && a.onClick(api, b));
        if (a.id) b.id = a.id;
        bar.append(b);
      });
      box.append(bar);
    }
    backdrop.append(box);

    function onKey(e) {
      if (e.key === 'Escape' && o.dismissible) api.close();
      if (e.key === 'Tab') {
        const f = [...box.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')].filter((n) => !n.disabled && n.offsetParent !== null);
        if (!f.length) return;
        if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
        else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
      }
    }
    const api = {
      el: box,
      body,
      closed: false,
      close() {
        if (api.closed) return;
        api.closed = true;
        document.removeEventListener('keydown', onKey);
        backdrop.remove();
        document.body.style.overflow = '';
        if (prevFocus && prevFocus.focus) prevFocus.focus();
        o.onClose && o.onClose();
      },
    };
    if (closeBtn) closeBtn.addEventListener('click', api.close);
    if (o.dismissible) backdrop.addEventListener('mousedown', (e) => { if (e.target === backdrop) api.close(); });
    document.addEventListener('keydown', onKey);
    document.body.append(backdrop);
    document.body.style.overflow = 'hidden';
    const first = box.querySelector(o.focus || 'input, textarea, select, .modal__actions .btn, button');
    if (first) setTimeout(() => first.focus(), 30);
    return api;
  };

  /* ------------------------------------------------------------------ *
   * Reautenticación por sesión expirada (CU-02, 2.3)
   * ------------------------------------------------------------------ */
  let reauthPromise = null;
  MR.reauth = function () {
    if (reauthPromise) return reauthPromise;
    reauthPromise = new Promise((resolve, reject) => {
      const input = MR.el('input', { class: 'input', type: 'password', id: 'reauth-pass', autocomplete: 'current-password', required: true });
      const body = MR.el('form', { novalidate: true },
        MR.el('p', { text: 'Por seguridad, tu sesión expiró. Ingresá tu contraseña para guardar los cambios.' }),
        MR.el('div', { class: 'field' }, MR.el('label', { for: 'reauth-pass', text: 'Contraseña' }), input));
      let done = false;
      const m = MR.modal({
        title: 'Sesión expirada',
        body,
        dismissible: true,
        onClose() { reauthPromise = null; if (!done) reject(new ApiError('Reautenticación cancelada', 401, { code: 'reauth_cancelled' })); },
        actions: [
          { label: 'Cancelar', class: 'btn-ghost', onClick: (mm) => mm.close() },
          { label: 'Continuar', class: 'btn-primary', id: 'reauth-ok', onClick: () => submit() },
        ],
      });
      body.addEventListener('submit', (e) => { e.preventDefault(); submit(); });
      async function submit() {
        const btn = m.el.querySelector('#reauth-ok');
        if (!input.value) { MR.fieldError(input, 'Ingresá tu contraseña'); MR.shake(input); return; }
        MR.loading(btn, true);
        try {
          await MR.api('Api/auth.php?action=reauth', { method: 'POST', body: { password: input.value }, reauth: false });
          done = true;
          m.close();
          resolve();
        } catch (err) {
          MR.loading(btn, false);
          if (err.code === 'unauthenticated') return;
          MR.fieldError(input, err.message);
          MR.shake(input);
        }
      }
    });
    return reauthPromise;
  };

  /* ------------------------------------------------------------------ *
   * Estados vacíos y de error
   * ------------------------------------------------------------------ */
  const EMPTY_ART = '<svg class="empty__art" viewBox="0 0 120 120" aria-hidden="true"><circle cx="60" cy="60" r="56" fill="var(--surface-2)"/><rect x="26" y="54" width="10" height="12" rx="2" fill="var(--mr-red)"/><rect x="84" y="54" width="10" height="12" rx="2" fill="var(--mr-red)"/><rect x="36" y="46" width="8" height="28" rx="2" fill="var(--text-3)"/><rect x="76" y="46" width="8" height="28" rx="2" fill="var(--text-3)"/><rect x="44" y="57" width="32" height="6" rx="3" fill="var(--text-3)"/><path d="M42 92h36" stroke="var(--border-strong)" stroke-width="3" stroke-linecap="round"/></svg>';

  MR.empty = function ({ title, text, action }) {
    const box = MR.el('div', { class: 'empty' });
    box.insertAdjacentHTML('beforeend', EMPTY_ART);
    if (title) box.append(MR.el('h3', { text: title }));
    if (text) box.append(MR.el('p', { text }));
    if (action) {
      const a = MR.el(action.href ? 'a' : 'button', { class: 'btn btn-primary', href: action.href, type: action.href ? null : 'button', text: action.label });
      if (action.onClick) a.addEventListener('click', action.onClick);
      box.append(a);
    }
    return box;
  };

  MR.errorState = function (message, onRetry) {
    const box = MR.el('div', { class: 'empty', role: 'alert' });
    const ic = MR.iconEl('alert');
    ic.setAttribute('width', '56');
    ic.setAttribute('height', '56');
    ic.style.color = 'var(--text-3)';
    box.append(ic, MR.el('h3', { text: message || 'No pudimos cargar la información' }));
    if (onRetry) {
      const b = MR.el('button', { type: 'button', class: 'btn btn-dark' });
      b.append(MR.iconEl('refresh'), document.createTextNode('Reintentar conexión'));
      b.addEventListener('click', onRetry);
      box.append(b);
    }
    return box;
  };

  /* ------------------------------------------------------------------ *
   * Formato
   * ------------------------------------------------------------------ */
  MR.fmtDate = function (iso) {
    if (!iso) return '';
    const [y, m, d] = String(iso).slice(0, 10).split('-');
    return d + '/' + m + '/' + y;
  };
  MR.fmtNum = (n) => Number(n).toLocaleString('es-AR', { maximumFractionDigits: 2 });

  /* ------------------------------------------------------------------ *
   * Tema
   * ------------------------------------------------------------------ */
  MR.applyTheme = function (theme, animate) {
    const root = document.documentElement;
    if (animate) {
      root.classList.add('theme-anim');
      setTimeout(() => root.classList.remove('theme-anim'), 350);
    }
    root.dataset.theme = theme === 'oscuro' ? 'oscuro' : 'claro';
    try { localStorage.setItem('mr-theme', root.dataset.theme); } catch (e) { /* almacenamiento no disponible */ }
  };
  try { localStorage.setItem('mr-theme', document.documentElement.dataset.theme); } catch (e) { /* ignorar */ }

  /* ------------------------------------------------------------------ *
   * Menú lateral en móvil
   * ------------------------------------------------------------------ */
  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('menuBtn');
    const side = document.getElementById('sidebar');
    if (!btn || !side) return;
    let scrim = null;
    const close = () => {
      side.classList.remove('is-open');
      btn.setAttribute('aria-expanded', 'false');
      scrim && scrim.remove();
      scrim = null;
    };
    btn.addEventListener('click', () => {
      if (side.classList.contains('is-open')) return close();
      side.classList.add('is-open');
      btn.setAttribute('aria-expanded', 'true');
      scrim = MR.el('div', { class: 'sidebar-scrim' });
      scrim.addEventListener('click', close);
      document.body.append(scrim);
    });
    document.addEventListener('keydown', (e) => e.key === 'Escape' && close());
  });
})();

/* CU-02 — Perfil: modo lectura/edición sin recargar la página */
(function () {
  'use strict';
  const form = document.getElementById('profileForm');
  const editBtn = document.getElementById('editBtn');
  const saveBtn = document.getElementById('saveBtn');
  const cancelBtn = document.getElementById('cancelBtn');
  const photo = document.getElementById('photo');
  const photoLabel = document.getElementById('photoLabel');
  const photoHint = document.getElementById('photoHint');
  const avatarBox = document.getElementById('avatarBox');
  const avatarImg = document.getElementById('avatarImg');
  const fields = [...form.querySelectorAll('.input:not([data-readonly]), .textarea')];
  const $ = (id) => document.getElementById(id); // form.name es el atributo del form, no el input
  const MAX = 5 * 1024 * 1024;
  const TYPES = ['image/jpeg', 'image/png', 'image/webp'];
  let snapshot = {};
  let savedPhoto = avatarImg.src;
  let photoOk = true;

  function setEditing(on) {
    fields.forEach((f) => { f.disabled = !on; MR.clearError(f); });
    editBtn.hidden = on;
    saveBtn.hidden = cancelBtn.hidden = photoLabel.hidden = photoHint.hidden = !on;
    if (on) {
      snapshot = Object.fromEntries(fields.map((f) => [f.id, f.value]));
      fields[0].focus();
    }
  }
  function resetPhoto() {
    photo.value = '';
    photoOk = true;
    avatarImg.src = savedPhoto;
    avatarBox.classList.remove('is-rejected');
    const b = avatarBox.querySelector('.avatar-box__bubble');
    if (b) b.remove();
  }
  function rejectPhoto(msg) {
    photoOk = false;
    avatarBox.classList.add('is-rejected');
    if (!avatarBox.querySelector('.avatar-box__bubble')) {
      avatarBox.append(MR.el('span', { class: 'avatar-box__bubble', role: 'alert', text: msg || 'Formato inválido o excede 5MB' }));
    }
  }

  editBtn.addEventListener('click', () => setEditing(true));
  cancelBtn.addEventListener('click', () => {
    fields.forEach((f) => { f.value = snapshot[f.id]; });
    resetPhoto();
    setEditing(false);
  });

  // 2.2 — foto mayor a 5MB o formato no admitido
  photo.addEventListener('change', () => {
    const file = photo.files[0];
    const b = avatarBox.querySelector('.avatar-box__bubble');
    if (b) b.remove();
    avatarBox.classList.remove('is-rejected');
    if (!file) { // selección cancelada: vuelve a la foto guardada y no bloquea el guardado
      photoOk = true;
      avatarImg.src = savedPhoto;
      return;
    }
    if (file.size > MAX || !TYPES.includes(file.type)) {
      avatarImg.src = savedPhoto;
      return rejectPhoto();
    }
    photoOk = true;
    avatarImg.src = URL.createObjectURL(file);
  });

  function validate() {
    const errors = {};
    const name = $('name').value.trim();
    if (!name) errors.name = 'El nombre es obligatorio';
    const peso = $('peso').value.trim().replace(',', '.');
    if (peso && (!/^\d+(\.\d{1,2})?$/.test(peso) || +peso < 20 || +peso > 400)) errors.peso = 'Ingresá un peso válido en kg (20 a 400)';
    const alt = $('altura').value.trim();
    if (alt && (!/^\d+$/.test(alt) || +alt < 80 || +alt > 260)) errors.altura = 'Ingresá una altura válida en cm (80 a 260)';
    return errors;
  }
  function showErrors(errors) {
    let first = null;
    for (const [k, msg] of Object.entries(errors)) {
      if (k === 'photo') { rejectPhoto(msg); continue; }
      const f = document.getElementById(k);
      if (!f) continue;
      MR.fieldError(f, msg);
      MR.shake(f);
      first = first || f;
    }
    if (first) first.focus();
  }
  fields.forEach((f) => f.addEventListener('input', () => MR.clearError(f)));

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (editBtn.hidden === false) return;
    fields.forEach((f) => MR.clearError(f));
    const errors = validate();
    if (!photoOk) errors.photo = 'Formato inválido o excede 5MB';
    if (Object.keys(errors).length) return showErrors(errors); // 2.1

    const fd = new FormData();
    fields.forEach((f) => fd.append(f.name, f.value));
    if (photo.files[0]) fd.append('photo', photo.files[0]);
    MR.loading(saveBtn, true);
    try {
      const res = await MR.api('Api/perfil.php?action=update', { method: 'POST', form: fd }); // 2.3 reautentica si expira
      const p = res.profile;
      $('name').value = p.name;
      $('peso').value = p.peso ?? '';
      $('altura').value = p.altura ?? '';
      $('objetivos').value = p.objetivos;
      savedPhoto = p.photo;
      resetPhoto();
      document.querySelectorAll('[data-user-name]').forEach((n) => { n.textContent = p.name; });
      document.querySelectorAll('[data-user-photo]').forEach((n) => { n.src = p.photo; });
      setEditing(false);
      MR.toast('Perfil actualizado', 'success');
    } catch (err) {
      if (err.data && err.data.fields) showErrors(err.data.fields);
      else if (err.code !== 'reauth_cancelled') MR.toast(err.code === 'network' ? 'Error de red. Tus cambios no se guardaron.' : err.message, 'error');
    } finally {
      MR.loading(saveBtn, false);
    }
  });

  document.querySelectorAll('[data-copy]').forEach((b) => b.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(b.dataset.copy); MR.toast('Código copiado', 'success'); }
    catch (e) { MR.toast('No se pudo copiar. Código: ' + b.dataset.copy); }
  }));
})();

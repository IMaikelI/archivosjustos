<?php
/** CU-02: Gestión de perfil. */
require __DIR__ . '/../layout.php';
$u = require_page_auth();
page_start('Perfil', 'perfil');
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Tu cuenta</p>
        <h1>Perfil</h1>
        <p>Tus datos personales y de entrenamiento.</p>
    </div>
    <div class="actions-bar">
        <button type="button" class="btn btn-dark" id="editBtn"><?= icon('edit') ?>EDITAR PERFIL</button>
        <button type="button" class="btn btn-ghost" id="cancelBtn" hidden>Cancelar</button>
        <button type="submit" form="profileForm" class="btn btn-primary" id="saveBtn" hidden><?= icon('check') ?>Guardar Cambios</button>
    </div>
</div>

<div class="profile">
    <div class="card avatar-box">
        <div class="avatar-box__img" id="avatarBox">
            <img src="<?= e(photo_url($u['Photo'])) ?>" alt="Foto de perfil" id="avatarImg">
        </div>
        <label class="btn btn-sm" id="photoLabel" hidden>
            <?= icon('image') ?>Cambiar foto
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" form="profileForm" class="sr-only">
        </label>
        <p class="xs muted" id="photoHint" hidden>JPG, PNG o WEBP · máximo 5MB</p>
        <p class="badge badge--accent" style="margin-top:8px"><?= e(ucfirst($u['Role'])) ?></p>
    </div>

    <div style="display:grid;gap:16px">
        <form class="card profile-form" id="profileForm" novalidate aria-live="polite">
            <div class="row">
                <div class="field">
                    <label for="name">Nombre</label>
                    <input class="input" id="name" name="name" maxlength="80" disabled value="<?= e($u['Name']) ?>">
                </div>
                <div class="field">
                    <label for="email">Correo</label>
                    <input class="input" id="email" value="<?= e($u['Email']) ?>" disabled data-readonly>
                </div>
            </div>
            <div class="row">
                <div class="field">
                    <label for="peso">Peso (kg)</label>
                    <input class="input" id="peso" name="peso" inputmode="decimal" disabled placeholder="—" value="<?= e($u['Peso'] !== null ? rtrim(rtrim((string) $u['Peso'], '0'), '.') : '') ?>">
                </div>
                <div class="field">
                    <label for="altura">Altura (cm)</label>
                    <input class="input" id="altura" name="altura" inputmode="numeric" disabled placeholder="—" value="<?= e($u['Altura'] ?? '') ?>">
                </div>
            </div>
            <div class="field" style="margin-bottom:0">
                <label for="objetivos">Objetivos</label>
                <textarea class="textarea" id="objetivos" name="objetivos" maxlength="500" disabled placeholder="Contá qué querés lograr"><?= e($u['Objetivos'] ?? '') ?></textarea>
            </div>
        </form>

        <div class="card" id="linkCard">
            <?php if ($u['Role'] === 'profesor'): ?>
                <h3>Tu código de profesor</h3>
                <p class="muted small">Compartilo con tus alumnos para que se vinculen desde la sección Rutinas.</p>
                <div class="code-box"><code><?= e($u['CodigoProfesor']) ?></code>
                    <button type="button" class="btn btn-sm" data-copy="<?= e($u['CodigoProfesor']) ?>">Copiar</button></div>
            <?php else:
                $prof = $u['ProfesorId'] ? row('SELECT Name FROM Users WHERE id = ?', [(int) $u['ProfesorId']]) : null; ?>
                <h3>Profesor</h3>
                <?php if ($prof): ?>
                    <p>Estás vinculado con <strong><?= e($prof['Name']) ?></strong>.</p>
                <?php else: ?>
                    <p class="muted">Todavía no te vinculaste con un profesor.</p>
                    <a class="btn btn-sm" href="<?= e(url('Rutinas/ListaRutinas.php?conectar=1')) ?>"><?= icon('link') ?>Conectarse con profesor</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php page_end(['Js/perfil.js']); ?>

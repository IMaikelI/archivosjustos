<?php
/** CU-01: Registro de Usuario o Profesor. */
require __DIR__ . '/../layout.php';
if (current_user()) {
    redirect(url('index.php'));
}
page_start('Registrarse', '', ['auth' => true]);
?>
<div class="auth__inner">
    <div class="auth__logo"><?= logo('lg') ?></div>

    <div class="auth__card">
        <p class="eyebrow">Crear cuenta</p>
        <h1>¿Cómo vas a usar MaxRep?</h1>
        <p class="muted">Elegí tu rol para continuar.</p>

        <div class="role-cards" role="radiogroup" aria-label="Tipo de cuenta">
            <button type="button" class="role-card" role="radio" aria-checked="false" data-role="usuario">
                <?= icon('dumbbell') ?>
                <span class="role-card__title">USUARIO</span>
                <span class="role-card__desc">Entreno, armo mis rutinas y sigo mis marcas.</span>
            </button>
            <button type="button" class="role-card" role="radio" aria-checked="false" data-role="profesor">
                <?= icon('teacher') ?>
                <span class="role-card__title">PROFESOR</span>
                <span class="role-card__desc">Creo rutinas y acompaño a mis alumnos.</span>
            </button>
        </div>

        <form id="registerForm" class="reveal" novalidate hidden>
            <h2 class="section" style="font-size:var(--fs-lg)">Tus datos <span class="badge badge--accent" id="roleBadge"></span></h2>
            <div class="field">
                <label for="name">Nombre <span class="req" aria-hidden="true">*</span></label>
                <input class="input" id="name" name="name" autocomplete="name" maxlength="80" required>
            </div>
            <div class="field">
                <label for="email">Correo electrónico <span class="req" aria-hidden="true">*</span></label>
                <input class="input" id="email" name="email" type="email" autocomplete="email" maxlength="190" required>
            </div>
            <div class="field">
                <label for="password">Contraseña <span class="req" aria-hidden="true">*</span></label>
                <input class="input" id="password" name="password" type="password" autocomplete="new-password" required aria-describedby="pwHint">
                <span class="field-hint" id="pwHint">Mínimo 8 caracteres, con mayúscula, minúscula y número.</span>
                <ul class="check-list" id="pwRules" hidden aria-live="polite">
                    <li data-rule="length">Al menos 8 caracteres</li>
                    <li data-rule="upper">Una letra mayúscula</li>
                    <li data-rule="lower">Una letra minúscula</li>
                    <li data-rule="number">Un número</li>
                </ul>
            </div>
            <div class="field">
                <label for="password2">Repetí la contraseña <span class="req" aria-hidden="true">*</span></label>
                <input class="input" id="password2" name="password2" type="password" autocomplete="new-password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-register" id="registerBtn">Registrarse</button>
        </form>
    </div>
    <p class="auth__alt">¿Ya tenés cuenta? <a href="<?= e(url('Cuenta/login.php')) ?>">Iniciá sesión</a></p>
</div>
<?php page_end(['Js/registro.js']); ?>

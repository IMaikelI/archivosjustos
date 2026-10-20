<?php
/** Inicio de sesión. */
require __DIR__ . '/../layout.php';
$next = safe_next($_GET['next'] ?? null);
if (current_user()) {
    redirect($next);
}
$expired = isset($_GET['expirada']);
page_start('Iniciar sesión', '', ['auth' => true]);
?>
<div class="auth__inner" style="max-width:460px">
    <div class="auth__logo"><?= logo('lg') ?></div>
    <div class="auth__card">
        <p class="eyebrow">Bienvenido</p>
        <h1>Iniciar sesión</h1>
        <?php if ($expired): ?>
            <div class="banner banner--info"><?= icon('clock') ?><span>Tu sesión expiró por inactividad. Volvé a ingresar.</span></div>
        <?php endif; ?>
        <div id="loginError" class="banner banner--error" role="alert" hidden></div>
        <form id="loginForm" novalidate data-next="<?= e($next) ?>">
            <div class="field">
                <label for="user">Correo o nombre de usuario</label>
                <input class="input" id="user" name="user" autocomplete="username" required>
            </div>
            <div class="field">
                <label for="password">Contraseña</label>
                <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block" id="loginBtn">Ingresar</button>
        </form>
    </div>
    <p class="auth__alt">¿No tenés cuenta? <a href="<?= e(url('Cuenta/registro.php')) ?>">Registrate</a></p>
</div>
<?php page_end(['Js/login.js']); ?>

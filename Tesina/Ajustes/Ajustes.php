<?php
/** CU-07: Configuración general. */
require __DIR__ . '/../layout.php';
$u = require_page_auth();
page_start('Ajustes', 'ajustes');
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Configuración</p>
        <h1>Ajustes</h1>
        <p>Apariencia, seguridad, suscripción y ayuda.</p>
    </div>
</div>

<div class="settings">
    <section class="card setting-row" aria-labelledby="h-tema">
        <div>
            <h3 id="h-tema">Modo Oscuro</h3>
            <p>Se guarda en tu cuenta y se aplica en todos tus dispositivos.</p>
        </div>
        <label class="switch">
            <input type="checkbox" id="darkToggle" role="switch" <?= $u['Theme'] === 'oscuro' ? 'checked' : '' ?> aria-labelledby="h-tema">
            <span class="switch__track" aria-hidden="true"></span>
        </label>
    </section>

    <section class="card" aria-labelledby="h-pass">
        <h3 id="h-pass">Cambiar contraseña</h3>
        <form id="passForm" novalidate style="margin-top:12px">
            <div class="field">
                <label for="current">Contraseña actual</label>
                <input class="input" type="password" id="current" autocomplete="current-password">
            </div>
            <div class="row">
                <div class="field">
                    <label for="newPass">Nueva contraseña</label>
                    <input class="input" type="password" id="newPass" autocomplete="new-password" aria-describedby="newPassHint">
                    <span class="field-hint" id="newPassHint">Mínimo 8 caracteres, con mayúscula, minúscula y número.</span>
                </div>
                <div class="field">
                    <label for="newPass2">Repetí la nueva contraseña</label>
                    <input class="input" type="password" id="newPass2" autocomplete="new-password">
                </div>
            </div>
            <button type="submit" class="btn btn-dark" id="passBtn" disabled>Confirmar cambio</button>
        </form>
    </section>

    <section class="card" aria-labelledby="h-subs" id="subsCard">
        <h3 id="h-subs">Suscripciones</h3>
        <div id="subsBody"><div class="skeleton skeleton-line" style="width:60%"></div><div class="skeleton skeleton-line"></div></div>
    </section>

    <section class="card setting-row" aria-labelledby="h-help">
        <div>
            <h3 id="h-help">Ayuda y soporte</h3>
            <p>Preguntas frecuentes y formulario de contacto.</p>
        </div>
        <button type="button" class="btn" id="helpBtn"><?= icon('help') ?>Ayuda y soporte</button>
    </section>
</div>
<?php page_end(['Js/ajustes.js']); ?>

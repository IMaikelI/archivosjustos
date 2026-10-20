<?php
/** CU-05: Lista de rutinas, vínculo con profesor y rutinas de alumnos. */
require __DIR__ . '/../layout.php';
$u = require_page_auth();
if (isset($_GET['nueva'])) {
    redirect(url('Rutinas/Rutinas.php?nueva=1'));
}
$prof = is_profesor($u);
$tab = ($_GET['tab'] ?? '') === 'alumnos' && $prof ? 'alumnos' : 'mias';
page_start('Rutinas', 'rutinas');
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Entrenamiento</p>
        <h1>Rutinas</h1>
        <p><?= $prof ? 'Creá rutinas y seguí las de tus alumnos.' : 'Tus rutinas, las compartidas con vos y tu profesor.' ?></p>
    </div>
    <div class="actions-bar">
        <a class="btn btn-primary" href="<?= e(url('Rutinas/Rutinas.php?nueva=1')) ?>"><?= icon('plus') ?>CREAR RUTINA</a>
        <?php if (!$prof): ?>
            <button type="button" class="btn btn-dark" id="connectBtn"<?= isset($_GET['conectar']) ? ' data-autoopen' : '' ?>><?= icon('link') ?>CONECTARSE CON PROFESOR</button>
        <?php endif; ?>
        <button type="button" class="btn" id="redeemBtn"><?= icon('share') ?>Usar código de rutina</button>
    </div>
</div>

<div id="todayBox"></div>
<div id="profBox"></div>

<div class="tabs" role="tablist" aria-label="Rutinas">
    <button class="tab" role="tab" id="tab-mias" aria-controls="panel-mias" aria-selected="<?= $tab === 'mias' ? 'true' : 'false' ?>">Mis rutinas</button>
    <button class="tab" role="tab" id="tab-compartidas" aria-controls="panel-compartidas" aria-selected="false">Compartidas conmigo</button>
    <?php if ($prof): ?>
        <button class="tab" role="tab" id="tab-alumnos" aria-controls="panel-alumnos" aria-selected="<?= $tab === 'alumnos' ? 'true' : 'false' ?>">RUTINAS ALUMNOS</button>
    <?php endif; ?>
</div>
<section id="panel-mias" role="tabpanel" aria-labelledby="tab-mias"<?= $tab === 'mias' ? '' : ' hidden' ?>></section>
<section id="panel-compartidas" role="tabpanel" aria-labelledby="tab-compartidas" hidden></section>
<?php if ($prof): ?>
    <section id="panel-alumnos" role="tabpanel" aria-labelledby="tab-alumnos"<?= $tab === 'alumnos' ? '' : ' hidden' ?>></section>
<?php endif; ?>

<script type="application/json" id="pageData"><?= json_encode([
    'role' => $u['Role'],
    'codigo' => $prof ? $u['CodigoProfesor'] : null,
    'dias' => MR_DIAS,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<?php page_end(['Js/rutinas.js']); ?>

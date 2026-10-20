<?php
/** CU-03: Inicio (dashboard). */
require __DIR__ . '/layout.php';
$u = require_page_auth();
page_start('Inicio', 'inicio');
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><?= e(MR_DIAS[(int) date('N')]) ?></p>
        <h1>Hola, <span data-user-name><?= e($u['Name']) ?></span></h1>
        <p>Tu resumen de entrenamiento.</p>
    </div>
    <div class="actions-bar">
        <a class="btn btn-primary" href="<?= e(url('Rutinas/ListaRutinas.php?nueva=1')) ?>"><?= icon('plus') ?>Nueva rutina</a>
    </div>
</div>
<div id="dashboard" aria-busy="true" aria-live="polite"></div>
<?php page_end(['Js/inicio.js']); ?>

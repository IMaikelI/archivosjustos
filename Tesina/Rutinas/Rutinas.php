<?php
/** CU-05: Lienzo de creación y edición de una rutina. */
require __DIR__ . '/../layout.php';
$u = require_page_auth();
$id = (int) ($_GET['id'] ?? 0);
$isNew = !$id;
$alumnos = is_profesor($u) ? rows('SELECT id, Name FROM Users WHERE ProfesorId = ? ORDER BY Name', [(int) $u['id']]) : [];
$para = (int) ($_GET['para'] ?? 0);
page_start($isNew ? 'Crear rutina' : 'Rutina', 'rutinas');
?>
<div class="page-head">
    <div>
        <p class="eyebrow"><a href="<?= e(url('Rutinas/ListaRutinas.php')) ?>" style="color:inherit">← Rutinas</a></p>
        <h1 id="editorTitle"><?= $isNew ? 'Crear rutina' : 'Rutina' ?></h1>
        <p id="editorMeta"></p>
    </div>
    <div class="actions-bar">
        <span class="badge" id="dirtyBadge" hidden>Cambios sin guardar</span>
        <button type="button" class="btn btn-primary" id="saveBtn" disabled>Añade al menos un ejercicio</button>
    </div>
</div>

<div id="editor" aria-busy="true"
     data-id="<?= $id ?>"
     data-para="<?= $para ?>">
    <div class="card" style="margin-bottom:16px">
        <div class="row">
            <div class="field">
                <label for="wName">Nombre de la rutina</label>
                <input class="input" id="wName" maxlength="80" placeholder="Ej. Tren superior" autocomplete="off">
            </div>
            <div class="field">
                <label for="wDia">Día</label>
                <select class="select" id="wDia">
                    <option value="">Sin día fijo</option>
                    <?php foreach (MR_DIAS as $n => $d): ?>
                        <option value="<?= $n ?>"><?= e($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($isNew && $alumnos): ?>
                <div class="field">
                    <label for="wOwner">Para</label>
                    <select class="select" id="wOwner">
                        <option value="">Mí (profesor)</option>
                        <?php foreach ($alumnos as $a): ?>
                            <option value="<?= (int) $a['id'] ?>"<?= $para === (int) $a['id'] ? ' selected' : '' ?>><?= e($a['Name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="section-title">
        <h2>Ejercicios</h2>
        <span class="muted small" id="countLabel"></span>
    </div>
    <div class="canvas is-empty" id="canvas" aria-live="polite"></div>
    <div style="margin-top:12px">
        <button type="button" class="add-ex" id="addBtn"><?= icon('plus') ?>Agregar Ejercicio</button>
    </div>
</div>
<?php page_end(['Js/editor.js']); ?>

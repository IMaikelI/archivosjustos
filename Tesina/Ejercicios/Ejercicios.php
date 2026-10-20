<?php
/** CU-06: Buscador y biblioteca de ejercicios. */
require __DIR__ . '/../layout.php';
require_page_auth();
$musculos = rows('SELECT m.id, m.Name FROM Musculos m WHERE EXISTS (SELECT 1 FROM Ejercicios e WHERE e.Mu = m.id) ORDER BY m.id');
page_start('Ejercicios', 'ejercicios');
?>
<div class="page-head">
    <div>
        <p class="eyebrow">Biblioteca</p>
        <h1>Ejercicios</h1>
        <p>Buscá un ejercicio para ver su técnica y registrar tus marcas.</p>
    </div>
</div>

<div class="search" id="searchBox">
    <?= icon('search', 'search__icon') ?>
    <label for="q" class="sr-only">Buscar ejercicios e info</label>
    <input type="search" id="q" class="search__input" placeholder="BUSCAR EJERCICIOS E INFO" autocomplete="off"
           aria-controls="results" aria-expanded="false" aria-haspopup="listbox">
    <ul class="search__recent" id="recent" role="listbox" aria-label="Búsquedas recientes" hidden></ul>
</div>

<div class="filters" role="group" aria-label="Filtros">
    <?php foreach ($musculos as $m): ?>
        <button type="button" class="chip" data-mu="<?= (int) $m['id'] ?>" aria-pressed="false"><?= e($m['Name']) ?></button>
    <?php endforeach; ?>
    <span class="chip-sep" aria-hidden="true"></span>
    <button type="button" class="chip" data-type="Libre" aria-pressed="false">Libre</button>
    <button type="button" class="chip" data-type="Cable" aria-pressed="false">Cable</button>
</div>

<p class="muted small" id="resultCount" aria-live="polite"></p>
<div id="results"></div>
<?php page_end(['Js/ejercicios.js']); ?>

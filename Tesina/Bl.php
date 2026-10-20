<?php
/**
 * Barra lateral de navegación. La incluye layout.php en todas las páginas con sesión.
 */
$u = current_user();
$active = $GLOBALS['MR_ACTIVE'] ?? '';
$items = [
    'inicio' => ['Inicio', 'index.php', 'home'],
    'rutinas' => ['Rutinas', 'Rutinas/ListaRutinas.php', 'list'],
    'ejercicios' => ['Ejercicios', 'Ejercicios/Ejercicios.php', 'dumbbell'],
    'perfil' => ['Perfil', 'Cuenta/Perfil.php', 'user'],
    'ajustes' => ['Ajustes', 'Ajustes/Ajustes.php', 'settings'],
];
?>
<header class="topbar">
    <a href="<?= e(url('index.php')) ?>" style="text-decoration:none"><?= logo('sm') ?></a>
    <button type="button" id="menuBtn" aria-controls="sidebar" aria-expanded="false" aria-label="Abrir menú"><?= icon('menu') ?></button>
</header>
<aside class="sidebar" id="sidebar" aria-label="Navegación principal">
    <a href="<?= e(url('index.php')) ?>" style="text-decoration:none"><?= logo() ?></a>

    <a class="profile-chip" href="<?= e(url('Cuenta/Perfil.php')) ?>">
        <img src="<?= e(photo_url($u['Photo'])) ?>" alt="" data-user-photo>
        <div>
            <strong data-user-name><?= e($u['Name']) ?></strong>
            <span><?= e($u['Role']) ?></span>
        </div>
    </a>

    <nav class="nav">
        <?php foreach ($items as $key => [$label, $href, $ic]): ?>
            <a href="<?= e(url($href)) ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>><?= icon($ic) ?><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar__foot nav">
        <form method="post" action="<?= e(url('Cuenta/logout.php')) ?>">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <button type="submit"><?= icon('logout') ?>Cerrar sesión</button>
        </form>
    </div>
</aside>

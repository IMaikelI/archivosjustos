<?php
/**
 * Plantilla compartida de páginas: <head>, barra lateral (Bl.php), calculadora RM (1Rm.html),
 * contenedor de notificaciones y scripts.
 *
 *   page_start('Rutinas', 'rutinas');   ...contenido...   page_end(['Js/rutinas.js']);
 */

require_once __DIR__ . '/Conection.php';

/** Íconos SVG en línea (trazos simples, heredan currentColor). */
function icon(string $name, string $class = ''): string
{
    static $paths = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/>',
        'dumbbell' => '<path d="M6 7v10M18 7v10M3 9.5v5M21 9.5v5M6 12h12"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.5 2.9-6 6.5-6s6.5 2.5 6.5 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14c2.2.6 3.5 2.8 3.5 6"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'alert' => '<path d="M12 3 2 20h20L12 3z"/><path d="M12 10v4M12 17.5v.5"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5v.5"/>',
        'grip' => '<circle cx="9" cy="6" r="1.3"/><circle cx="15" cy="6" r="1.3"/><circle cx="9" cy="12" r="1.3"/><circle cx="15" cy="12" r="1.3"/><circle cx="9" cy="18" r="1.3"/><circle cx="15" cy="18" r="1.3"/>',
        'up' => '<path d="m6 15 6-6 6 6"/>',
        'down' => '<path d="m6 9 6 6 6-6"/>',
        'calc' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 11h2M14 11h2M8 15h2M14 15h2M8 18.5h2M14 18.5h2"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'share' => '<circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="m8.2 10.8 7.6-4.4M8.2 13.2l7.6 4.4"/>',
        'edit' => '<path d="M4 20h4L19 9l-4-4L4 16v4z"/><path d="m13.5 6.5 4 4"/>',
        'trash' => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'refresh' => '<path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/>',
        'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.7.3-1 .9-1 1.7v.5M12 17v.5"/>',
        'card' => '<rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19M6 15h4"/>',
        'moon' => '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/>',
        'lock' => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>',
        'wifi-off' => '<path d="M2 2l20 20M8.5 16.5a5 5 0 0 1 7 0M5 13a10 10 0 0 1 5.2-2.8M19 13a10 10 0 0 0-2-1.5M2 8.8a15 15 0 0 1 4.2-2.6M22 8.8A15 15 0 0 0 10.7 5M12 20h.01"/>',
        'teacher' => '<path d="M2 9 12 4l10 5-10 5L2 9z"/><path d="M6 11v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5M22 9v6"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'trophy' => '<path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4z"/><path d="M7 6H4v2a3 3 0 0 0 3 3M17 6h3v2a3 3 0 0 1-3 3"/>',
    ];
    $p = $paths[$name] ?? '';
    $cls = $class !== '' ? ' class="' . e($class) . '"' : '';
    return '<svg' . $cls . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $p . '</svg>';
}

/** Logotipo MAXREP (Anton, MAX rojo + REP blanco hueso, dos líneas rojas). */
function logo(string $size = ''): string
{
    $mod = $size !== '' ? ' mr-logo--' . e($size) : '';
    $lines = $size === 'sm' ? '' : '<span class="mr-logo__lines" aria-hidden="true"><i></i><i></i></span>';
    return '<span class="mr-logo' . $mod . '" role="img" aria-label="MaxRep">'
        . '<span class="mr-logo__word" aria-hidden="true"><span class="mr-logo__max">MAX</span><span class="mr-logo__rep">REP</span></span>'
        . $lines . '</span>';
}

/**
 * @param string $title  Título de la pestaña
 * @param string $active Sección activa del menú (inicio, rutinas, ejercicios, perfil, ajustes)
 * @param array  $opts   ['auth' => true] para páginas sin barra lateral (login/registro)
 */
function page_start(string $title, string $active = '', array $opts = []): void
{
    $u = current_user();
    $GLOBALS['MR_ACTIVE'] = $active;
    $GLOBALS['MR_AUTH_LAYOUT'] = !empty($opts['auth']) || !$u;
    $theme = ($u['Theme'] ?? '') === 'oscuro' ? 'oscuro' : 'claro';
    ?>
<!DOCTYPE html>
<html lang="es" data-theme="<?= $theme ?>"<?= $u ? '' : ' data-theme-local="1"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="base-url" content="<?= e(base_path()) ?>">
    <meta name="theme-color" content="#0A0A0A">
    <title><?= e($title) ?> · MaxRep</title>
    <link rel="icon" href="<?= e(url('img/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= e(asset('Css/Standard.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('Css/Rm.css')) ?>">
    <?php if (!$u): ?>
    <script>try{if(localStorage.getItem('mr-theme')==='oscuro')document.documentElement.dataset.theme='oscuro'}catch(e){}</script>
    <?php endif; ?>
</head>
<body>
    <?php if ($GLOBALS['MR_AUTH_LAYOUT']): ?>
<main class="auth" id="main">
    <?php else: ?>
<div class="app">
    <?php include __DIR__ . '/Bl.php'; ?>
<main class="main" id="main">
    <?php endif;
}

function page_end(array $scripts = []): void
{
    $u = current_user();
    ?>
</main>
    <?php if (!$GLOBALS['MR_AUTH_LAYOUT']): ?>
</div>
    <?php include __DIR__ . '/1Rm.html'; ?>
    <?php endif; ?>
<div class="toasts" id="toasts" role="status" aria-live="polite"></div>
<script src="<?= e(asset('Js/app.js')) ?>"></script>
    <?php if ($u && !$GLOBALS['MR_AUTH_LAYOUT']): ?>
<script src="<?= e(asset('Js/rm.js')) ?>"></script>
    <?php endif; ?>
    <?php foreach ($scripts as $s): ?>
<script src="<?= e(asset($s)) ?>"></script>
    <?php endforeach; ?>
</body>
</html>
    <?php
}

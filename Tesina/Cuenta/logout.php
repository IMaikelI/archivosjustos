<?php
/** Cierra la sesión (solo por POST con token CSRF). */
require __DIR__ . '/../Conection.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
    logout_user();
}
redirect(url('Cuenta/login.php'));

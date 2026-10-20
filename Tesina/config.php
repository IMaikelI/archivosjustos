<?php
/**
 * Configuración de MaxRep.
 *
 * Los valores por defecto sirven para XAMPP en local (root sin contraseña, base "ironcore").
 * En cualquier otro entorno, definí las variables de entorno MAXREP_* en lugar de editar este archivo.
 */

$env = static function (string $name, $default) {
    $v = getenv($name);
    return ($v === false || $v === '') ? $default : $v;
};

return [
    // Base de datos
    'db_host' => $env('MAXREP_DB_HOST', 'localhost'),
    'db_port' => (int) $env('MAXREP_DB_PORT', 3306),
    'db_user' => $env('MAXREP_DB_USER', 'root'),
    'db_pass' => getenv('MAXREP_DB_PASS') === false ? '' : getenv('MAXREP_DB_PASS'),
    'db_name' => $env('MAXREP_DB_NAME', 'ironcore'),

    // Zona horaria para "rutina de hoy" y fechas (XAMPP suele venir con Europe/Berlin)
    'timezone' => $env('MAXREP_TZ', 'America/Argentina/Cordoba'),

    // Segundos de inactividad antes de que la sesión expire (CU-02, excepción 2.3)
    'session_idle' => (int) $env('MAXREP_SESSION_IDLE', 1800),

    // Muestra errores detallados (solo para desarrollo)
    'debug' => $env('MAXREP_DEBUG', '0') === '1',

    // Foto de perfil (CU-02, excepción 2.2)
    'photo_max_bytes' => 5 * 1024 * 1024,

    // Suscripciones (CU-07). "test" = pasarela de prueba integrada, sin cobros reales.
    // No existe todavía un modo "live": requiere integrar un procesador de pagos real.
    'payment_mode' => $env('MAXREP_PAYMENT_MODE', 'test'),
    'plan' => [
        'codigo' => 'premium_mensual',
        'nombre' => 'MaxRep Premium (mensual)',
        'precio' => (float) $env('MAXREP_PLAN_PRECIO', 4999),
        'moneda' => $env('MAXREP_PLAN_MONEDA', 'ARS'),
    ],

    // Minutos de validez de un código para compartir rutinas
    'token_minutes' => 30,
];

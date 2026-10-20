<?php
/**
 * Núcleo de MaxRep: conexión MySQL, sesión, autenticación, CSRF y helpers JSON.
 * Lo incluyen todas las páginas y endpoints con require_once.
 *
 * Reglas:
 *  - Toda consulta con datos externos usa q()/rows()/row() (consultas preparadas).
 *  - Toda salida HTML de datos pasa por e().
 *  - Los endpoints de Api/ definen MR_API antes de incluir este archivo.
 */

if (defined('MAXREP_CORE')) {
    return;
}
define('MAXREP_CORE', true);

$GLOBALS['MR_CONFIG'] = require __DIR__ . '/config.php';

function cfg(string $key)
{
    return $GLOBALS['MR_CONFIG'][$key] ?? null;
}

ini_set('display_errors', cfg('debug') ? '1' : '0');
date_default_timezone_set(cfg('timezone') ?: 'UTC');
error_reporting(E_ALL);

/* ------------------------------------------------------------------ *
 * Errores
 * ------------------------------------------------------------------ */

set_exception_handler(function (Throwable $e) {
    error_log('[MaxRep] ' . $e);
    $dbDown = $e->getMessage() === 'db_unavailable';
    if (defined('MR_API')) {
        json_error(
            $dbDown ? 'No se pudo conectar con la base de datos' : 'Error interno del servidor',
            $dbDown ? 503 : 500,
            ['code' => $dbDown ? 'db_unavailable' : 'server_error']
        );
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    $detail = cfg('debug') ? '<pre>' . e((string) $e) . '</pre>' : '';
    echo '<!doctype html><meta charset="utf-8"><title>MaxRep</title>'
        . '<div style="font-family:system-ui;padding:2rem"><h1>Algo salió mal</h1><p>'
        . ($dbDown ? 'No se pudo conectar con la base de datos. Verificá que MySQL esté iniciado.' : 'Ocurrió un error inesperado.')
        . '</p>' . $detail . '</div>';
    exit;
});

/* ------------------------------------------------------------------ *
 * Base de datos
 * ------------------------------------------------------------------ */

function Conect(): mysqli
{
    static $con = null;
    if ($con instanceof mysqli) {
        return $con;
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    try {
        $con = new mysqli(cfg('db_host'), cfg('db_user'), cfg('db_pass'), cfg('db_name'), cfg('db_port'));
        $con->set_charset('utf8mb4');
    } catch (mysqli_sql_exception $e) {
        error_log('[MaxRep] DB: ' . $e->getMessage());
        throw new RuntimeException('db_unavailable');
    }
    return $con;
}

/** Ejecuta una consulta preparada. Los tipos se infieren de los valores. */
function q(string $sql, array $params = []): mysqli_stmt
{
    $st = Conect()->prepare($sql);
    if ($params) {
        $params = array_values($params);
        $types = '';
        foreach ($params as $p) {
            $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
        }
        $st->bind_param($types, ...$params);
    }
    $st->execute();
    return $st;
}

function rows(string $sql, array $params = []): array
{
    $res = q($sql, $params)->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function row(string $sql, array $params = []): ?array
{
    $r = rows($sql, $params);
    return $r[0] ?? null;
}

/** Ejecuta INSERT/UPDATE/DELETE y devuelve las filas afectadas. */
function run(string $sql, array $params = []): int
{
    return q($sql, $params)->affected_rows;
}

function last_id(): int
{
    return (int) Conect()->insert_id;
}

/* ------------------------------------------------------------------ *
 * Rutas
 * ------------------------------------------------------------------ */

/** Ruta web de la carpeta del proyecto (p. ej. "/Tesina" en XAMPP, "" en la raíz). */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $env = getenv('MAXREP_BASE_URL');
    if ($env !== false) {
        return $base = rtrim($env, '/');
    }
    $doc = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $dir = str_replace('\\', '/', __DIR__);
    $base = ($doc !== '' && stripos($dir, $doc) === 0) ? substr($dir, strlen($doc)) : '';
    return $base = rtrim($base, '/');
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** Cache-busting para CSS/JS propios. */
function asset(string $path): string
{
    $file = __DIR__ . '/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 0;
    return url($path) . '?v=' . $v;
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

/* ------------------------------------------------------------------ *
 * Sesión y autenticación
 * ------------------------------------------------------------------ */

function mr_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (PHP_SAPI !== 'cli') {
        session_name('MAXREPSESS');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => base_path() === '' ? '/' : base_path() . '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
    }
    session_start();

    if (!empty($_SESSION['uid'])) {
        $last = $_SESSION['last'] ?? time();
        if (time() - $last > cfg('session_idle')) {
            // Se conserva quién era para permitir la reautenticación (CU-02, 2.3)
            $_SESSION['expired_uid'] = $_SESSION['uid'];
            unset($_SESSION['uid'], $_SESSION['last']);
        } else {
            $_SESSION['last'] = time();
        }
    }
}

function login_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    $_SESSION['last'] = time();
    unset($_SESSION['expired_uid'], $_SESSION['login_fail']);
    $GLOBALS['MR_USER'] = null;
}

function logout_user(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['uid'])) {
        return null;
    }
    if (!empty($GLOBALS['MR_USER']) && (int) $GLOBALS['MR_USER']['id'] === (int) $_SESSION['uid']) {
        return $GLOBALS['MR_USER'];
    }
    $u = row(
        'SELECT id, Name, Email, Role, Photo, Peso, Altura, Objetivos, Theme, CodigoProfesor, ProfesorId
         FROM Users WHERE id = ?',
        [(int) $_SESSION['uid']]
    );
    if (!$u) {
        logout_user();
        return null;
    }
    return $GLOBALS['MR_USER'] = $u;
}

function is_profesor(?array $u = null): bool
{
    $u = $u ?? current_user();
    return $u && $u['Role'] === 'profesor';
}

/** Para páginas: si no hay sesión, redirige al login conservando el destino. */
function require_page_auth(): array
{
    $u = current_user();
    if (!$u) {
        $next = $_SERVER['REQUEST_URI'] ?? url('index.php');
        $q = isset($_SESSION['expired_uid']) ? '&expirada=1' : '';
        redirect(url('Cuenta/login.php') . '?next=' . rawurlencode($next) . $q);
    }
    return $u;
}

/** Para endpoints JSON. */
function require_api_auth(): array
{
    $u = current_user();
    if ($u) {
        return $u;
    }
    if (!empty($_SESSION['expired_uid'])) {
        json_error('Tu sesión expiró. Volvé a ingresar tu contraseña.', 401, ['code' => 'session_expired']);
    }
    json_error('Necesitás iniciar sesión.', 401, ['code' => 'unauthenticated']);
}

/** Valida que un "next" sea una ruta interna del proyecto (evita redirecciones abiertas). */
function safe_next(?string $next): string
{
    $default = url('index.php');
    if (!$next || !str_starts_with($next, '/') || str_starts_with($next, '//')) {
        return $default;
    }
    $base = base_path();
    if ($base !== '' && stripos($next, $base . '/') !== 0) {
        return $default;
    }
    return $next;
}

/* ------------------------------------------------------------------ *
 * CSRF
 * ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        json_error('La página quedó desactualizada. Recargala e intentá de nuevo.', 419, ['code' => 'csrf']);
    }
}

/* ------------------------------------------------------------------ *
 * Entrada / salida
 * ------------------------------------------------------------------ */

function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_out($data, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $msg, int $status = 400, array $extra = []): void
{
    json_out(['ok' => false, 'error' => $msg] + $extra, $status);
}

/** Cuerpo de la petición: JSON o formulario. */
function input(): array
{
    static $data = null;
    if ($data !== null) {
        return $data;
    }
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
        $data = is_array($data) ? $data : [];
    } else {
        $data = $_POST;
    }
    return $data;
}

function str_in(array $in, string $key, int $max = 255): string
{
    $v = $in[$key] ?? '';
    $v = is_scalar($v) ? trim((string) $v) : '';
    return mb_substr($v, 0, $max);
}

/** Inicia un endpoint JSON: sesión, método permitido y CSRF en escrituras. */
function api_start(array $methods = ['GET', 'POST']): string
{
    mr_session();
    $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($m, $methods, true)) {
        json_error('Método no permitido', 405);
    }
    if ($m !== 'GET') {
        check_csrf();
    }
    return $m;
}

/* ------------------------------------------------------------------ *
 * Dominio compartido
 * ------------------------------------------------------------------ */

const MR_DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

/** Requisitos de contraseña (CU-01, 1.2). Devuelve las claves de los requisitos que faltan. */
function password_issues(string $p): array
{
    $missing = [];
    if (mb_strlen($p) < 8) {
        $missing[] = 'length';
    }
    if (!preg_match('/\p{Lu}/u', $p)) {
        $missing[] = 'upper';
    }
    if (!preg_match('/\p{Ll}/u', $p)) {
        $missing[] = 'lower';
    }
    if (!preg_match('/\d/', $p)) {
        $missing[] = 'number';
    }
    return $missing;
}

/** Verifica una contraseña; si estaba en texto plano (esquema anterior) o con hash viejo, la re-hashea. */
function verify_password(int $userId, string $plain, string $stored): bool
{
    if ($stored === '') {
        return false;
    }
    if (password_get_info($stored)['algo'] === null) {
        $ok = hash_equals($stored, $plain);
    } else {
        $ok = password_verify($plain, $stored);
    }
    if ($ok && (password_get_info($stored)['algo'] === null || password_needs_rehash($stored, PASSWORD_DEFAULT))) {
        run('UPDATE Users SET Pass = ? WHERE id = ?', [password_hash($plain, PASSWORD_DEFAULT), $userId]);
    }
    return $ok;
}

function photo_url(?string $photo): string
{
    if (!$photo) {
        return url('img/avatar.svg');
    }
    if (preg_match('~^https?://~i', $photo)) {
        return $photo;
    }
    return url($photo);
}

mr_session();

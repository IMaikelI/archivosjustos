<?php
/**
 * Autenticación (CU-01 y CU-02/2.3).
 *   POST ?action=register  {role, name, email, password, password2}
 *   POST ?action=login     {user, password, next}
 *   POST ?action=reauth    {password}
 *   GET  ?action=session
 */
define('MR_API', true);
require __DIR__ . '/../Conection.php';

$method = api_start(['GET', 'POST']);
$action = $_GET['action'] ?? '';
$in = input();

switch ($action) {
    case 'register':
        $role = str_in($in, 'role', 10);
        $name = str_in($in, 'name', 80);
        $email = mb_strtolower(str_in($in, 'email', 190));
        $pass = (string) ($in['password'] ?? '');
        $pass2 = (string) ($in['password2'] ?? '');

        if (!in_array($role, ['usuario', 'profesor'], true)) {
            json_error('Elegí si te registrás como Usuario o Profesor.', 422, ['code' => 'role']);
        }
        $missing = [];
        foreach (['name' => $name, 'email' => $email, 'password' => $pass, 'password2' => $pass2] as $k => $v) {
            if ($v === '') {
                $missing[] = $k;
            }
        }
        if ($missing) {
            json_error('Completá los campos obligatorios.', 422, ['code' => 'missing', 'fields' => $missing]);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_error('Ingresá un correo válido.', 422, ['code' => 'invalid', 'field' => 'email']);
        }
        if ($issues = password_issues($pass)) {
            json_error('La contraseña no cumple los requisitos.', 422, ['code' => 'weak_password', 'missing' => $issues]);
        }
        if (!hash_equals($pass, $pass2)) {
            json_error('Las contraseñas no coinciden.', 422, ['code' => 'invalid', 'field' => 'password2']);
        }
        if (row('SELECT id FROM Users WHERE LOWER(Email) = ?', [$email])) {
            json_error('Este correo ya está en uso', 409, ['code' => 'email_taken', 'field' => 'email']);
        }

        $code = null;
        if ($role === 'profesor') {
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            do {
                $code = '';
                for ($i = 0; $i < 8; $i++) {
                    $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
            } while (row('SELECT id FROM Users WHERE CodigoProfesor = ?', [$code]));
        }
        try {
            run('INSERT INTO Users (Name, Email, Pass, Role, CodigoProfesor) VALUES (?,?,?,?,?)',
                [$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role, $code]);
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) { // carrera con otro registro del mismo correo
                json_error('Este correo ya está en uso', 409, ['code' => 'email_taken', 'field' => 'email']);
            }
            throw $e;
        }
        login_user(last_id());
        json_out(['ok' => true, 'redirect' => url('index.php')], 201);

    case 'login':
        $user = str_in($in, 'user', 190);
        $pass = (string) ($in['password'] ?? '');
        $fail = $_SESSION['login_fail'] ?? ['n' => 0, 't' => 0];
        if ($fail['n'] >= 5 && time() - $fail['t'] < 30) {
            json_error('Demasiados intentos. Esperá unos segundos e intentá de nuevo.', 429, ['code' => 'throttled']);
        }
        if ($user === '' || $pass === '') {
            json_error('Ingresá tu correo y tu contraseña.', 422, ['code' => 'missing']);
        }
        $u = row('SELECT id, Pass FROM Users WHERE LOWER(Email) = LOWER(?) OR Name = ? ORDER BY LOWER(Email) = LOWER(?) DESC LIMIT 1',
            [$user, $user, $user]);
        if (!$u || !verify_password((int) $u['id'], $pass, (string) $u['Pass'])) {
            $_SESSION['login_fail'] = ['n' => $fail['n'] + 1, 't' => time()];
            json_error('Usuario o contraseña incorrectos', 401, ['code' => 'bad_credentials']);
        }
        login_user((int) $u['id']);
        json_out(['ok' => true, 'redirect' => safe_next($in['next'] ?? null)]);

    case 'reauth':
        $uid = (int) ($_SESSION['uid'] ?? $_SESSION['expired_uid'] ?? 0);
        if (!$uid) {
            json_error('Necesitás iniciar sesión.', 401, ['code' => 'unauthenticated']);
        }
        $u = row('SELECT id, Pass FROM Users WHERE id = ?', [$uid]);
        if (!$u || !verify_password($uid, (string) ($in['password'] ?? ''), (string) $u['Pass'])) {
            json_error('Contraseña incorrecta', 401, ['code' => 'bad_credentials']);
        }
        login_user($uid);
        json_out(['ok' => true, 'csrf' => csrf_token()]);

    case 'session':
        $u = current_user();
        json_out(['ok' => true, 'authenticated' => (bool) $u, 'expired' => !$u && !empty($_SESSION['expired_uid'])]);

    default:
        json_error('Acción desconocida', 404);
}

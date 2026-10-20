<?php
/**
 * Ajustes (CU-07).
 *   POST ?action=theme        {theme: claro|oscuro}
 *   POST ?action=password     {current, password, password2}
 *   GET  ?action=subscription
 *   POST ?action=subscribe    {holder, number, exp, cvc}
 *   POST ?action=cancel
 *   POST ?action=support      {asunto, mensaje}
 *
 * Pagos: con payment_mode = "test" se usa una pasarela de prueba integrada que NO cobra.
 * Nunca se guardan el número completo de tarjeta ni el código de seguridad.
 */
define('MR_API', true);
require __DIR__ . '/../Conection.php';

$method = api_start(['GET', 'POST']);
$me = require_api_auth();
$uid = (int) $me['id'];
$action = $_GET['action'] ?? '';
if ($action !== 'subscription' && $method !== 'POST') {
    json_error('Método no permitido', 405);
}
$in = input();
$plan = cfg('plan');

function active_sub(int $uid): ?array
{
    return row("SELECT id, Plan, Status, StartedAt FROM Subscriptions WHERE UserId = ? AND Status = 'activa' ORDER BY id DESC LIMIT 1", [$uid]);
}

function luhn(string $n): bool
{
    $sum = 0;
    $alt = false;
    for ($i = strlen($n) - 1; $i >= 0; $i--) {
        $d = (int) $n[$i];
        if ($alt) {
            $d *= 2;
            if ($d > 9) {
                $d -= 9;
            }
        }
        $sum += $d;
        $alt = !$alt;
    }
    return $sum % 10 === 0;
}

function card_brand(string $n): string
{
    return match (true) {
        (bool) preg_match('/^4/', $n) => 'Visa',
        (bool) preg_match('/^(5[1-5]|2[2-7])/', $n) => 'Mastercard',
        (bool) preg_match('/^3[47]/', $n) => 'American Express',
        default => 'Tarjeta',
    };
}

switch ($action) {
    case 'theme':
        $theme = ($in['theme'] ?? '') === 'oscuro' ? 'oscuro' : 'claro';
        run('UPDATE Users SET Theme = ? WHERE id = ?', [$theme, $uid]);
        json_out(['ok' => true, 'theme' => $theme]);

    case 'password':
        $cur = (string) ($in['current'] ?? '');
        $new = (string) ($in['password'] ?? '');
        $new2 = (string) ($in['password2'] ?? '');
        $stored = (string) row('SELECT Pass FROM Users WHERE id = ?', [$uid])['Pass'];
        if (!verify_password($uid, $cur, $stored)) { // 7.2
            json_error('La contraseña actual es incorrecta', 422, ['code' => 'bad_current', 'field' => 'current']);
        }
        if ($issues = password_issues($new)) {
            json_error('La nueva contraseña no cumple los requisitos.', 422, ['code' => 'weak_password', 'missing' => $issues, 'field' => 'password']);
        }
        if (!hash_equals($new, $new2)) {
            json_error('Las contraseñas no coinciden.', 422, ['code' => 'invalid', 'field' => 'password2']);
        }
        if (hash_equals($cur, $new)) {
            json_error('La nueva contraseña debe ser distinta de la actual.', 422, ['code' => 'invalid', 'field' => 'password']);
        }
        run('UPDATE Users SET Pass = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $uid]);
        session_regenerate_id(true);
        json_out(['ok' => true]);

    case 'subscription':
        $sub = active_sub($uid);
        $pays = rows('SELECT Reference, Amount, Currency, Status, CardBrand, CardLast4, CreatedAt, Mode FROM Payments
                      WHERE UserId = ? ORDER BY id DESC LIMIT 5', [$uid]);
        json_out([
            'ok' => true,
            'mode' => cfg('payment_mode'),
            'plan' => $plan,
            'subscription' => $sub ? ['status' => $sub['Status'], 'startedAt' => $sub['StartedAt'], 'plan' => $plan['nombre']] : null,
            'payments' => array_map(fn($p) => [
                'reference' => $p['Reference'], 'amount' => (float) $p['Amount'], 'currency' => $p['Currency'],
                'status' => $p['Status'], 'card' => $p['CardBrand'] . ' •••• ' . $p['CardLast4'], 'date' => $p['CreatedAt'], 'mode' => $p['Mode'],
            ], $pays),
        ]);

    case 'subscribe':
        if (cfg('payment_mode') !== 'test') {
            json_error('La pasarela de pagos no está configurada en este servidor.', 503, ['code' => 'gateway_unavailable']);
        }
        if (active_sub($uid)) {
            json_error('Ya tenés una suscripción activa.', 409, ['code' => 'already_active']);
        }
        $holder = str_in($in, 'holder', 80);
        $number = preg_replace('/\D/', '', (string) ($in['number'] ?? ''));
        $exp = preg_replace('/\s/', '', (string) ($in['exp'] ?? ''));
        $cvc = preg_replace('/\D/', '', (string) ($in['cvc'] ?? ''));
        $errors = [];
        if ($holder === '') {
            $errors['holder'] = 'Ingresá el nombre del titular';
        }
        if (strlen($number) < 13 || strlen($number) > 19 || !luhn($number)) {
            $errors['number'] = 'Número de tarjeta inválido';
        }
        if (!preg_match('~^(0[1-9]|1[0-2])/(\d{2})$~', $exp, $m)) {
            $errors['exp'] = 'Usá el formato MM/AA';
        } else {
            $last = (new DateTime(sprintf('20%s-%s-01', $m[2], $m[1])))->modify('last day of this month');
            if ($last < new DateTime('today')) {
                $errors['exp'] = 'La tarjeta está vencida';
            }
        }
        if (!preg_match('/^\d{3,4}$/', $cvc)) {
            $errors['cvc'] = 'Código inválido';
        }
        if ($errors) {
            json_error('Revisá los datos de la tarjeta.', 422, ['code' => 'invalid', 'fields' => $errors]);
        }

        // Pasarela de prueba: la tarjeta 4000 0000 0000 0002 simula un rechazo del emisor.
        $approved = $number !== '4000000000000002';
        $ref = 'MR-TEST-' . strtoupper(bin2hex(random_bytes(4)));
        $brand = card_brand($number);
        $last4 = substr($number, -4);
        $subId = null;
        if ($approved) {
            run('INSERT INTO Subscriptions (UserId, Plan) VALUES (?, ?)', [$uid, $plan['codigo']]);
            $subId = last_id();
        }
        run('INSERT INTO Payments (UserId, SubscriptionId, Amount, Currency, Status, Mode, CardBrand, CardLast4, Reference)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [$uid, $subId, $plan['precio'], $plan['moneda'], $approved ? 'aprobado' : 'rechazado', 'test', $brand, $last4, $ref]);
        if (!$approved) { // 7.1
            json_error('Pago rechazado. Verifique los datos', 402, ['code' => 'declined']);
        }
        json_out(['ok' => true, 'receipt' => [
            'reference' => $ref, 'plan' => $plan['nombre'], 'amount' => $plan['precio'], 'currency' => $plan['moneda'],
            'card' => $brand . ' •••• ' . $last4, 'holder' => $holder, 'date' => date('Y-m-d H:i'), 'mode' => 'test',
        ]], 201);

    case 'cancel':
        $sub = active_sub($uid);
        if (!$sub) {
            json_error('No tenés una suscripción activa.', 404);
        }
        run("UPDATE Subscriptions SET Status = 'cancelada', CancelledAt = NOW() WHERE id = ?", [(int) $sub['id']]);
        json_out(['ok' => true]);

    case 'support':
        $asunto = str_in($in, 'asunto', 121);
        $mensaje = str_in($in, 'mensaje', 4001);
        $errors = [];
        if ($asunto === '' || mb_strlen($asunto) > 120) {
            $errors['asunto'] = 'Ingresá un asunto (máximo 120 caracteres)';
        }
        if (mb_strlen($mensaje) < 10 || mb_strlen($mensaje) > 4000) {
            $errors['mensaje'] = 'Contanos tu consulta (entre 10 y 4000 caracteres)';
        }
        if ($errors) {
            json_error('Revisá el formulario.', 422, ['code' => 'invalid', 'fields' => $errors]);
        }
        run('INSERT INTO SupportMessages (UserId, Asunto, Mensaje) VALUES (?,?,?)', [$uid, $asunto, $mensaje]);
        json_out(['ok' => true, 'ticket' => last_id()], 201);

    default:
        json_error('Acción desconocida', 404);
}

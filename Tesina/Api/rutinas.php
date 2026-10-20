<?php
/**
 * Rutinas (CU-05).
 *   GET  ?action=list                    mis rutinas, compartidas conmigo
 *   GET  ?action=get&id=                 rutina con ejercicios
 *   GET  ?action=alumnos                 (profesor) alumnos vinculados y sus rutinas
 *   POST ?action=save     {id?, version?, name, dia, ownerId?, items:[{ej, sets, reps}]}
 *   POST ?action=delete   {id}
 *   POST ?action=share    {id}            genera un código temporal
 *   POST ?action=redeem   {code}          agrega una rutina compartida (solo lectura)
 *   POST ?action=unshare  {id}
 *   POST ?action=link     {code}          alumno → profesor
 *   POST ?action=unlink
 *
 * Permisos: ver = dueño, profesor del dueño o usuario con la rutina compartida.
 *           editar/borrar/compartir = dueño o profesor del dueño.
 */
define('MR_API', true);
require __DIR__ . '/../Conection.php';

$method = api_start(['GET', 'POST']);
$me = require_api_auth();
$uid = (int) $me['id'];
$action = $_GET['action'] ?? '';
$in = input();
$writes = ['save', 'delete', 'share', 'redeem', 'unshare', 'link', 'unlink'];
if (in_array($action, $writes, true) && $method !== 'POST') {
    json_error('Método no permitido', 405);
}

/** Carga una rutina con los datos necesarios para decidir permisos. */
function load_work(int $id): ?array
{
    return row(
        'SELECT w.id, w.UserId, w.Name, w.Dia, w.Version, w.UpdatedAt, w.UpdatedBy,
                o.Name AS OwnerName, o.ProfesorId AS OwnerProfesor, ub.Name AS UpdatedByName
         FROM Works w JOIN Users o ON o.id = w.UserId LEFT JOIN Users ub ON ub.id = w.UpdatedBy
         WHERE w.id = ?',
        [$id]
    );
}

function can_edit(array $w, int $uid): bool
{
    return (int) $w['UserId'] === $uid || (int) $w['OwnerProfesor'] === $uid;
}

function can_view(array $w, int $uid): bool
{
    return can_edit($w, $uid)
        || (bool) row('SELECT 1 FROM WorkShares WHERE WorkId = ? AND UserId = ?', [(int) $w['id'], $uid]);
}

function work_summary(array $w, int $uid): array
{
    return [
        'id' => (int) $w['id'],
        'name' => $w['Name'],
        'dia' => $w['Dia'] !== null ? (int) $w['Dia'] : null,
        'diaLabel' => $w['Dia'] !== null ? MR_DIAS[(int) $w['Dia']] : null,
        'n' => (int) ($w['n'] ?? 0),
        'owner' => ['id' => (int) $w['UserId'], 'name' => $w['OwnerName'] ?? null],
        'canEdit' => can_edit($w, $uid),
        'updatedAt' => $w['UpdatedAt'],
    ];
}

const LIST_SQL = 'SELECT w.id, w.UserId, w.Name, w.Dia, w.UpdatedAt, o.Name AS OwnerName, o.ProfesorId AS OwnerProfesor,
                         (SELECT COUNT(*) FROM WorkItems i WHERE i.WorkId = w.id) AS n
                  FROM Works w JOIN Users o ON o.id = w.UserId ';

switch ($action) {
    case 'list':
        $mine = rows(LIST_SQL . 'WHERE w.UserId = ? ORDER BY w.Dia IS NULL, w.Dia, w.Name', [$uid]);
        $shared = rows(LIST_SQL . 'JOIN WorkShares s ON s.WorkId = w.id WHERE s.UserId = ? ORDER BY w.Name', [$uid]);
        json_out([
            'ok' => true,
            'today' => (int) date('N'),
            'mine' => array_map(fn($w) => work_summary($w, $uid), $mine),
            'shared' => array_map(fn($w) => work_summary($w, $uid), $shared),
            'profesor' => $me['ProfesorId'] ? row('SELECT id, Name AS name FROM Users WHERE id = ?', [(int) $me['ProfesorId']]) : null,
        ]);

    case 'get':
        $w = load_work((int) ($_GET['id'] ?? 0));
        if (!$w || !can_view($w, $uid)) {
            json_error('La rutina no existe o no tenés acceso.', 404);
        }
        $items = rows(
            'SELECT i.EjercicioId, i.Sets, i.Reps, e.Name, e.Type, m.Name AS Mu
             FROM WorkItems i JOIN Ejercicios e ON e.id = i.EjercicioId LEFT JOIN Musculos m ON m.id = e.Mu
             WHERE i.WorkId = ? ORDER BY i.Pos',
            [(int) $w['id']]
        );
        json_out([
            'ok' => true,
            'work' => work_summary($w, $uid) + [
                'version' => (int) $w['Version'],
                'updatedBy' => $w['UpdatedByName'],
                'items' => array_map(fn($i) => [
                    'ej' => (int) $i['EjercicioId'], 'name' => $i['Name'], 'type' => $i['Type'], 'mu' => $i['Mu'],
                    'sets' => (int) $i['Sets'], 'reps' => (int) $i['Reps'],
                ], $items),
            ],
        ]);

    case 'alumnos':
        if (!is_profesor($me)) {
            json_error('Solo para profesores.', 403);
        }
        $alumnos = rows(
            'SELECT a.id, a.Name, a.Email, a.Objetivos,
                    (SELECT MAX(w.UpdatedAt) FROM Works w WHERE w.UserId = a.id) AS LastUpdate
             FROM Users a WHERE a.ProfesorId = ? ORDER BY a.Name',
            [$uid]
        );
        $out = [];
        foreach ($alumnos as $a) {
            $ws = rows(LIST_SQL . 'WHERE w.UserId = ? ORDER BY w.Dia IS NULL, w.Dia, w.Name', [(int) $a['id']]);
            $out[] = [
                'id' => (int) $a['id'], 'name' => $a['Name'], 'email' => $a['Email'], 'objetivos' => $a['Objetivos'],
                'lastUpdate' => $a['LastUpdate'],
                'works' => array_map(fn($w) => work_summary($w, $uid), $ws),
            ];
        }
        json_out(['ok' => true, 'alumnos' => $out]);

    case 'save':
        $id = (int) ($in['id'] ?? 0);
        $name = str_in($in, 'name', 81);
        $dia = isset($in['dia']) && $in['dia'] !== '' && $in['dia'] !== null ? (int) $in['dia'] : null;
        $items = is_array($in['items'] ?? null) ? $in['items'] : [];

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Poné un nombre a la rutina';
        } elseif (mb_strlen($name) > 80) {
            $errors['name'] = 'Máximo 80 caracteres';
        }
        if ($dia !== null && ($dia < 1 || $dia > 7)) {
            $errors['dia'] = 'Día inválido';
        }
        if ($errors) {
            json_error('Revisá los datos de la rutina.', 422, ['code' => 'invalid', 'fields' => $errors]);
        }
        if (!$items) { // 5.2
            json_error('Añade al menos un ejercicio', 422, ['code' => 'empty']);
        }
        if (count($items) > 40) {
            json_error('Una rutina puede tener hasta 40 ejercicios.', 422, ['code' => 'invalid']);
        }
        $clean = [];
        $seen = [];
        foreach ($items as $it) {
            $ej = (int) ($it['ej'] ?? 0);
            $sets = (int) ($it['sets'] ?? 0);
            $reps = (int) ($it['reps'] ?? 0);
            if (isset($seen[$ej])) {
                json_error('Hay ejercicios repetidos en la rutina.', 422, ['code' => 'invalid']);
            }
            if ($sets < 1 || $sets > 50 || $reps < 1 || $reps > 100) {
                json_error('Las series deben estar entre 1 y 50 y las repeticiones entre 1 y 100.', 422, ['code' => 'invalid', 'ej' => $ej]);
            }
            $seen[$ej] = true;
            $clean[] = [$ej, $sets, $reps];
        }
        $ids = array_keys($seen);
        $found = rows('SELECT id FROM Ejercicios WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
        if (count($found) !== count($ids)) {
            json_error('Algún ejercicio ya no existe en la biblioteca.', 422, ['code' => 'invalid']);
        }

        $db = Conect();
        $db->begin_transaction();
        try {
            if ($id) {
                $w = load_work($id);
                if (!$w || !can_edit($w, $uid)) {
                    $db->rollback();
                    json_error('No podés editar esta rutina.', 403);
                }
                // Control optimista de concurrencia (5.3)
                $affected = run('UPDATE Works SET Name = ?, Dia = ?, Version = Version + 1, UpdatedBy = ?, UpdatedAt = NOW()
                                 WHERE id = ? AND Version = ?', [$name, $dia, $uid, $id, (int) ($in['version'] ?? -1)]);
                if ($affected === 0) {
                    $db->rollback();
                    $cur = load_work($id);
                    json_error('Esta rutina fue modificada por otro usuario recientemente. Por favor, recarga la página.', 409, [
                        'code' => 'conflict', 'updatedBy' => $cur['UpdatedByName'] ?? null,
                    ]);
                }
                run('DELETE FROM WorkItems WHERE WorkId = ?', [$id]);
            } else {
                $owner = $uid;
                if (!empty($in['ownerId']) && (int) $in['ownerId'] !== $uid) {
                    $al = row('SELECT id FROM Users WHERE id = ? AND ProfesorId = ?', [(int) $in['ownerId'], $uid]);
                    if (!$al || !is_profesor($me)) {
                        $db->rollback();
                        json_error('Solo podés crear rutinas para tus alumnos vinculados.', 403);
                    }
                    $owner = (int) $al['id'];
                }
                run('INSERT INTO Works (UserId, CreatedBy, Name, Dia, UpdatedBy) VALUES (?,?,?,?,?)', [$owner, $uid, $name, $dia, $uid]);
                $id = last_id();
            }
            $st = $db->prepare('INSERT INTO WorkItems (WorkId, Pos, EjercicioId, Sets, Reps) VALUES (?,?,?,?,?)');
            foreach ($clean as $pos => [$ej, $sets, $reps]) {
                $st->bind_param('iiiii', $id, $pos, $ej, $sets, $reps);
                $st->execute();
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollback();
            throw $e;
        }
        $w = load_work($id);
        json_out(['ok' => true, 'id' => $id, 'version' => (int) $w['Version']], 200);

    case 'delete':
        $w = load_work((int) ($in['id'] ?? 0));
        if (!$w || !can_edit($w, $uid)) {
            json_error('No podés borrar esta rutina.', 403);
        }
        run('DELETE FROM Works WHERE id = ?', [(int) $w['id']]);
        json_out(['ok' => true]);

    case 'share':
        $w = load_work((int) ($in['id'] ?? 0));
        if (!$w || !can_edit($w, $uid)) {
            json_error('No podés compartir esta rutina.', 403);
        }
        run('DELETE FROM Tokens WHERE date < NOW() - INTERVAL ? MINUTE', [cfg('token_minutes')]);
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (row('SELECT 1 FROM Tokens WHERE n = ?', [$code]));
        run('INSERT INTO Tokens (n, date, ruti, CreatedBy) VALUES (?, NOW(), ?, ?)', [$code, (int) $w['id'], $uid]);
        json_out(['ok' => true, 'code' => $code, 'minutes' => cfg('token_minutes')]);

    case 'redeem':
        $code = strtoupper(str_in($in, 'code', 10));
        $t = $code !== '' ? row('SELECT n, ruti FROM Tokens WHERE n = ? AND date >= NOW() - INTERVAL ? MINUTE', [$code, cfg('token_minutes')]) : null;
        $w = $t ? load_work((int) $t['ruti']) : null;
        if (!$w) {
            json_error('Código no encontrado o vencido', 404, ['code' => 'not_found']);
        }
        if (can_edit($w, $uid)) {
            json_error('Esa rutina ya es tuya.', 409, ['code' => 'own']);
        }
        run('INSERT IGNORE INTO WorkShares (WorkId, UserId) VALUES (?, ?)', [(int) $w['id'], $uid]);
        run('DELETE FROM Tokens WHERE n = ?', [$code]); // un solo uso
        json_out(['ok' => true, 'work' => ['id' => (int) $w['id'], 'name' => $w['Name']]]);

    case 'unshare':
        run('DELETE FROM WorkShares WHERE WorkId = ? AND UserId = ?', [(int) ($in['id'] ?? 0), $uid]);
        json_out(['ok' => true]);

    case 'link':
        if (is_profesor($me)) {
            json_error('Las cuentas de profesor no se vinculan con otro profesor.', 403);
        }
        $code = strtoupper(preg_replace('/\s+/', '', str_in($in, 'code', 20)));
        $prof = $code !== '' ? row("SELECT id, Name FROM Users WHERE CodigoProfesor = ? AND Role = 'profesor'", [$code]) : null;
        if (!$prof) { // 5.1
            json_error('Código no encontrado', 404, ['code' => 'not_found']);
        }
        run('UPDATE Users SET ProfesorId = ? WHERE id = ?', [(int) $prof['id'], $uid]);
        json_out(['ok' => true, 'profesor' => ['id' => (int) $prof['id'], 'name' => $prof['Name']]]);

    case 'unlink':
        run('UPDATE Users SET ProfesorId = NULL WHERE id = ?', [$uid]);
        json_out(['ok' => true]);

    default:
        json_error('Acción desconocida', 404);
}

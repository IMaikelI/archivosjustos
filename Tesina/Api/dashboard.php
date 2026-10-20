<?php
/** Resumen para Inicio (CU-03). GET */
define('MR_API', true);
require __DIR__ . '/../Conection.php';

api_start(['GET']);
$u = require_api_auth();
$uid = (int) $u['id'];
$today = (int) date('N');

$works = rows(
    'SELECT w.id, w.Name, w.Dia, w.UpdatedAt, COUNT(i.id) AS n
     FROM Works w LEFT JOIN WorkItems i ON i.WorkId = w.id
     WHERE w.UserId = ? GROUP BY w.id ORDER BY w.UpdatedAt DESC',
    [$uid]
);
$todayWork = null;
foreach ($works as $w) {
    if ((int) $w['Dia'] === $today) {
        $todayWork = $w;
        break;
    }
}
$todayItems = $todayWork ? rows(
    'SELECT e.Name, i.Sets, i.Reps FROM WorkItems i JOIN Ejercicios e ON e.id = i.EjercicioId WHERE i.WorkId = ? ORDER BY i.Pos',
    [(int) $todayWork['id']]
) : [];

$records = rows(
    'SELECT r.EjercicioId, e.Name, r.Peso, r.Fecha,
            (SELECT r2.Peso FROM Records r2 WHERE r2.UserId = r.UserId AND r2.EjercicioId = r.EjercicioId
               AND (r2.Fecha < r.Fecha OR (r2.Fecha = r.Fecha AND r2.id < r.id)) ORDER BY r2.Fecha DESC, r2.id DESC LIMIT 1) AS Prev
     FROM Records r JOIN Ejercicios e ON e.id = r.EjercicioId
     WHERE r.UserId = ? ORDER BY r.Fecha DESC, r.id DESC LIMIT 5',
    [$uid]
);
$totRecords = (int) row('SELECT COUNT(*) c FROM Records WHERE UserId = ?', [$uid])['c'];
$shared = (int) row('SELECT COUNT(*) c FROM WorkShares WHERE UserId = ?', [$uid])['c'];

$people = null;
if ($u['Role'] === 'profesor') {
    $people = rows(
        'SELECT a.id, a.Name, (SELECT COUNT(*) FROM Works w WHERE w.UserId = a.id) AS rutinas
         FROM Users a WHERE a.ProfesorId = ? ORDER BY a.Name LIMIT 6',
        [$uid]
    );
    $alumnos = (int) row('SELECT COUNT(*) c FROM Users WHERE ProfesorId = ?', [$uid])['c'];
} else {
    $prof = $u['ProfesorId'] ? row('SELECT Name FROM Users WHERE id = ?', [(int) $u['ProfesorId']]) : null;
}

json_out([
    'ok' => true,
    'user' => ['name' => $u['Name'], 'role' => $u['Role']],
    'today' => ['dia' => $today, 'label' => MR_DIAS[$today]],
    'todayWork' => $todayWork ? [
        'id' => (int) $todayWork['id'],
        'name' => $todayWork['Name'],
        'items' => array_map(fn($i) => ['name' => $i['Name'], 'sets' => (int) $i['Sets'], 'reps' => (int) $i['Reps']], $todayItems),
    ] : null,
    'works' => array_map(fn($w) => [
        'id' => (int) $w['id'], 'name' => $w['Name'], 'n' => (int) $w['n'],
        'dia' => $w['Dia'] ? MR_DIAS[(int) $w['Dia']] : null,
    ], array_slice($works, 0, 4)),
    'stats' => ['rutinas' => count($works), 'marcas' => $totRecords, 'compartidas' => $shared],
    'records' => array_map(fn($r) => [
        'ejercicioId' => (int) $r['EjercicioId'], 'name' => $r['Name'], 'peso' => (float) $r['Peso'], 'fecha' => $r['Fecha'],
        'delta' => $r['Prev'] !== null ? round((float) $r['Peso'] - (float) $r['Prev'], 2) : null,
    ], $records),
    'profesor' => $u['Role'] === 'profesor' ? null : ($prof['Name'] ?? null),
    'alumnos' => $u['Role'] === 'profesor' ? [
        'total' => $alumnos,
        'list' => array_map(fn($a) => ['id' => (int) $a['id'], 'name' => $a['Name'], 'rutinas' => (int) $a['rutinas']], $people),
    ] : null,
    'isNew' => !$works && !$totRecords,
]);

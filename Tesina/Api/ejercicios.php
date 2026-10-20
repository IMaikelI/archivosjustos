<?php
/**
 * Biblioteca de ejercicios y marcas personales (CU-06).
 *   GET  ?action=list&q=&mu=&type=
 *   GET  ?action=musculos
 *   GET  ?action=get&id=           detalle + marcas del usuario
 *   POST ?action=record {ejercicioId, peso, fecha}
 *   POST ?action=record_delete {id}
 */
define('MR_API', true);
require __DIR__ . '/../Conection.php';

$method = api_start(['GET', 'POST']);
$me = require_api_auth();
$uid = (int) $me['id'];
$action = $_GET['action'] ?? 'list';
if (in_array($action, ['record', 'record_delete'], true) && $method !== 'POST') {
    json_error('Método no permitido', 405);
}

function ex_out(array $e): array
{
    return [
        'id' => (int) $e['id'], 'name' => $e['Name'], 'type' => $e['Type'],
        'mu' => (int) $e['Mu'], 'muName' => $e['MuName'] ?? null,
        'img' => $e['Img'] ? media_url($e['Img']) : null,
        'hasVid' => !empty($e['Vid']),
    ];
}

/** URLs externas tal cual; rutas locales relativas al proyecto. Solo http(s). */
function media_url(?string $u): ?string
{
    if (!$u) {
        return null;
    }
    if (preg_match('~^https?://~i', $u)) {
        return $u;
    }
    return preg_match('~^[\w/.\-]+$~', $u) ? url($u) : null;
}

/** Convierte un enlace de YouTube en URL de inserción; otros videos se reproducen con <video>. */
function video_info(?string $v): ?array
{
    if (!$v) {
        return null;
    }
    if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})~', $v, $m)) {
        return ['kind' => 'youtube', 'src' => 'https://www.youtube-nocookie.com/embed/' . $m[1]];
    }
    $src = media_url($v);
    return $src ? ['kind' => 'file', 'src' => $src] : null;
}

switch ($action) {
    case 'list':
        $where = [];
        $params = [];
        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            foreach (preg_split('/\s+/', mb_substr($q, 0, 60)) as $word) {
                $where[] = 'e.Name LIKE ?';
                $params[] = '%' . addcslashes($word, '%_\\') . '%';
            }
        }
        if (isset($_GET['mu']) && $_GET['mu'] !== '') {
            $where[] = 'e.Mu = ?';
            $params[] = (int) $_GET['mu'];
        }
        if (in_array($_GET['type'] ?? '', ['Libre', 'Cable'], true)) {
            $where[] = 'e.Type = ?';
            $params[] = $_GET['type'];
        }
        $sql = 'SELECT e.id, e.Name, e.Type, e.Mu, e.Img, e.Vid, m.Name AS MuName
                FROM Ejercicios e LEFT JOIN Musculos m ON m.id = e.Mu'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY e.Mu, e.Name';
        json_out(['ok' => true, 'items' => array_map('ex_out', rows($sql, $params))]);

    case 'musculos':
        json_out(['ok' => true, 'items' => rows(
            'SELECT m.id, m.Name AS name, COUNT(e.id) AS n FROM Musculos m JOIN Ejercicios e ON e.Mu = m.id GROUP BY m.id ORDER BY m.id'
        )]);

    case 'get':
        $e = row('SELECT e.*, m.Name AS MuName FROM Ejercicios e LEFT JOIN Musculos m ON m.id = e.Mu WHERE e.id = ?', [(int) ($_GET['id'] ?? 0)]);
        if (!$e) {
            json_error('Ejercicio no encontrado', 404);
        }
        $recs = rows('SELECT id, Peso, Fecha FROM Records WHERE UserId = ? AND EjercicioId = ? ORDER BY Fecha, id', [$uid, (int) $e['id']]);
        $prev = null;
        $out = [];
        foreach ($recs as $r) {
            $p = (float) $r['Peso'];
            $out[] = ['id' => (int) $r['id'], 'peso' => $p, 'fecha' => $r['Fecha'], 'delta' => $prev === null ? null : round($p - $prev, 2)];
            $prev = $p;
        }
        json_out(['ok' => true, 'ejercicio' => ex_out($e) + [
            'info' => $e['Info'] ?? null,
            'video' => video_info($e['Vid'] ?? null),
            'records' => array_reverse($out),
            'best' => $out ? max(array_column($out, 'peso')) : null,
        ]]);

    case 'record':
        $in = input();
        $ej = (int) ($in['ejercicioId'] ?? 0);
        $peso = (float) str_replace(',', '.', (string) ($in['peso'] ?? ''));
        $fecha = (string) ($in['fecha'] ?? '');
        $errors = [];
        if (!row('SELECT 1 FROM Ejercicios WHERE id = ?', [$ej])) {
            json_error('Ejercicio no encontrado', 404);
        }
        if ($peso <= 0 || $peso > 1000) {
            $errors['peso'] = 'Ingresá un peso entre 0,5 y 1000 kg';
        }
        $d = DateTime::createFromFormat('!Y-m-d', $fecha);
        if (!$d || $d->format('Y-m-d') !== $fecha) {
            $errors['fecha'] = 'Fecha inválida';
        } elseif ($d > new DateTime('today')) {
            $errors['fecha'] = 'La fecha no puede ser futura';
        } elseif ($d < new DateTime('1950-01-01')) {
            $errors['fecha'] = 'Fecha inválida';
        }
        if ($errors) {
            json_error('Revisá los datos de la marca.', 422, ['code' => 'invalid', 'fields' => $errors]);
        }
        run('INSERT INTO Records (UserId, EjercicioId, Peso, Fecha) VALUES (?,?,?,?)', [$uid, $ej, round($peso, 2), $fecha]);
        json_out(['ok' => true, 'id' => last_id()], 201);

    case 'record_delete':
        $in = input();
        $n = run('DELETE FROM Records WHERE id = ? AND UserId = ?', [(int) ($in['id'] ?? 0), $uid]);
        if (!$n) {
            json_error('Marca no encontrada', 404);
        }
        json_out(['ok' => true]);

    default:
        json_error('Acción desconocida', 404);
}

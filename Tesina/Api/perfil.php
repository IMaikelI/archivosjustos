<?php
/**
 * Perfil (CU-02).
 *   GET                  datos del perfil
 *   POST ?action=update  multipart: name, peso, altura, objetivos, photo (opcional)
 */
define('MR_API', true);
require __DIR__ . '/../Conection.php';

$method = api_start(['GET', 'POST']);
$u = require_api_auth();
$uid = (int) $u['id'];

function profile_payload(int $uid): array
{
    $u = row('SELECT id, Name, Email, Role, Photo, Peso, Altura, Objetivos, CodigoProfesor, ProfesorId FROM Users WHERE id = ?', [$uid]);
    $prof = $u['ProfesorId'] ? row('SELECT id, Name FROM Users WHERE id = ?', [(int) $u['ProfesorId']]) : null;
    $alumnos = $u['Role'] === 'profesor' ? (int) row('SELECT COUNT(*) c FROM Users WHERE ProfesorId = ?', [$uid])['c'] : null;
    return [
        'id' => (int) $u['id'],
        'name' => $u['Name'],
        'email' => $u['Email'],
        'role' => $u['Role'],
        'photo' => photo_url($u['Photo']),
        'peso' => $u['Peso'] !== null ? (float) $u['Peso'] : null,
        'altura' => $u['Altura'] !== null ? (int) $u['Altura'] : null,
        'objetivos' => $u['Objetivos'] ?? '',
        'codigo' => $u['Role'] === 'profesor' ? $u['CodigoProfesor'] : null,
        'profesor' => $prof ? ['id' => (int) $prof['id'], 'name' => $prof['Name']] : null,
        'alumnos' => $alumnos,
    ];
}

if ($method === 'GET') {
    json_out(['ok' => true, 'profile' => profile_payload($uid)]);
}

if (($_GET['action'] ?? '') !== 'update') {
    json_error('Acción desconocida', 404);
}

// Si el cuerpo supera post_max_size PHP descarta todo: se informa como foto demasiado grande.
$len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($len > 0 && empty($_POST) && empty($_FILES)) {
    json_error('Formato inválido o excede 5MB', 413, ['code' => 'invalid', 'fields' => ['photo' => 'Formato inválido o excede 5MB']]);
}

$in = input();
$errors = [];
$name = str_in($in, 'name', 81);
if ($name === '') {
    $errors['name'] = 'El nombre es obligatorio';
} elseif (mb_strlen($name) > 80) {
    $errors['name'] = 'Máximo 80 caracteres';
}

$parseNum = static function (string $raw, float $min, float $max, bool $int, string $label) {
    $raw = str_replace(',', '.', trim($raw));
    if ($raw === '') {
        return null;
    }
    if (!preg_match($int ? '/^\d+$/' : '/^\d+(\.\d{1,2})?$/', $raw)) {
        return ['err' => "$label debe ser un número" . ($int ? ' entero' : '')];
    }
    $v = $int ? (int) $raw : (float) $raw;
    if ($v < $min || $v > $max) {
        return ['err' => "$label debe estar entre $min y $max"];
    }
    return $v;
};
$peso = $parseNum(str_in($in, 'peso', 10), 20, 400, false, 'El peso');
$altura = $parseNum(str_in($in, 'altura', 10), 80, 260, true, 'La altura');
if (is_array($peso)) {
    $errors['peso'] = $peso['err'];
}
if (is_array($altura)) {
    $errors['altura'] = $altura['err'];
}
$objetivos = str_in($in, 'objetivos', 501);
if (mb_strlen($objetivos) > 500) {
    $errors['objetivos'] = 'Máximo 500 caracteres';
}

// Foto (CU-02, 2.2)
$newPhoto = null;
$f = $_FILES['photo'] ?? null;
if ($f && $f['error'] !== UPLOAD_ERR_NO_FILE) {
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = ($f['error'] === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name']))
        ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) : '';
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > cfg('photo_max_bytes') || !isset($allowed[$mime]) || !@getimagesize($f['tmp_name'])) {
        $errors['photo'] = 'Formato inválido o excede 5MB';
    } else {
        $newPhoto = ['tmp' => $f['tmp_name'], 'ext' => $allowed[$mime]];
    }
}

if ($errors) {
    json_error('Revisá los campos marcados.', 422, ['code' => 'invalid', 'fields' => $errors]);
}

$photoPath = null;
if ($newPhoto) {
    $dir = __DIR__ . '/../uploads/perfiles';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $file = 'u' . $uid . '_' . bin2hex(random_bytes(6)) . '.' . $newPhoto['ext'];
    if (!move_uploaded_file($newPhoto['tmp'], "$dir/$file")) {
        json_error('No se pudo guardar la foto.', 500);
    }
    $photoPath = 'uploads/perfiles/' . $file;
    $old = $u['Photo'] ?? '';
    if ($old && str_starts_with($old, 'uploads/perfiles/') && is_file(__DIR__ . '/../' . $old)) {
        @unlink(__DIR__ . '/../' . $old);
    }
}

$sql = 'UPDATE Users SET Name = ?, Peso = ?, Altura = ?, Objetivos = ?' . ($photoPath ? ', Photo = ?' : '') . ' WHERE id = ?';
$params = [$name, $peso, $altura, $objetivos !== '' ? $objetivos : null];
if ($photoPath) {
    $params[] = $photoPath;
}
$params[] = $uid;
run($sql, $params);

json_out(['ok' => true, 'profile' => profile_payload($uid)]);

<?php

require '../Send-Recieve/Send-Recieve.php';
require '../conection.php';
$Con = Conect();

if (!isset($_GET['Code'])) {
    Send(false);
    exit();
}

$Code = $_GET['Code'];
$id = $_GET['id'];

/*
 * Buscar el token de forma segura
 */
$stmt = mysqli_prepare($Con, "SELECT * FROM tokens WHERE n = $Code");
mysqli_stmt_bind_param($stmt, "s", $Code);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$r = mysqli_fetch_assoc($result);

if (!$r) {
    Send(false);
    exit();
}


/*
 * Obtener el UserId de la obra
 */
$OgId = 0;

if (isset($r['ruti'])) {

    $Ruti = (int)$r['ruti'];

    $stmt2 = mysqli_prepare(
        $Con,
        "SELECT UserId FROM Works WHERE UserId = $Ruti"
    );

    mysqli_stmt_bind_param($stmt2, "i", $Ruti);
    mysqli_stmt_execute($stmt2);

    $result2 = mysqli_stmt_get_result($stmt2);
    $r2 = mysqli_fetch_assoc($result2);

    if ($r2 && isset($r2['UserId'])) {
        $OgId = $r2['UserId'];
    }
}


/*
 * Borrar el token para que no pueda utilizarse otra vez
 */
$stmt3 = mysqli_prepare(
    $Con,
    "DELETE FROM Tokens WHERE n = $Code"
);

mysqli_stmt_bind_param($stmt3, "s", $Code);
mysqli_stmt_execute($stmt3);


/*
 * Añadir el nuevo UserId a Works.UserId
 */
if (isset($r['ruti'])) {

    $Ruti = (int)$r['ruti'];

    $stmt4 = mysqli_prepare(
        $Con,
        "UPDATE Works
         SET UserId = CONCAT(UserId, ',', $id)
         WHERE id = $Ruti"
    );

    mysqli_stmt_bind_param($stmt4, "ii", $OgId, $Ruti);
    mysqli_stmt_execute($stmt4);
}


/*
 * Respuesta JSON
 */
Send(true);
exit();
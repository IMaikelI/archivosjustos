<?php
session_start();

include '../Conection.php';

$Conn = Conect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $a = $_POST['nombre'];
    $b = $_POST['contra'];

    $sql = "SELECT * FROM users WHERE Name = '$a' AND Pass = '$b'";

    $resultado = mysqli_query($Conn, $sql);
    $temp = mysqli_fetch_assoc($resultado);

    if ($temp) {
        $User = $temp['id'];
        $_SESSION['User'] = $User;
    } else {
        echo "Usuario o contraseña incorrectos";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuenta</title>
</head>

<body>

<header>
    <a href="Index.php">index</a>
</header>

<form class="login" method="post" action="../index.php">
    <input 
        type="text" 
        placeholder="Nombre de usuario" 
        name="nombre"
    >

    <input 
        type="password" 
        placeholder="Contraseña" 
        name="contra"
    >

    <button type="submit">Log in</button>
</form>

</body>
</html>
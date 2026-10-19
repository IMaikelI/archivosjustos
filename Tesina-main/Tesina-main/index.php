<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio</title>
    <link rel="stylesheet" href="Css/Standard.css">

</head>
<body>
    <?php
    include 'Conection.php';
    ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
        $Con = Conect();
        $_SESSION['Conection'] = $Con;

            $_SESSION['User'] = 1;
        $_SESSION['a'] = 4;
        include 'Bl.php';
        include '1Rm.html';
    ?>

</body>
</html>
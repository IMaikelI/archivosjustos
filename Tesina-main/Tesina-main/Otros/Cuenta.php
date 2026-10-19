<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuenta</title>
</head>
<body>
    <header>
        <a href="Index.php">index</a>
    </header>

    <?php
        include '../Conection.php';
        //include '../bl.php';

        //$_SESSION['User'] = 1;

        $Conn = Conect();
        $User = $_SESSION['User']; //User = id de usuario

        if ($User !== 0) {
            $Sql = "SELECT Name, photo FROM Users WHERE id = $User";
            $PfpFetch = mysqli_fetch_assoc(r($Conn, $Sql));
            $Pfp = $PfpFetch['photo'];
            $NameFetch = mysqli_fetch_assoc(r($Conn, $Sql));
            $Name = $NameFetch['Name'];

            echo "<p>$Name</p>";
            echo "<img src='$Pfp'>";            
        } else{
            echo "<div class = 'no usuario'>
                <label>No hay una sesion iniciada</label>
                <a href='login.php' class='boton'>Iniciar sesion</a>
                <a href='login.php' class='boton'>Registrarse</a>

            </div>";
        };


    ?>
</body>
</html>


<div class="Bl">

<!-- Perfil -->
<?php
    require 'Conection.php';
    $User = $_SESSION['User'];
    
    $Pfpfetch = mysqli_fetch_assoc(r(conect(), C("Users", "Photo", "WHERE id = $User")));
    $Pfp = $Pfpfetch['Photo'];

    $Namefetch = mysqli_fetch_assoc(r(conect(), C("Users", "Name", "WHERE id = $User")));
    $Name = $Namefetch['Name'];

    //Añadir Js
    echo "<div>
            <div>
                <img src='$Pfp' alt=''>
                <p>$Name</p>
            </div>
        </div>";
?>

<a href="../index.php">Inicio</a>

<!-- Rutinas (Js pendiente) -->
<a href="./Rutinas/ListaRutinas.php">Rutinas</a>

<!-- Resto -->
<a href="Ejercicios/Ejercicios.php">Ejercicios</a>

<a href="" class="ajustes">Ajustes</a>
</div>

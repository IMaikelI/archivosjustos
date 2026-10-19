<?php
        error_reporting(E_ERROR | E_PARSE);
        require '../conection.php';

        $Con = Conect();
        $User = $_SESSION['User'];

        //Busca el id de las rutinas del usuario en la base de datos
        $Sql = "SELECT Works FROM Users WHERE id = $User";
        $temp = mysqli_fetch_assoc(r($Con, $Sql));
        $WorksFetch = $temp ['Works']; 
        $Works = implode(",", array_map('intval', explode(", ", $WorksFetch))); //String -> Int array
        
        //Fetch info
        $Sql = "SELECT * FROM Works WHERE UserId in ($Works)";
        $temp = r($Con, $Sql);

        echo "<div class='ListaRutinas' id='+Rutina'>
            <h1>Rutinas</h1>
        ";

        //Crea un div por cada rutina
        while ($row = mysqli_fetch_assoc($temp)) {

            //Datos (Str) -> array
            $Name = $row['Name'];
            $id = $row['id'];

            //Muestra cada rutina
            echo "<div id = 'Remove $id'>
                    <input type='hidden' name='Id' value='$id'>
                    <button Onclick='href($id)' id ='Edit $id'>$Name</button>

                    <button onclick = 'edit($id)' id= 'Cancel $id'>Editar</button> 
                    <button onclick = 'Delete($id)'>Borrar</button>
                    <button onclick = 'Link($id)'> Vincular </button>
            </div><br>";
        }
        echo "<button onclick = 'NewRoutine()' Id = '+' class = '+'> + </button>
              <button onclick = 'vinc($User)'> vincular rutina </button>
        </div>";

        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            if (isset($_POST['Nr'])) {

                $NewRoutine = $_POST['Nr'];

                $Sql = "INSERT INTO Works (UserId, Name, Ejercicios, Sets, Reps) VALUES ('$User', '$NewRoutine', '', '', '')";

                r($Con, $Sql);

            }

            if (isset($_POST['Edit'])) {

                $Edited = $_POST['Edit'];
                $Edit = $Edited[0];
                $id = $Edited[1];

                $Sql = "UPDATE Works
                        SET Name = '$Edit' 
                        WHERE Id = $id";

                r($Con, $Sql);
            }

            if (isset($_POST['Delete'])) {

                $Id = $_POST['Delete'];

                $Sql = "DELETE FROM Works WHERE id = $Id";

                r($Con, $Sql);

                ResetId($Con, "Works");
            }

            if (isset($_POST['id'])) {

                    $Id = $_POST['id'];
                    $tem = rand (0, 99999);
                    
                    $Sql = "INSERT INTO Tokens (n, ruti, date) VALUES ($tem, $Id, NOW())";

                    r($Con, $Sql);
            }}
            ?>
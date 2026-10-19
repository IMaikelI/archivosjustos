<?php
    require '../Conection.php';
    require '../Send-Recieve/Send-Recieve.php';

    error_reporting(E_ALL);
    ini_set('display_errors', 0);

    $Con = Conect();
    $User = $_SESSION['User'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['List'])) {


            $sql = "SELECT records FROM users WHERE id = $User";
            $temp = r($Con, $sql);
            while ($row = mysqli_fetch_assoc($temp)) {
                $rec = $row['records']; 
            
                $RecEj = explode("; ", $rec); //Separa x Ejercicio}
            
                $rec = [];
                for ($i=0; $i < count($RecEj); $i++) { 
                    $RecI[$i] = explode(", ", $RecEj[$i]); //Separa Ej - Registros
                
                    $e = $RecI[$i][0]; //index 1001 2001 etc
                    $rec[$e] = explode(". ", $RecI[$i][1]); //final ej: array 1001(10, 10/5) array 2005(3', 7/3)
                
                };

                $a = $rec;
                $Ids = implode(', ', array_keys($a));
                $sql = "SELECT * FROM Ejercicios WHERE id IN ($Ids)";
                $r = r($Con, $sql);

                while ($row = mysqli_fetch_assoc($r)) {

                    if (is_array($rec)) {
                        foreach ($rec as $rec => $value) { // rec = (1001, 2001, etc) - value = (10 - 10/5, etc)
                            for ($i=0; $i < count($value); $i++) { 
                                $values[$rec][$i] = explode(" - ", $value[$i]); // Ejercicio(n(peso)(fecha))(n(peso)(fecha))
                            };
                        };
                    };
                };
            };
            $_SESSION ['data'] = $values[$_POST['List']];
    };
}
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            Send($_SESSION ['data']);
        };
?>
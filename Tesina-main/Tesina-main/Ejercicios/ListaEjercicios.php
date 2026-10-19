<?php

    error_reporting(E_ALL);
    ini_set('display_errors', 0);


    require '../Conection.php';
    require '../Send-Recieve/Send-Recieve.php';
    $Con = Conect();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (isset($_POST['Conditions'])) {

            $Filter = $_POST['Conditions'];
            $wId = $_SESSION['wId'];
            $Id = 1;

            $Sql = "SELECT * FROM ejercicios $Filter ";
                $r = r($Con, $Sql);

            //Verifica si ya esta el ejercicio
            $Sql2 = "SELECT * FROM Works WHERE id = $wId";
                $r2 = r($Con, $Sql2);
            while ($row = mysqli_fetch_assoc($r2)){
                $Set = explode(", ", $row['Ejercicios']);
            };

            while ($row = mysqli_fetch_assoc($r)) {
                if (in_array($row['id'], $Set)) {
                    $Ejercicio ['Num'][$row['id']] = "$row[Name], true , $row[Vid], $row[info]";
                } else {
                    $Ejercicio ['Num'][$row['id']] = "$row[Name], false, $row[Vid], $row[info]";
                }}

                $_SESSION ['Ejercicio'] = $Ejercicio;
            }
            
        }
                if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                Send($_SESSION ['Ejercicio']);
    }
    

?>

<?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['a'])) {
        
        $Id = $_POST['a'];
        $Wid = $_SESSION['wId'];

        $Sql = "SELECT * FROM Works WHERE id = $Wid";
        $temp = r($Con, $Sql);

        while ($row = mysqli_fetch_assoc($temp)) {

            $Exp = explode(", ", $row['Ejercicios']); //Datos (Str) -> array
            if ($row['Ejercicios'] == "") {
                $Imp = "$Id";
            } else {
                array_push($Exp, $Id); //Add Id
                $Imp = implode(", ", $Exp); //Array -> Datos (Str)
            };

            $Sets = explode(", ", $row['Sets']); //Datos (Str) -> array
            if ($row['Sets'] == "") {
                $SetsImp = "0";
            } else {
                array_push($Sets, "0"); //Add entry
                $SetsImp = implode(", ", $Sets); //Array -> Datos (Str)
            };

            $Reps = explode(", ", $row['Reps']); //Datos (Str) -> array
            if ($row['Reps'] == "") {
                $RepsImp = "0";
            } else {
                array_push($Reps, "0"); //Add entry
                $RepsImp = implode(", ", $Reps); //Array -> Datos (Str) 
            };


            $add = "UPDATE Works
                SET Ejercicios = '$Imp', 
                Sets = '$SetsImp',
                Reps = '$RepsImp'
                WHERE id = $Wid";
                r($Con, $add);
        }
    }
}
?>

<?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST'){
        if (isset($_POST['remove'])){
            $temp = explode ("-", $_POST['remove']);
            
            $RemoveId = $temp[0]; //Id a borrar
            $String = $temp[1]; //Array completo
            $Id = $_SESSION['wId'];

            $Sql = "SELECT Ejercicios, Reps, sets FROM WORKS WHERE id = $Id";
                $r = r($Con, $Sql);

                $temp = explode(",", $String); //Str -> Array

                $Key = array_search($RemoveId, $temp); //Key de (Id a borrar)
                unset($temp[$Key]); //Eliminar
                $aStr = implode(", ", array_values($temp)); //Reiniciar las keys

            if ($row = mysqli_fetch_assoc($r)) {
                $Reps = explode(", ", $row['Reps']);
                unset ($Reps[$Key]);
                $aReps = implode(", ", array_values($Reps));

                $Sets = explode(", ", $row['sets']);
                unset($Sets[$Key]);
                $aSets = implode(", ", array_values($Sets));
            }


                $Sql = "UPDATE Works
                        SET Ejercicios = '$aStr',
                        Sets = '$aSets',
                        Reps = '$aReps' 
                        WHERE Id = $Id";

                r($Con, $Sql);
                return;
        }

        if (isset($_POST['NewSR'])) {

            $Id = $_SESSION['wId'];
            $NewSr = explode(",", $_POST['NewSR']);

            $Value = $NewSr[0];
            $Key = explode("-", $NewSr[1]);
        
            $Sql = "SELECT $Key[0] FROM Works WHERE id = $Id";
            $r = r($Con, $Sql);

            if ($row = mysqli_fetch_assoc($r)) {
                $Sr = explode(", ", $row[$Key[0]]);
                $Sr [$Key[1]] = $Value;
                $aSr = implode(", ", array_values($Sr));
            }

            $Sql = "UPDATE Works
                    SET $Key[0] = '$aSr'
                    WHERE id = $Id";
            r($Con, $Sql);

        }
    }

?>

<?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST'){
        if (isset($_POST['Ch'])) {

            $Id = $_SESSION['wId'];
            $dir = explode(",", $_POST['Ch'])[1];

            if ($dir == 0) {
                $i = explode(",", $_POST["Ch"])[0] -  1;
            } else {
                $i = explode(",", $_POST["Ch"])[0];
            };
            
            
            $Sql = "SELECT * FROM Works WHERE id = $Id";
            $r = r($Con, $Sql);

            if ($row = mysqli_fetch_assoc($r)) {

            $Exp = explode(", ", $row['Ejercicios']); //Datos (Str) -> array
            $Sets = explode(", ", $row['Sets']); //Datos (Str) -> array
            $Reps = explode(", ", $row['Reps']); //Datos (Str) -> array

            $temp = $Exp[$i];
            $Exp[$i] = $Exp[$i+1];
            $Exp[$i+1] = $temp;

            $tempS = $Sets[$i];
            $Sets[$i] = $Sets[$i+1];
            $Sets[$i+1] = $tempS;

            $tempR = $Reps[$i];
            $Reps[$i] = $Reps[$i+1];
            $Reps[$i+1] = $tempR;

            $iExp = implode(", ", $Exp);
            $iSets = implode(", ", $Sets);
            $iReps = implode(", ", $Reps);
            

                $Sql = "UPDATE Works
                        SET Ejercicios = '$iExp',
                        Sets = '$iSets',
                        Reps = '$iReps' 
                        WHERE Id = $Id";

                r($Con, $Sql);
            };
        };
    };
?>

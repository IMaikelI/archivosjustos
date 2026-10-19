<?php
    require '../Conection.php';

    $Con = Conect();
    $User = $_SESSION['User'];

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
            
            echo "<div class= 'record'>
                        <label>$row[Name]   </label>";

            foreach ($values[$row['id']] as $v => $a) {
                if ($v == 0) {
                    $plus = 0;
                };
                echo "<br>";
                echo "<label> peso = $a[0] </label>";
                echo "<label> fecha = $a[1] </label>";
                if ($plus !== 0) {
                    echo "<label> +". $a[0] - $plus ."</label>"; 
                }
                    $plus = $a[0];
                
            };
            echo "</div>";
        };






    $sql = "SELECT * FROM Ejercicios";
    $r = r($Con, $sql);
            echo "<form action='records.php' method='post'>
            <label>nuevo registro</label>
            <select name='ej'>
            ";
            while ($row = mysqli_fetch_assoc($r)) { 
                echo "<option value='$row[id]'> $row[Name] </option>";
                
            };
            echo "
            </select>
            <input type='int' placeholder='peso' name='weight'>
            <input type='date' placeholder='fecha'name='date'>
            <button type='submit'>Nuevo</button>
        </form>";

        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $ej = $_POST['ej'];
            $weight = $_POST['weight'];
            $date = implode("/", explode("-", $_POST['date']));

            $entry = [$weight, $date];

            $Update = $values; 

            if (in_array($ej, array_keys($Update))) {
                array_push($Update[$ej], $entry);
            } else {
                $Update[$ej][0] = $entry;
            };


            foreach ($Update as $i => $value) {
                $parts = [];
                foreach ($value as $val) {
                    $parts[] = "{$val[0]} - {$val[1]}";
                };
                $asc []= "$i, ". implode(". ", $parts);
            };
            $update = implode("; ", $asc);
            

            $sql3 = "UPDATE Users
                    SET records = '$update'
                    WHERE id = $User";
            $r = r($Con,$sql3);
            echo '<meta http-equiv="refresh" content="0;url=records.php">';

        };
    }
?>



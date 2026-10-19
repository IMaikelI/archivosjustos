<?php
session_start();

if (!function_exists('Conect')){
    function Conect() {
        $host = "localhost";
        $usuario = "root";
        $password = "";
        $baseDeDatos = "ironcore";

        $con = new mysqli($host, $usuario, $password, $baseDeDatos);

        if ($con->connect_error) {
            die("Conexión fallida: " . $con->connect_error);
        }

        return $con;
    }
}

if (!function_exists('r')){
    function r($con, $Sql) {
        $r = [];

        $r = $con->query($Sql);

        return $r;
    }
}
if (!function_exists('C')){
    function C($Tabla, $Objs, $Condiciones){
        $sql = "Select $Objs FROM $Tabla $Condiciones";
        
        return $sql;
    }
}

if (!function_exists('ResetId')){
    function ResetID($Con, $Tabla){

        $sql = "SELECT MAX(Id) as _Id FROM $Tabla";
        $r = r($Con, $sql);
        $temp = mysqli_fetch_assoc($r);
        
        $Id = $temp['_Id'];

        $Sql = "ALTER TABLE $Tabla AUTO_INCREMENT = $Id";
        r($Con, $Sql);

    }
}
?>


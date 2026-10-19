<?php
    function Send($Data){
        header('Content-Type: application/json');

        echo json_encode($Data);
    }
?>